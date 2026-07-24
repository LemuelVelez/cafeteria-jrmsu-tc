<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;

class OrderController extends BaseController
{
    public function index(): string
    {
        return $this->render('customer/orders/index', ['orders' => (new OrderModel())->where('customer_id', (session()->get('user')['id'] ?? null))->orderBy('created_at', 'DESC')->findAll()]);
    }

    public function show(int $id): string
    {
        $order = (new OrderModel())->where(['id' => $id, 'customer_id' => (session()->get('user')['id'] ?? null)])->first();
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return $this->render('customer/orders/show', [
            'order' => $order,
            'items' => (new OrderItemModel())->where('order_id', $id)->findAll(),
            'history' => (new OrderStatusHistoryModel())->where('order_id', $id)->orderBy('created_at')->findAll(),
        ]);
    }
}
