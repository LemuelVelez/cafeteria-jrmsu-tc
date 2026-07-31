<?php

namespace App\Controllers\Cashier;

use App\Controllers\BaseController;
use App\Models\OrderModel;
use App\Services\OrderService;

class OrderController extends BaseController
{
    public function index(): string
    {
        $actor = (array) session()->get('user');
        $orders = (new OrderModel())->detailed();
        foreach ($orders as &$order) {
            $order['status_options'] = OrderService::allowedTransitions($order, $actor);
        }
        unset($order);

        return $this->render('cashier/orders/index', ['orders' => $orders]);
    }

    public function status(int $id)
    {
        try {
            (new OrderService())->updateStatus($id, (string) $this->request->getPost('status'), session()->get('user'));
            return redirect()->to('/cashier/orders')->with('success', 'Order status updated.');
        } catch (\Throwable $e) {
            return redirect()->to('/cashier/orders')->with('error', $e->getMessage());
        }
    }
}
