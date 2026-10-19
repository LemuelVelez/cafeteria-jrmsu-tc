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
        $qr = new OrderQrService();
        $order = str_starts_with($token, 'd2.') ? $qr->verifyDeliveryToken($token) : $qr->verify($token);
        $actor = session()->get('user');
        if (! $order || ! in_array($actor['role'] ?? '', ['admin','cashier','rider'], true)) {
            throw PageNotFoundException::forPageNotFound('Invalid or tampered order verification code.');
        }
        if (($actor['role'] ?? '') === 'rider'
            && ($order['order_type'] !== 'delivery'
                || (int) ($order['rider_id'] ?? 0) !== (int) ($actor['id'] ?? 0)
                || ! in_array($order['status'], ['ready_for_pickup','out_for_delivery'], true))) {
            throw PageNotFoundException::forPageNotFound();
        }
        // The verification screen uses preparation and handoff details, not contact PII.
        unset($order['delivery_address']);
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
