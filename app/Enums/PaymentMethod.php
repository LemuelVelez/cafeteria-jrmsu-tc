<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CashOnPickup = 'cash_on_pickup';
    case CashOnDelivery = 'cash_on_delivery';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function forOrderType(OrderType $orderType): self
    {
        return match ($orderType) {
            OrderType::Pickup => self::CashOnPickup,
            OrderType::Delivery => self::CashOnDelivery,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CashOnPickup => 'Cash on Pickup',
            self::CashOnDelivery => 'Cash on Delivery',
        };
    }
}
