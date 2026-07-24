<?php

namespace App\Controllers\Rider;

use App\Controllers\BaseController;
use App\Models\OrderModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $riderId = (int) (session()->get('user')['id'] ?? null);
        $orders = (new OrderModel())->detailed(['orders.rider_id' => $riderId]);
        return $this->render('rider/dashboard', [
            'active' => array_filter($orders, fn ($order) => ! in_array($order['status'], ['delivered', 'cancelled'], true)),
            'completedToday' => count(array_filter($orders, fn ($order) => $order['status'] === 'delivered' && str_starts_with($order['updated_at'], date('Y-m-d')))),
        ]);
    }
}
