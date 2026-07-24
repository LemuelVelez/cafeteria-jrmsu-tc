<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;

class ReportController extends BaseController
{
    public function index(): string
    {
        $from = (string) ($this->request->getGet('from') ?: date('Y-m-01'));
        $to = (string) ($this->request->getGet('to') ?: date('Y-m-d'));
        $orders = (new OrderModel())->where('DATE(created_at) >=', $from)->where('DATE(created_at) <=', $to)->where('status !=', 'cancelled')->findAll();
        $totals = ['orders' => count($orders), 'revenue' => array_sum(array_column($orders, 'total')), 'discounts' => array_sum(array_column($orders, 'discount'))];
        $topProducts = (new OrderItemModel())->select('product_name, SUM(quantity) quantity, SUM(line_total) revenue')->join('orders', 'orders.id = order_items.order_id')->where('orders.status !=', 'cancelled')->where('DATE(orders.created_at) >=', $from)->where('DATE(orders.created_at) <=', $to)->groupBy('product_name')->orderBy('quantity', 'DESC')->findAll(10);
        return $this->render('admin/reports/index', compact('from', 'to', 'orders', 'totals', 'topProducts'));
    }
}
