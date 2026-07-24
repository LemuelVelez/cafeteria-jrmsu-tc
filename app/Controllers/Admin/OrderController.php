<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\UserModel;
use App\Services\OrderService;
use CodeIgniter\Exceptions\PageNotFoundException;

class OrderController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/orders/index', [
            'orders' => (new OrderModel())->detailed(),
            'riders' => (new UserModel())->activeRiders(),
        ]);
    }

    public function show(int $id): string
    {
        $orders = (new OrderModel())->detailed(['orders.id' => $id]);
        $order = $orders[0] ?? null;
        if (! $order) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->render('admin/orders/show', [
            'order' => $order,
            'items' => (new OrderItemModel())->where('order_id', $id)->findAll(),
            'riders' => (new UserModel())->activeRiders(),
        ]);
    }

    public function status(int $id)
    {
        try {
            (new OrderService())->updateStatus(
                $id,
                (string) $this->request->getPost('status'),
                session()->get('user'),
                (string) $this->request->getPost('note'),
            );

            return redirect()->to('/admin/orders/' . $id)->with('success', 'Order status updated.');
        } catch (\Throwable $e) {
            return redirect()->to('/admin/orders/' . $id)->with('error', $e->getMessage());
        }
    }

    public function assignRider(int $id)
    {
        try {
            (new OrderService())->assignRider(
                $id,
                (int) $this->request->getPost('rider_id'),
                session()->get('user'),
            );

            return redirect()->to('/admin/orders/' . $id)->with('success', 'Rider assigned.');
        } catch (\Throwable $e) {
            return redirect()->to('/admin/orders/' . $id)->with('error', $e->getMessage());
        }
    }
}
