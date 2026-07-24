<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\UserModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $orders = new OrderModel();
        $summary = $orders->todaySummary();
        $stats = [
            'orders' => (int) $summary['order_count'],
            'revenue' => (float) $summary['revenue'],
            'customers' => (new UserModel())->where('role', 'customer')->countAllResults(),
            'products' => (new ProductModel())->where('is_available', 1)->countAllResults(),
        ];
        $recentOrders = array_slice($orders->detailed(), 0, 8);
        $dailyRevenue = $orders->select('DATE(created_at) day, COALESCE(SUM(total),0) revenue')->where('status !=', 'cancelled')->where('created_at >=', date('Y-m-d 00:00:00', strtotime('-6 days')))->groupBy('DATE(created_at)')->orderBy('day')->findAll();
        return $this->render('admin/dashboard', compact('stats', 'recentOrders', 'dailyRevenue'));
    }
}
