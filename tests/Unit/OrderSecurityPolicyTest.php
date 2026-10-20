<?php

namespace Tests\Unit;

use App\Services\OrderApiPresenter;
use App\Services\StockAlertPolicy;
use PHPUnit\Framework\TestCase;

final class OrderSecurityPolicyTest extends TestCase
{
    public function testPublicOrderApiNeverExposesSigningSecretsOrVerificationHashes(): void
    {
        $order = [
            'id'=>17,'order_number'=>'ORD-17','order_type'=>'delivery',
            'status'=>'out_for_delivery','rider_id'=>4,'customer_id'=>6,
            'request_token'=>'SECRET-MUST-NOT-LEAK','pickup_code_hash'=>'HASH1',
            'delivery_code_hash'=>'HASH2','handoff_verified_by'=>99,
            'delivery_address'=>'Shipping Street','notes'=>'Gate code 1111',
            'total'=>'190.00',
        ];
        foreach ([['role'=>'admin','id'=>1],['role'=>'cashier','id'=>3],
            ['role'=>'rider','id'=>4], ['role'=>'customer','id'=>6]] as $actor) {
            $result = OrderApiPresenter::present($order, $actor);
            foreach (['request_token','pickup_code_hash','delivery_code_hash',
                'handoff_verified_by','customer_id'] as $private) {
                self::assertArrayNotHasKey($private, $result);
            }
            self::assertSame('ORD-17', $result['order_number']);
        }
    }

    public function testRiderLosesContactAndNotesAfterTerminalStatus(): void
    {
        $row=['id'=>1,'rider_id'=>5,'status'=>'out_for_delivery',
            'delivery_address'=>'Private house','notes'=>'Apartment door'];
        $actor=['role'=>'rider','id'=>5];
        self::assertSame('Private house', OrderApiPresenter::present($row,$actor)['delivery_address']);
        $row['status']='delivered';
        $filtered=OrderApiPresenter::present($row,$actor);
        self::assertArrayNotHasKey('delivery_address',$filtered);
        self::assertArrayNotHasKey('notes',$filtered);
        self::assertArrayNotHasKey('notes',OrderApiPresenter::items([['notes'=>'Secret']],$row,$actor)[0]);
        self::assertArrayNotHasKey('delivery_address',OrderApiPresenter::present($row,['role'=>'rider','id'=>6]));
    }

    public function testLevelAlertsRearmWhenRestocked(): void
    {
        self::assertNull(StockAlertPolicy::transition(null, 5, 5, 100));
        self::assertSame('stock_half', StockAlertPolicy::transition(80, 50, 5, 100));
        self::assertNull(StockAlertPolicy::transition(50, 49, 5, 100));
        self::assertSame('low_stock', StockAlertPolicy::transition(49, 25, 5, 100));
        self::assertNull(StockAlertPolicy::transition(25, 20, 5, 100));
        self::assertSame('out_of_stock', StockAlertPolicy::transition(20, 0, 5, 100));
        self::assertNull(StockAlertPolicy::transition(0, 0, 5, 100));
        self::assertNull(StockAlertPolicy::transition(5, 80, 5, 100));
        self::assertSame('stock_half', StockAlertPolicy::transition(80, 50, 5, 100));
        self::assertNull(StockAlertPolicy::transition(80, 50, 5, 100, 50, 25, false));
        self::assertSame('low_stock', StockAlertPolicy::transition(50, 25, 5, 100, 50, 25, false));
    }
}
