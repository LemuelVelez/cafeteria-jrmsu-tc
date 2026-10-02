<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
use App\Models\PaymentModel;
use App\Models\ProductAddonModel;
use App\Models\ProductModel;
use App\Models\PromoUsageModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use Throwable;

class OrderService
{
    private BaseConnection $db;
    private InventoryService $inventory;
    private NotificationService $notifications;
    private AuditLogService $audit;

    public function __construct(
        private readonly OrderModel $orders = new OrderModel(),
        private readonly OrderItemModel $items = new OrderItemModel(),
        private readonly ProductModel $products = new ProductModel(),
        private readonly ProductAddonModel $addons = new ProductAddonModel(),
        private readonly PromoService $promoService = new PromoService(),
        private readonly SettingModel $settings = new SettingModel(),
    ) {
        $this->db = db_connect();
        $this->inventory = new InventoryService($this->db);
        $this->notifications = new NotificationService($this->db);
        $this->audit = new AuditLogService($this->db);
    }

    public function create(array $payload, array $actor): array
    {
        $actorRole = (string) ($actor['role'] ?? '');
        $actorId = (int) ($actor['id'] ?? 0);
        if (! in_array($actorRole, ['customer', 'cashier'], true) || $actorId < 1) {
            throw new \DomainException('You are not allowed to create orders.');
        }

        $cart = $payload['items'] ?? [];
        if (! is_array($cart) || $cart === []) {
            throw new \DomainException('The cart is empty.');
        }

        $orderType = OrderType::tryFrom((string) ($payload['order_type'] ?? OrderType::Pickup->value));
        if (! $orderType) {
            throw new \DomainException('Invalid order type.');
        }
        $settingKey = $orderType === OrderType::Delivery ? 'delivery_enabled' : 'pickup_enabled';
        if ((string) $this->settings->getValue($settingKey, '1') !== '1') {
            throw new \DomainException(ucfirst($orderType->value) . ' ordering is currently unavailable.');
        }

        $paymentMethod = PaymentMethod::forOrderType($orderType);
        if ((string) ($payload['payment_method'] ?? $paymentMethod->value) !== $paymentMethod->value) {
            throw new \DomainException('Pickup orders require Cash on Pickup and delivery orders require Cash on Delivery.');
        }
        $deliveryAddress = trim((string) ($payload['delivery_address'] ?? ''));
        if ($orderType === OrderType::Delivery && mb_strlen($deliveryAddress) < 5) {
            throw new \DomainException('A delivery address is required.');
        }
        if (mb_strlen($deliveryAddress) > 1000) {
            throw new \DomainException('The delivery address must not exceed 1000 characters.');
        }

        $requestToken = trim((string) ($payload['request_token'] ?? ''));
        if ($requestToken === '' || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $requestToken)) {
            throw new \DomainException('A valid order request token is required.');
        }

