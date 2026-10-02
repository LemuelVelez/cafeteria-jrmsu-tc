<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\ProductAddonModel;
use App\Models\ProductModel;
use App\Services\OrderService;
use DomainException;
use Throwable;

class OrderApiController extends BaseController
{
    public function create()
    {
        try {
            $payload = $this->request->getJSON(true) ?: $this->request->getPost();
            $actor = session()->get('user');
            if (! is_array($actor) || empty($actor['id']) || empty($actor['role'])) {
                return $this->jsonError('Your session has expired. Sign in again before placing the order.', null, 401);
            }

            $order = (new OrderService())->create($payload, $actor);

            $redirectPath = $actor['role'] === 'cashier'
                ? 'cashier/orders'
                : 'customer/orders/' . $order['id'];

            return $this->jsonSuccess('Order created successfully.', [
                'order_number' => $order['order_number'],
                'order_id' => (int) $order['id'],
                'redirect_url' => base_url($redirectPath),
            ], 201);
        } catch (DomainException $exception) {
            return $this->jsonError($exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Order checkout failed: {message}', ['message' => $exception->getMessage()]);

            return $this->jsonError('The order could not be placed right now. Please try again.', null, 500);
        }
    }

    public function show(int $id)
    {
        $order = (new OrderModel())->find($id);
        if (! $order || ! $this->canView($order)) {
            return $this->jsonError('Order not found.', null, 404);
        }
        return $this->jsonSuccess('Order loaded.', ['order' => $order, 'items' => (new OrderItemModel())->where('order_id', $id)->findAll()]);
    }


    public function reorder(int $id)
    {
        $user = session()->get('user');
        $order = (new OrderModel())->where(['id' => $id, 'customer_id' => (int) ($user['id'] ?? 0)])->first();
        if (! $order || ($user['role'] ?? '') !== 'customer') {
            return $this->jsonError('Order not found.', null, 404);
        }
        $items = (new OrderItemModel())->where('order_id', $id)->findAll();
        $ready = [];
        $skipped = [];
        foreach ($items as $item) {
            $product = (new ProductModel())->find((int) $item['product_id']);
            if (! $product || ! (bool) $product['is_available']) {
                $skipped[] = $item['product_name'] . ': unavailable';
                continue;
            }
            if ((int) $product['stock'] < 1) {
                $skipped[] = $item['product_name'] . ': out of stock';
                continue;
            }
            $selected = json_decode((string) ($item['addons_json'] ?? '[]'), true) ?: [];
            $selectedIds = array_map(static fn (array $addon): int => (int) ($addon['id'] ?? 0), $selected);
            $addons = $selectedIds ? (new ProductAddonModel())->where('product_id', $product['id'])->where('is_active', 1)->whereIn('id', $selectedIds)->findAll() : [];
            if (count($addons) !== count(array_filter($selectedIds))) {
                $skipped[] = $item['product_name'] . ': one or more add-ons are no longer available';
                continue;
            }
            $ready[] = [
                'product_id' => (int) $product['id'],
                'name' => $product['name'],
                'price' => (float) $product['price'],
                'stock' => (int) $product['stock'],
                'image' => ! empty($product['image']) ? media_url((string) $product['image']) : '',
                'quantity' => min((int) $item['quantity'], (int) $product['stock']),
                'addons' => array_map(static fn (array $addon): array => ['id'=>(int)$addon['id'],'name'=>$addon['name'],'price'=>(float)$addon['price']], $addons),
                'notes' => (string) ($item['notes'] ?? ''),
            ];
        }
        return $this->jsonSuccess('Reorder items checked against current menu and stock.', ['items' => $ready, 'skipped' => $skipped]);
    }

    public function pendingCount()
    {
        $count = (new OrderModel())->whereIn('status', ['pending', 'confirmed', 'preparing'])->countAllResults();
        return $this->jsonSuccess('Pending order count loaded.', ['count' => $count]);
    }

    public function status(int $id)
    {
        try {
            $payload = $this->request->getJSON(true) ?: $this->request->getRawInput();
            $order = (new OrderService())->updateStatus($id, (string) ($payload['status'] ?? ''), session()->get('user'), $payload['note'] ?? null);
            return $this->jsonSuccess('Order status updated.', $order);
        } catch (DomainException $exception) {
            return $this->jsonError($exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Order status update failed: {message}', ['message' => $exception->getMessage()]);
            return $this->jsonError('The order status could not be updated right now.', null, 500);
        }
    }

    public function assignRider(int $id)
    {
        try {
            $payload = $this->request->getJSON(true) ?: $this->request->getRawInput();
            $order = (new OrderService())->assignRider(
                $id,
                (int) ($payload['rider_id'] ?? 0),
                session()->get('user'),
            );

            return $this->jsonSuccess('Rider assigned.', $order);
        } catch (DomainException $exception) {
            return $this->jsonError($exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Rider assignment failed: {message}', ['message' => $exception->getMessage()]);
            return $this->jsonError('The rider could not be assigned right now.', null, 500);
        }
    }

    private function canView(array $order): bool
    {
        $user = session()->get('user');
        if (! is_array($user) || empty($user['role']) || empty($user['id'])) {
            return false;
        }

        return in_array($user['role'], ['admin', 'cashier'], true)
            || ($user['role'] === 'customer' && (int) $order['customer_id'] === (int) $user['id'])
            || ($user['role'] === 'rider' && (int) $order['rider_id'] === (int) $user['id']);
    }
}
