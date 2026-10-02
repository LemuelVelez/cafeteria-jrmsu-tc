<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Services\ReportService;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $orders = new OrderModel();
        $today = (new ReportService())->todaySummary();
        $stats = [
            'orders' => array_sum(array_map(static fn(array $row): int => (int)$row['count'], $today['statuses'])),
            'revenue' => (float)($today['sales']['net_sales'] ?? 0),
            'customers' => (new UserModel())->where('role', 'customer')->countAllResults(),
            'products' => (new ProductModel())->where('is_available', 1)->countAllResults(),
            'lowStock' => (int)$today['lowStockCount'],
        ];
        $recentOrders = array_slice($orders->detailed(), 0, 8);
        $dailyRevenue = $orders->select('DATE(created_at) day, COALESCE(SUM(total),0) revenue')->where('payment_status', 'paid')->where('created_at >=', date('Y-m-d 00:00:00', strtotime('-6 days')))->groupBy('DATE(created_at)')->orderBy('day')->findAll();
        return $this->render('admin/dashboard', compact('stats', 'recentOrders', 'dailyRevenue') + ['todayStatuses' => $today['statuses']]);
    }
}
