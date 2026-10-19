<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Services\OrderService;
use PHPUnit\Framework\TestCase;

final class OrderTransitionMatrixTest extends TestCase
{
    public function testPickupAndDeliveryTransitionsAreScopedByRole(): void
    {
        $expected = [
            'pickup' => [
                'admin'=>['ready_for_pickup'=>['completed','cancelled']],
                'cashier'=>['ready_for_pickup'=>['completed','cancelled']],
                'rider'=>['ready_for_pickup'=>[]],
            ],
            'delivery' => [
                'admin'=>['ready_for_pickup'=>['cancelled']],
                'cashier'=>['ready_for_pickup'=>['cancelled']],
                'rider'=>['ready_for_pickup'=>['out_for_delivery'], 'out_for_delivery'=>['delivered']],
            ],
        ];
        foreach ($expected as $orderType=>$roles) {
            foreach ($roles as $role=>$statuses) {
                foreach ($statuses as $current=>$transitions) {
                    $order=['order_type'=>$orderType,'status'=>$current,'rider_id'=>100];
                    $actor=['role'=>$role,'id'=>$role==='rider'?100:200];
                    self::assertSame($transitions,OrderService::allowedTransitions($order,$actor),
                        $orderType.'/'.$role.'/'.$current);
                }
            }
        }
        self::assertSame([],OrderService::allowedTransitions(
            ['order_type'=>'delivery','status'=>'ready_for_pickup','rider_id'=>100],
            ['role'=>'rider','id'=>101]));
    }

    public function testEveryTerminalOrderHasNoOutgoingTransitions(): void
    {
        foreach (['completed','delivered','cancelled'] as $status) {
            foreach (['admin','cashier','rider'] as $role) {
                self::assertSame([],OrderService::allowedTransitions(
                    ['order_type'=>'delivery','status'=>$status,'rider_id'=>100],
                    ['role'=>$role,'id'=>100]));
            }
        }
    }

    public function testTheEnumDoesNotAllowSkippingPreparation(): void
    {
        self::assertSame(['confirmed','cancelled'],OrderStatus::Pending->transitions());
        self::assertSame(['preparing','cancelled'],OrderStatus::Confirmed->transitions());
        self::assertSame(['ready_for_pickup','cancelled'],OrderStatus::Preparing->transitions());
        self::assertSame(['delivered'],OrderStatus::OutForDelivery->transitions());
    }
}
