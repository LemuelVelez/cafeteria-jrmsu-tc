<?php

namespace App\Services;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
use App\Models\PaymentModel;
use App\Models\ProductAddonModel;
use App\Models\ProductModel;
use App\Models\PromoModel;
use App\Models\PromoUsageModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use Throwable;

class OrderService
{
    private BaseConnection $db;

    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['out_for_delivery', 'delivered', 'cancelled'],
        'out_for_delivery' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly OrderModel $orders = new OrderModel(),
        private readonly OrderItemModel $items = new OrderItemModel(),
        private readonly ProductModel $products = new ProductModel(),
        private readonly ProductAddonModel $addons = new ProductAddonModel(),
        private readonly PromoService $promoService = new PromoService(),
        private readonly SettingModel $settings = new SettingModel(),
    ) {
        $this->db = db_connect();
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
        $submittedPaymentMethod = (string) ($payload['payment_method'] ?? $paymentMethod->value);
        if ($submittedPaymentMethod !== $paymentMethod->value) {
            throw new \DomainException('Pickup orders require Cash on Pickup and delivery orders require Cash on Delivery.');
        }
        $deliveryAddress = trim((string) ($payload['delivery_address'] ?? ''));
        if ($orderType === OrderType::Delivery && mb_strlen($deliveryAddress) < 5) {
            throw new \DomainException('A delivery address is required.');
        }

        $customerId = $actorRole === 'customer' ? $actorId : (int) ($payload['customer_id'] ?? 0);
        if ($actorRole === 'cashier' && $customerId > 0) {
            $customer = (new UserModel())->where(['id' => $customerId, 'role' => 'customer', 'status' => 'active'])->first();
            if (! $customer) {
                throw new \DomainException('Selected customer is not active.');
            }
        }

        $this->db->transBegin();
        try {
            $subtotal = 0.0;
            $normalized = [];

            foreach ($cart as $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $quantity = max(1, min(99, (int) ($line['quantity'] ?? 1)));
                $product = $this->db->query('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$productId])->getRowArray();
                if (! $product || ! (bool) $product['is_available'] || (int) $product['stock'] < $quantity) {
                    throw new \DomainException('One or more products are unavailable or out of stock.');
                }

                $selectedAddonIds = [];
                foreach (($line['addons'] ?? []) as $selectedAddon) {
                    $selectedAddonIds[] = (int) (is_array($selectedAddon) ? ($selectedAddon['id'] ?? 0) : $selectedAddon);
                }
                $selectedAddonIds = array_values(array_unique(array_filter($selectedAddonIds)));
                $actualAddons = [];
                if ($selectedAddonIds !== []) {
                    $actualAddons = $this->addons
                        ->where('product_id', $productId)
                        ->where('is_active', 1)
                        ->whereIn('id', $selectedAddonIds)
                        ->findAll();
                    if (count($actualAddons) !== count($selectedAddonIds)) {
                        throw new \DomainException('One or more selected add-ons are invalid.');
                    }
                }

                $addonTotal = array_sum(array_map(static fn (array $addon): float => (float) $addon['price'], $actualAddons));
                $lineTotal = ((float) $product['price'] + $addonTotal) * $quantity;
                $subtotal += $lineTotal;
                $normalized[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'addon_total' => $addonTotal,
                    'addons_json' => json_encode(array_map(static fn (array $addon): array => [
                        'id' => (int) $addon['id'],
                        'name' => $addon['name'],
                        'price' => (float) $addon['price'],
                    ], $actualAddons), JSON_THROW_ON_ERROR),
                    'notes' => mb_substr(trim((string) ($line['notes'] ?? '')), 0, 500),
                    'line_total' => round($lineTotal, 2),
                ];
            }

            $promoId = null;
            $discount = 0.0;
            if (! empty($payload['promo_code'])) {
                $promoResult = $this->promoService->calculate((string) $payload['promo_code'], $subtotal);
                $promoId = (int) $promoResult['promo']['id'];
                $discount = (float) $promoResult['discount'];
            }

            $deliveryFee = $orderType === OrderType::Delivery
                ? (float) $this->settings->getValue('delivery_fee', env('CAFETERIA_DELIVERY_FEE', 40))
                : 0.0;
            $total = round(max(0, $subtotal - $discount + $deliveryFee), 2);
            $initialStatus = $actorRole === 'cashier' ? 'confirmed' : 'pending';
            $orderId = $this->orders->insert([
                'order_number' => generate_order_number(),
                'customer_id' => $customerId ?: null,
                'cashier_id' => $actorRole === 'cashier' ? $actorId : null,
                'order_type' => $orderType->value,
                'status' => $initialStatus,
                'subtotal' => round($subtotal, 2),
                'discount' => $discount,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'payment_method' => $paymentMethod->value,
                'payment_status' => 'pending',
                'delivery_address' => $orderType === OrderType::Delivery ? $deliveryAddress : null,
                'notes' => mb_substr(trim((string) ($payload['notes'] ?? '')), 0, 1000),
                'promo_id' => $promoId,
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
                if (! $this->db->table('products')->where('id', $product['id'])->decrement('stock', $line['quantity'])) {
                    throw new \RuntimeException('Unable to update product stock.');
                }
            }

            if (! (new PaymentModel())->insert([
                'order_id' => $orderId,
                'method' => $paymentMethod->value,
                'amount' => $total,
                'status' => 'pending',
            ])) {
                throw new \RuntimeException('Unable to save the order payment.');
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
                if (! (new PromoUsageModel())->insert([
                    'promo_id' => $promoId,
                    'order_id' => $orderId,
                    'user_id' => $customerId ?: $actorId,
                ])) {
                    throw new \RuntimeException('Unable to record promo usage.');
                }
                if (! $this->db->table('promos')->where('id', $promoId)->increment('used_count')) {
                    throw new \RuntimeException('Unable to update promo usage.');
                }
            }

            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Unable to create the order.');
            }
            $order = $this->orders->find($orderId);
            if (! $order) {
                throw new \RuntimeException('The order was created but could not be loaded.');
            }
            $this->db->transCommit();

            return $order;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function updateStatus(int $orderId, string $nextStatus, array $actor, ?string $note = null): array
    {
        $order = $this->orders->find($orderId);
        if (! $order) {
            throw new \DomainException('Order not found.');
        }
        if (! in_array($nextStatus, self::TRANSITIONS[$order['status']] ?? [], true)) {
            throw new \DomainException('Invalid order status transition.');
        }
        $role = (string) ($actor['role'] ?? '');
        if ($role === 'rider' && ((int) $order['rider_id'] !== (int) ($actor['id'] ?? 0) || ! in_array($nextStatus, ['out_for_delivery', 'delivered'], true))) {
            throw new \DomainException('Riders may update only their assigned deliveries.');
        }
        if ($role === 'cashier') {
            $cashierStatuses = ['confirmed', 'preparing', 'ready', 'cancelled'];
            $mayCompleteCounterOrder = $nextStatus === 'delivered' && $order['status'] === 'ready' && $order['order_type'] !== 'delivery';
            if (! in_array($nextStatus, $cashierStatuses, true) && ! $mayCompleteCounterOrder) {
                throw new \DomainException('Cashiers may update preparation and counter-order statuses only.');
            }
        }
        if (! in_array($role, ['admin', 'cashier', 'rider'], true)) {
            throw new \DomainException('You are not allowed to update order statuses.');
        }
        if ($nextStatus === 'out_for_delivery' && ($order['order_type'] !== 'delivery' || ! $order['rider_id'])) {
            throw new \DomainException('A rider must be assigned before delivery starts.');
        }

        $this->db->transBegin();
        try {
            $orderUpdates = ['status' => $nextStatus];
            if ($nextStatus === 'delivered') {
                $orderUpdates['payment_status'] = 'paid';
            }
            if (! $this->orders->update($orderId, $orderUpdates)) {
                throw new \RuntimeException('Unable to update the order status.');
            }
            if ($nextStatus === 'delivered' && ! $this->db->table('payments')
                ->where('order_id', $orderId)
                ->update(['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')])) {
                throw new \RuntimeException('Unable to update the payment status.');
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
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Unable to update the order status.');
            }

            $updatedOrder = $this->orders->find($orderId);
            if (! $updatedOrder) {
                throw new \RuntimeException('The updated order could not be loaded.');
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

        $order = $this->orders->find($orderId);
        if (! $order || $order['order_type'] !== 'delivery') {
            throw new \DomainException('Delivery order not found.');
        }
        if (in_array($order['status'], ['out_for_delivery', 'delivered', 'cancelled'], true)) {
            throw new \DomainException('The rider cannot be changed at this order stage.');
        }

        $rider = (new UserModel())->where(['id' => $riderId, 'role' => 'rider', 'status' => 'active'])->first();
        if (! $rider) {
            throw new \DomainException('Select an active rider.');
        }
        if (! $this->orders->update($orderId, ['rider_id' => $riderId])) {
            throw new \RuntimeException('Unable to assign the rider.');
        }

        $updatedOrder = $this->orders->find($orderId);
        if (! $updatedOrder) {
            throw new \RuntimeException('The updated order could not be loaded.');
        }

        return $updatedOrder;
    }
}
