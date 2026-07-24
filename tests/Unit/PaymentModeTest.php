<?php

namespace Tests\Unit;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class PaymentModeTest extends TestCase
{
    public function testPickupUsesCashOnPickup(): void
    {
        self::assertSame(PaymentMethod::CashOnPickup, PaymentMethod::forOrderType(OrderType::Pickup));
    }

    public function testDeliveryUsesCashOnDelivery(): void
    {
        self::assertSame(PaymentMethod::CashOnDelivery, PaymentMethod::forOrderType(OrderType::Delivery));
    }
}
