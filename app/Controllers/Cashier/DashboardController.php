<?php

namespace App\Controllers\Cashier;

use App\Controllers\BaseController;
use App\Models\OrderModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $orders = new OrderModel();
        $userId = (int) (session()->get('user')['id'] ?? null);
        $summary = $orders->todaySummary($userId);
        return $this->render('cashier/dashboard', [
            'summary' => $summary,
            'recentOrders' => array_slice($orders->detailed(['orders.cashier_id' => $userId]), 0, 8),
            'pendingCount' => $orders->whereIn('status', ['pending', 'confirmed', 'preparing'])->countAllResults(),
        ]);
    }
}
