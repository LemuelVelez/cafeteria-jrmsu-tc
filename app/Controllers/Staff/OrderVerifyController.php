<?php

namespace App\Controllers\Staff;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Services\OrderQrService;
use CodeIgniter\Exceptions\PageNotFoundException;

class OrderVerifyController extends BaseController
{
    public function show(string $token): string
    {
        $order = (new OrderQrService())->verify($token);
        if (! $order) {
            throw PageNotFoundException::forPageNotFound('Invalid or tampered order verification code.');
        }
        return $this->render('staff/orders/verify', [
            'title' => 'Verify order',
            'order' => $order,
            'items' => (new OrderItemModel())->where('order_id', $order['id'])->findAll(),
        ]);
    }
}
