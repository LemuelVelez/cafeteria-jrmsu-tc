<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
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

            return $this->jsonSuccess('Order created successfully.', [
                'order_number' => $order['order_number'],
                'order_id' => (int) $order['id'],
                'redirect_url' => base_url('customer/orders/' . $order['id']),
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
        } catch (Throwable $exception) {
            return $this->jsonError($exception->getMessage());
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
        } catch (Throwable $exception) {
            return $this->jsonError($exception->getMessage());
        }
    }

    private function canView(array $order): bool
    {
        $user = session()->get('user');
        return in_array($user['role'], ['admin', 'cashier'], true)
            || ($user['role'] === 'customer' && (int) $order['customer_id'] === (int) $user['id'])
            || ($user['role'] === 'rider' && (int) $order['rider_id'] === (int) $user['id']);
    }
}
