<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Services\OrderService;
use App\Validation\PasswordRules;
use PHPUnit\Framework\TestCase;

final class PanelCorrectionsTest extends TestCase
{
    public function testStrongPasswordPolicyRejectsWeakCommonAndPersonalPasswords(): void
    {
        $rule = new PasswordRules();
        $error = null;
        self::assertFalse($rule->strong_password('weakpass', null, ['name'=>'Maria Santos','email'=>'maria@example.com'], $error));
        self::assertFalse($rule->strong_password('Password123!', null, ['name'=>'Other User','email'=>'other@example.com'], $error));
        self::assertFalse($rule->strong_password('Maria#2026Secure', null, ['name'=>'Maria','email'=>'maria@example.com'], $error));
        self::assertTrue($rule->strong_password('River!Quartz27', null, ['name'=>'Maria','email'=>'maria@example.com'], $error));
    }

    public function testOrderStatusWorkflowUsesPanelLabels(): void
    {
        self::assertSame('Ready for Pickup', OrderStatus::ReadyForPickup->label());
        self::assertSame('Completed', OrderStatus::Completed->label());
        self::assertSame(['confirmed','cancelled'], OrderStatus::Pending->transitions());
        self::assertContains('ready_for_pickup', OrderStatus::Preparing->transitions());
        self::assertTrue(OrderStatus::Completed->isTerminal());
    }

    public function testRoleTransitionsProtectPickupAndAssignedDelivery(): void
    {
        $pickup = ['status'=>'ready_for_pickup','order_type'=>'pickup','rider_id'=>null];
        self::assertContains('completed', OrderService::allowedTransitions($pickup, ['role'=>'cashier','id'=>2]));
        self::assertNotContains('out_for_delivery', OrderService::allowedTransitions($pickup, ['role'=>'cashier','id'=>2]));

        $delivery = ['status'=>'ready_for_pickup','order_type'=>'delivery','rider_id'=>7];
        self::assertSame(['out_for_delivery'], OrderService::allowedTransitions($delivery, ['role'=>'rider','id'=>7]));
        self::assertSame([], OrderService::allowedTransitions($delivery, ['role'=>'rider','id'=>8]));
    }

    public function testSignedOrderQrUsesAnAuthenticatedExpiringPurposeBoundToken(): void
    {
        $issuedAt = 1790000000;
        $key = 'fixture-key';
        $secret = 'fixture-order-request';
        $qr = \App\Services\SignedOrderQr::issue(9, $secret, $key,
            \App\Services\SignedOrderQr::KITCHEN, $issuedAt);
        self::assertTrue(\App\Services\SignedOrderQr::matches($qr, $secret, $key,
            \App\Services\SignedOrderQr::KITCHEN, $issuedAt + 20));
        self::assertFalse(\App\Services\SignedOrderQr::matches($qr, $secret, $key,
            \App\Services\SignedOrderQr::DELIVERY, $issuedAt + 20));
        self::assertFalse(\App\Services\SignedOrderQr::matches($qr, $secret, $key,
            \App\Services\SignedOrderQr::KITCHEN,
            $issuedAt + \App\Services\SignedOrderQr::VALID_FOR_SECONDS));
    }

}
