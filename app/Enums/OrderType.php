<?php

namespace App\Enums;

enum OrderType: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Pickup',
            self::Delivery => 'Delivery',
        };
    }
}
