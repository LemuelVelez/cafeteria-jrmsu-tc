<?php

namespace Tests\Database;

use App\Services\OrderQrService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

/** Database-backed delivery QR proof: never substitute a kitchen QR for receipt. */
#[Group('database')]
final class DeliveryQrSeparationDatabaseTest extends DatabaseTestCase
{
    public function testDeliveryQrCannotBeReplacedWithKitchenQrAndExpiresAtDelivery(): void
    {
        $customer = $this->makeCustomer();
        $db = db_connect('tests');
        $productId = (int) $db->table('products')->where('slug','crispy-fries')->get()->getRow('id');
        $order = $this->placeOrder([
            'items'=>[['product_id'=>$productId,'quantity'=>1]],
            'order_type'=>'delivery','delivery_address'=>'Fixture delivery address, 123 Test Street',
            'request_token'=>bin2hex(random_bytes(18)),
        ], ['id'=>$customer,'role'=>'customer']);
        $qr = new OrderQrService();
        $kitchen = $qr->token($order);
        $delivery = $qr->deliveryToken($order);
        self::assertNull($qr->verifyDeliveryToken($kitchen));
        self::assertNotNull($qr->verify($kitchen));
        self::assertNull($qr->verifyDeliveryToken($delivery)); // Not yet dispatched.
        $db->table('orders')->where('id',$order['id'])->update(['status'=>'out_for_delivery']);
        self::assertSame((int)$order['id'], (int)$qr->verifyDeliveryToken($delivery)['id']);
        self::assertNull($qr->verifyDeliveryToken(substr($delivery,0,-1).($delivery[-1]==='0'?'1':'0')));
        $db->table('orders')->where('id',$order['id'])->update(['status'=>'delivered']);
        self::assertNull($qr->verifyDeliveryToken($delivery));
    }
}
