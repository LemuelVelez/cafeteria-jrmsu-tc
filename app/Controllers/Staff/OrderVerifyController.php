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
        $order['delivery_address']=\App\Services\FieldEncryption::open($order['delivery_address']??null);
        return $this->render('staff/orders/verify', [
            'title' => 'Verify order',
            'order' => $order,
            'token' => $token,
            'items' => (new OrderItemModel())->where('order_id', $order['id'])->findAll(),
            'handoffs' => db_connect()->table('order_handoffs h')->select('h.*,u.name verifier_name')
                ->join('users u','u.id=h.verified_by','left')->where('order_id',$order['id'])->get()->getResultArray(),
        ]);
    }
}
