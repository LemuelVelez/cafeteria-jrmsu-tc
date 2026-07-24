<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Models\OrderModel;
use App\Models\ProductModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $userId = (int) (session()->get('user')['id'] ?? null);
        $orders = (new OrderModel())->where('customer_id', $userId)->orderBy('created_at', 'DESC')->findAll();
        return $this->render('customer/dashboard', [
            'orders' => array_slice($orders, 0, 5),
            'activeOrders' => count(array_filter($orders, fn ($order) => ! in_array($order['status'], ['delivered', 'cancelled'], true))),
            'featuredProducts' => (new ProductModel())->where(['is_available' => 1, 'is_featured' => 1])->findAll(4),
        ]);
    }
}
