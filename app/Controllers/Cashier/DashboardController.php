<?php

namespace App\Controllers\Cashier;

use App\Controllers\BaseController;
use App\Models\OrderModel;
use App\Services\ReportService;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $orders = new OrderModel();
        $userId = (int)(session()->get('user')['id'] ?? 0);
        $summary = $orders->todaySummary($userId);
        $today = (new ReportService())->todaySummary();
        return $this->render('cashier/dashboard', [
            'summary' => $summary,
            'recentOrders' => array_slice($orders->detailed(['orders.cashier_id' => $userId]), 0, 8),
            'pendingCount' => $orders->whereIn('status', ['pending', 'confirmed', 'preparing'])->countAllResults(),
            'lowStockCount' => (int)$today['lowStockCount'],
            'todayStatuses' => $today['statuses'],
        ]);
    }
}