        $customerId = $actorRole === 'customer' ? $actorId : 0;
        if ($actorRole === 'cashier') {
            $customerIdInput = $payload['customer_id'] ?? null;
            if ($customerIdInput !== null && $customerIdInput !== '' && $customerIdInput !== 0 && $customerIdInput !== '0') {
                $validatedCustomerId = filter_var($customerIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($validatedCustomerId === false) {
                    throw new \DomainException('Selected customer is invalid.');
                }
                $customerId = (int) $validatedCustomerId;
            }
            if ($customerId > 0) {
                $customer = (new UserModel())->where(['id' => $customerId, 'role' => 'customer', 'status' => 'active'])->first();
                if (! $customer) {
                    throw new \DomainException('Selected customer is not active.');
                }
            }
        }

        $existingOrder = $this->findIdempotentOrder($requestToken, $actorRole, $actorId);
        if ($existingOrder) {
            return $existingOrder;
        }

        $this->db->transBegin();
        try {
            $subtotal = 0.0;
            $normalized = [];
            $lockedProducts = [];
            $requestedQuantities = [];

            foreach ($cart as $line) {
                if (! is_array($line)) {
                    throw new \DomainException('One or more cart items are invalid.');
                }
                $productId = filter_var($line['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $quantity = filter_var($line['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
                if ($productId === false || $quantity === false) {
                    throw new \DomainException('Each cart item must have a valid product and a quantity from 1 to 99.');
                }
                $productId = (int) $productId;
                $quantity = (int) $quantity;

                if (! isset($lockedProducts[$productId])) {
                    $lockedProducts[$productId] = $this->db->query(
                        'SELECT products.* FROM products INNER JOIN categories ON categories.id=products.category_id WHERE products.id=? AND products.deleted_at IS NULL AND categories.deleted_at IS NULL AND categories.is_active=1 FOR UPDATE',
                        [$productId],
                    )->getRowArray();
                }
                $product = $lockedProducts[$productId];
                $requestedQuantities[$productId] = ($requestedQuantities[$productId] ?? 0) + $quantity;
                if (! $product || ! (bool) $product['is_available'] || (int) $product['stock'] < $requestedQuantities[$productId]) {
                    throw new \DomainException('One or more products are unavailable or out of stock.');
                }

                $selectedAddonIds = [];
                $submittedAddons = $line['addons'] ?? [];
                if (! is_array($submittedAddons)) {
                    throw new \DomainException('One or more selected add-ons are invalid.');
                }
                foreach ($submittedAddons as $selectedAddon) {
                    $addonIdInput = is_array($selectedAddon) ? ($selectedAddon['id'] ?? null) : $selectedAddon;
                    $addonId = filter_var($addonIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($addonId === false) {
                        throw new \DomainException('One or more selected add-ons are invalid.');
                    }
                    $selectedAddonIds[] = (int) $addonId;
                }
                $selectedAddonIds = array_values(array_unique($selectedAddonIds));
                $actualAddons = [];
                if ($selectedAddonIds !== []) {
                    $actualAddons = $this->addons->where('product_id', $productId)->where('is_active', 1)->whereIn('id', $selectedAddonIds)->findAll();
                    if (count($actualAddons) !== count($selectedAddonIds)) {
                        throw new \DomainException('One or more selected add-ons are invalid.');
                    }
                }

                $addonTotal = array_sum(array_map(static fn (array $addon): float => (float) $addon['price'], $actualAddons));
                $lineTotal = round(((float) $product['price'] + $addonTotal) * $quantity, 2);
                $subtotal += $lineTotal;
                $normalized[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'addon_total' => $addonTotal,
                    'addons_json' => json_encode(array_map(static fn (array $addon): array => [
                        'id' => (int) $addon['id'], 'name' => $addon['name'], 'price' => (float) $addon['price'],
                    ], $actualAddons), JSON_THROW_ON_ERROR),
                    'notes' => mb_substr(trim((string) ($line['notes'] ?? '')), 0, 500),
                    'line_total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $promoId = null;
            $discount = 0.0;
            if (! empty($payload['promo_code'])) {
                $promoResult = $this->promoService->calculate((string) $payload['promo_code'], $subtotal, true);
                $promoId = (int) $promoResult['promo']['id'];
                $discount = round((float) $promoResult['discount'], 2);
            }
            $deliveryFee = $orderType === OrderType::Delivery
                ? round(max(0.0, (float) $this->settings->getValue('delivery_fee', env('CAFETERIA_DELIVERY_FEE', 40.00))), 2)
                : 0.0;
            $total = round(max(0.0, $subtotal - $discount + $deliveryFee), 2);
            $initialStatus = $actorRole === 'cashier' ? OrderStatus::Confirmed->value : OrderStatus::Pending->value;

            $orderId = $this->orders->insert([
                'order_number' => generate_order_number(),
                'customer_id' => $customerId ?: null,
                'cashier_id' => $actorRole === 'cashier' ? $actorId : null,
                'order_type' => $orderType->value,
                'status' => $initialStatus,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'payment_method' => $paymentMethod->value,
                'payment_status' => 'pending',
                'delivery_address' => $orderType === OrderType::Delivery ? $deliveryAddress : null,
                'notes' => mb_substr(trim((string) ($payload['notes'] ?? '')), 0, 1000),
                'promo_id' => $promoId,
                'request_token' => $requestToken,
            ], true);
            if (! $orderId) {
                throw new \RuntimeException('Unable to save the order.');
            }

            foreach ($normalized as $line) {
                $product = $line['product'];
                if (! $this->items->insert([
                    'order_id' => $orderId,
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $product['price'],
                    'addon_total' => $line['addon_total'],
                    'line_total' => $line['line_total'],
                    'addons_json' => $line['addons_json'],
                    'notes' => $line['notes'],
                ])) {
                    throw new \RuntimeException('Unable to save an order item.');
                }
            }

            $persistedSubtotal = (float) ($this->db->query('SELECT COALESCE(SUM(line_total),0) total FROM order_items WHERE order_id=?', [$orderId])->getRow('total') ?? 0);
            if (abs($persistedSubtotal - $subtotal) > 0.009 || abs(($persistedSubtotal - $discount + $deliveryFee) - $total) > 0.009) {
                throw new \RuntimeException('Order totals failed the server-side reconciliation check.');
            }

            foreach ($requestedQuantities as $productId => $quantity) {
                $updatedProduct = $this->inventory->deductForOrder((int) $productId, (int) $quantity, (int) $orderId, $actorId);
                $this->notifications->lowStock($updatedProduct);
            }

            if (! (new PaymentModel())->insert([
                'order_id' => $orderId,
                'method' => $paymentMethod->value,
                'amount' => $total,
                'status' => 'pending',
            ])) {
                throw new \RuntimeException('Unable to save the order payment.');
            }
            $paymentAmount = (float) ($this->db->query('SELECT amount FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1', [$orderId])->getRow('amount') ?? -1);
            if (abs($paymentAmount - $total) > 0.009) {
                throw new \RuntimeException('Payment amount does not match the order total.');
            }

            if (! (new OrderStatusHistoryModel())->insert([
                'order_id' => $orderId,
                'user_id' => $actorId,
                'from_status' => null,
                'to_status' => $initialStatus,
                'note' => 'Order created.',
            ])) {
                throw new \RuntimeException('Unable to save the initial order status.');
            }
            if ($promoId) {
                if (! (new PromoUsageModel())->insert(['promo_id' => $promoId, 'order_id' => $orderId, 'user_id' => $customerId ?: $actorId])) {
                    throw new \RuntimeException('Unable to record promo usage.');
                }
                if (! $this->db->table('promos')->where('id', $promoId)->increment('used_count')) {
                    throw new \RuntimeException('Unable to update promo usage.');
                }
            }

            $order = $this->orders->find($orderId);
            if (! $order) {
                throw new \RuntimeException('The order was created but could not be loaded.');
            }
            $this->notifications->orderPlaced($order);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Unable to create the order.');
            }
            $this->db->transCommit();
            return $order;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $existingOrder = $this->findIdempotentOrder($requestToken, $actorRole, $actorId);
            if ($existingOrder) {
                return $existingOrder;
            }
            throw $exception;
        }
    }

    private function findIdempotentOrder(string $requestToken, string $actorRole, int $actorId): ?array
    {
        $order = $this->orders->where('request_token', $requestToken)->first();
        if (! $order) {
            return null;
        }
        $belongsToActor = $actorRole === 'customer'
            ? (int) ($order['customer_id'] ?? 0) === $actorId
            : (int) ($order['cashier_id'] ?? 0) === $actorId;
        return $belongsToActor ? $order : null;
    }

    public static function allowedTransitions(array $order, array $actor): array
    {
        $current = OrderStatus::tryFrom((string) ($order['status'] ?? ''));
        $candidates = $current?->transitions() ?? [];
        $role = (string) ($actor['role'] ?? '');
        $actorId = (int) ($actor['id'] ?? 0);
        $isDelivery = (string) ($order['order_type'] ?? '') === OrderType::Delivery->value;

        if ($role === 'admin') {
            return array_values(array_filter($candidates, static function (string $status) use ($isDelivery): bool {
                if ($isDelivery && in_array($status, [OrderStatus::OutForDelivery->value, OrderStatus::Completed->value], true)) {
                    return false;
                }
                if (! $isDelivery && $status === OrderStatus::OutForDelivery->value) {
                    return false;
                }
                return true;
            }));
        }

        if ($role === 'cashier') {
            return array_values(array_filter($candidates, static function (string $status) use ($order, $isDelivery): bool {
                if (in_array($status, [OrderStatus::Confirmed->value, OrderStatus::Preparing->value, OrderStatus::ReadyForPickup->value, OrderStatus::Cancelled->value], true)) {
                    return true;
                }
                return $status === OrderStatus::Completed->value
                    && ! $isDelivery
                    && (string) ($order['status'] ?? '') === OrderStatus::ReadyForPickup->value;
            }));
        }

        if ($role === 'rider' && $actorId > 0 && (int) ($order['rider_id'] ?? 0) === $actorId && $isDelivery) {
            if ($current === OrderStatus::ReadyForPickup && in_array(OrderStatus::OutForDelivery->value, $candidates, true)) {
                return [OrderStatus::OutForDelivery->value];
            }
            if ($current === OrderStatus::OutForDelivery && in_array(OrderStatus::Completed->value, $candidates, true)) {
                return [OrderStatus::Completed->value];
            }
        }
        return [];
    }

    public function updateStatus(int $orderId, string $nextStatus, array $actor, ?string $note = null): array
    {
        $role = (string) ($actor['role'] ?? '');
        if (! in_array($role, ['admin', 'cashier', 'rider'], true) || ! OrderStatus::tryFrom($nextStatus)) {
            throw new \DomainException('You are not allowed to update order statuses.');
        }

        $this->db->transBegin();
        try {
            $order = $this->db->query('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId])->getRowArray();
            if (! $order) {
                throw new \DomainException('Order not found.');
            }
            if ($role === 'rider' && (int) ($order['rider_id'] ?? 0) !== (int) ($actor['id'] ?? 0)) {
                throw new \DomainException('Riders may update only their assigned deliveries.');
            }
            if ($nextStatus === OrderStatus::OutForDelivery->value && ((string) $order['order_type'] !== OrderType::Delivery->value || empty($order['rider_id']))) {
                throw new \DomainException('A rider must be assigned before delivery starts.');
            }
            if (! in_array($nextStatus, self::allowedTransitions($order, $actor), true)) {
                throw new \DomainException('Invalid order status transition.');
            }

            $orderUpdates = ['status' => $nextStatus];
            if ($nextStatus === OrderStatus::Completed->value) {
                $orderUpdates['payment_status'] = 'paid';
            } elseif ($nextStatus === OrderStatus::Cancelled->value) {
                $orderUpdates['payment_status'] = 'failed';
            }
            if (! $this->orders->update($orderId, $orderUpdates)) {
                throw new \RuntimeException('Unable to update the order status.');
            }

            if ($nextStatus === OrderStatus::Completed->value) {
                if (! $this->db->table('payments')->where('order_id', $orderId)->update(['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')])) {
                    throw new \RuntimeException('Unable to update the payment status.');
                }
            } elseif ($nextStatus === OrderStatus::Cancelled->value) {
                $stockRows = $this->db->query('SELECT product_id, SUM(quantity) quantity FROM order_items WHERE order_id=? GROUP BY product_id', [$orderId])->getResultArray();
                foreach ($stockRows as $stockRow) {
                    $updatedProduct = $this->inventory->restockForCancelledOrder((int) $stockRow['product_id'], (int) $stockRow['quantity'], $orderId, (int) ($actor['id'] ?? 0));
                    $this->notifications->lowStock($updatedProduct);
                }
                if (! $this->db->table('payments')->where('order_id', $orderId)->update(['status' => 'failed', 'paid_at' => null])) {
                    throw new \RuntimeException('Unable to cancel the payment.');
                }
                if (! empty($order['promo_id'])) {
                    $usage = $this->db->table('promo_usages')->where(['promo_id' => (int) $order['promo_id'], 'order_id' => $orderId])->get()->getRowArray();
                    if ($usage) {
                        if (! $this->db->table('promo_usages')->where('id', (int) $usage['id'])->delete()) {
                            throw new \RuntimeException('Unable to restore promo usage.');
                        }
                        if (! $this->db->table('promos')->where('id', (int) $order['promo_id'])->where('used_count >', 0)->decrement('used_count')) {
                            throw new \RuntimeException('Unable to restore the promo usage count.');
                        }
                    }
                }
                $this->audit->record('order_cancelled', 'order', $orderId, ['from_status' => $order['status'], 'reason' => mb_substr(trim((string) $note), 0, 180)], (int) ($actor['id'] ?? 0));
            }

            if (! (new OrderStatusHistoryModel())->insert([
                'order_id' => $orderId,
                'user_id' => (int) ($actor['id'] ?? 0),
                'from_status' => $order['status'],
                'to_status' => $nextStatus,
                'note' => mb_substr(trim((string) $note), 0, 1000) ?: null,
            ])) {
                throw new \RuntimeException('Unable to save the order status history.');
            }

            $updatedOrder = $this->orders->find($orderId);
            if (! $updatedOrder) {
                throw new \RuntimeException('The updated order could not be loaded.');
            }
            $this->notifications->statusChanged($updatedOrder, $nextStatus, $note);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Unable to update the order status.');
            }
            $this->db->transCommit();
            return $updatedOrder;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function assignRider(int $orderId, int $riderId, array $actor): array
    {
        if (($actor['role'] ?? null) !== 'admin') {
            throw new \DomainException('Only administrators may assign riders.');
        }
        $this->db->transBegin();
        try {
            $order = $this->db->query('SELECT * FROM orders WHERE id=? FOR UPDATE', [$orderId])->getRowArray();
            if (! $order || $order['order_type'] !== OrderType::Delivery->value) {
                throw new \DomainException('Delivery order not found.');
            }
            if (in_array($order['status'], [OrderStatus::OutForDelivery->value, OrderStatus::Completed->value, OrderStatus::Cancelled->value], true)) {
                throw new \DomainException('The rider cannot be changed at this order stage.');
            }
            $rider = (new UserModel())->where(['id' => $riderId, 'role' => 'rider', 'status' => 'active'])->first();
            if (! $rider) {
                throw new \DomainException('Select an active rider.');
            }
            $oldRiderId = ! empty($order['rider_id']) ? (int) $order['rider_id'] : null;
            if (! $this->orders->update($orderId, ['rider_id' => $riderId])) {
                throw new \RuntimeException('Unable to assign the rider.');
            }
            $updatedOrder = $this->orders->find($orderId);
            if (! $updatedOrder) {
                throw new \RuntimeException('The updated order could not be loaded.');
            }
            $this->notifications->riderAssigned($updatedOrder, $oldRiderId, $riderId);
            $this->audit->record('rider_assignment', 'order', $orderId, ['old_rider_id' => $oldRiderId, 'new_rider_id' => $riderId], (int) ($actor['id'] ?? 0));
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Unable to assign the rider.');
            }
            $this->db->transCommit();
            return $updatedOrder;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }
}
