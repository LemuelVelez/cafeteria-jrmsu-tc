<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Enums\OrderStatus;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
use App\Services\OrderQrService;
use CodeIgniter\Exceptions\PageNotFoundException;

class OrderController extends BaseController
{
    public function index(): string
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $status = trim((string) $this->request->getGet('status'));
        $type = trim((string) $this->request->getGet('type'));
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));
        $q = trim((string) $this->request->getGet('q'));

        $model = new OrderModel();
        $model->select('orders.*, (SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_items.order_id=orders.id) AS item_count')
            ->where('customer_id', $userId);
        if ($status !== '' && in_array($status, OrderStatus::values(), true)) {
            $model->where('status', $status);
        }
        if (in_array($type, ['pickup', 'delivery'], true)) {
            $model->where('order_type', $type);
        }
        if ($from !== '' && $this->isDate($from)) {
            $model->where('DATE(created_at) >=', $from);
        }
        if ($to !== '' && $this->isDate($to)) {
            $model->where('DATE(created_at) <=', $to);
        }
        if ($q !== '') {
            $model->like('order_number', $q);
        }
        $orders = $model->orderBy('created_at', 'DESC')->paginate(10);

        $db = db_connect();
        $summary = $db->query(
            "SELECT COUNT(*) total_orders, SUM(status='completed') completed_orders, COALESCE(SUM(CASE WHEN status='completed' AND payment_status='paid' THEN total ELSE 0 END),0) total_spent, SUM(status NOT IN ('completed','cancelled')) active_orders FROM orders WHERE customer_id=?",
            [$userId],
        )->getRowArray() ?? [];

        return $this->render('customer/orders/index', compact('orders', 'summary', 'status', 'type', 'from', 'to', 'q') + ['pager' => $model->pager]);
    }

    public function show(int $id): string
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $orders = (new OrderModel())->detailed(['orders.id' => $id, 'orders.customer_id' => $userId]);
        $order = $orders[0] ?? null;
        if (! $order) {
            throw PageNotFoundException::forPageNotFound();
        }
        $token = (new OrderQrService())->token($order);
        return $this->render('customer/orders/show', [
            'order' => $order,
            'items' => (new OrderItemModel())->where('order_id', $id)->findAll(),
            'history' => (new OrderStatusHistoryModel())->where('order_id', $id)->orderBy('created_at')->findAll(),
            'verificationUrl' => base_url('staff/orders/verify/' . rawurlencode($token)),
        ]);
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
