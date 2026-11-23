<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case ReadyForPickup = 'ready_for_pickup';
    case ReadyForDelivery = 'ready_for_delivery';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Preparing => 'Preparing',
            self::ReadyForPickup => 'Ready for Pickup',
            self::ReadyForDelivery => 'Ready for Delivery',
            self::OutForDelivery => 'Out for Delivery',
            self::Completed => 'Completed',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'bi-clock',
            self::Confirmed => 'bi-check-circle',
            self::Preparing => 'bi-cup-hot',
            self::ReadyForPickup => 'bi-bag-check',
            self::ReadyForDelivery => 'bi-box-seam',
            self::OutForDelivery => 'bi-bicycle',
            self::Completed => 'bi-check2-circle',
            self::Delivered => 'bi-check2-all',
            self::Cancelled => 'bi-x-circle',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Confirmed => 'confirmed',
            self::Preparing => 'preparing',
            self::ReadyForPickup => 'ready-for-pickup',
            self::ReadyForDelivery => 'ready-for-delivery',
            self::OutForDelivery => 'out-for-delivery',
            self::Completed => 'completed',
            self::Delivered => 'delivered',
            self::Cancelled => 'cancelled',
        };
    }

    public function customerDescription(): string
    {
        return match ($this) {
            self::Pending => 'Your order was received and is waiting for confirmation.',
            self::Confirmed => 'Your order has been confirmed.',
            self::Preparing => 'Your meal is being prepared.',
            self::ReadyForPickup => 'Your order is ready. Please proceed to the counter.',
            self::ReadyForDelivery => 'Your order is packed and ready for delivery. It will be dispatched once your rider is verified.',
            self::OutForDelivery => 'Your order is on the way.',
            self::Completed => 'Your order has been picked up. Thank you!',
            self::Delivered => 'Your order has been delivered. Thank you!',
            self::Cancelled => 'This order was cancelled.',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Delivered, self::Cancelled], true);
    }

    public function isFulfilled(): bool
    {
        return in_array($this, [self::Completed, self::Delivered], true);
    }

    /** @return list<string> */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed->value, self::Cancelled->value],
            self::Confirmed => [self::Preparing->value, self::Cancelled->value],
            self::Preparing => [self::ReadyForPickup->value, self::ReadyForDelivery->value, self::Cancelled->value],
            self::ReadyForPickup => [self::Completed->value, self::Cancelled->value],
            self::ReadyForDelivery => [self::OutForDelivery->value, self::Cancelled->value],
            self::OutForDelivery => [self::Delivered->value],
            self::Completed, self::Delivered, self::Cancelled => [],
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
