<?php

namespace App\Controllers\Rider;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Services\OrderService;
use CodeIgniter\Exceptions\PageNotFoundException;

class DeliveryController extends BaseController
{
    public function index(): string
    {
        return $this->render('rider/deliveries/index', [
            'orders' => (new OrderModel())->detailed([
                'orders.rider_id' => session()->get('user')['id'] ?? null,
            ]),
        ]);
    }

    public function show(int $id): string
    {
        $orders = (new OrderModel())->detailed([
            'orders.id' => $id,
            'orders.rider_id' => session()->get('user')['id'] ?? null,
        ]);
        $order = $orders[0] ?? null;
        if (! $order) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->render('rider/deliveries/show', [
            'order' => $order,
            'items' => (new OrderItemModel())->where('order_id', $id)->findAll(),
        ]);
    }

    public function status(int $id)
    {
        try {
            (new OrderService())->updateStatus(
                $id,
                (string) $this->request->getPost('status'),
                session()->get('user'),
            );

            return redirect()->to('/rider/deliveries/' . $id)->with('success', 'Delivery status updated.');
        } catch (\Throwable $e) {
            return redirect()->to('/rider/deliveries/' . $id)->with('error', $e->getMessage());
        }
    }
}
