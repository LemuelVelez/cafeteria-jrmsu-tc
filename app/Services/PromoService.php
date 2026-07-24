<?php

namespace App\Services;

use App\Models\PromoModel;

class PromoService
{
    public function __construct(private readonly PromoModel $promos = new PromoModel())
    {
    }

    public function calculate(string $code, float $subtotal): array
    {
        $promo = $this->promos->validCode($code);
        if (! $promo) {
            throw new \DomainException('Promo code is invalid or expired.');
        }
        if ((int) $promo['usage_limit'] > 0 && (int) $promo['used_count'] >= (int) $promo['usage_limit']) {
            throw new \DomainException('Promo code usage limit has been reached.');
        }
        if ($subtotal < (float) $promo['minimum_order']) {
            throw new \DomainException('Minimum order amount has not been reached.');
        }
        $discount = $promo['discount_type'] === 'percentage'
            ? $subtotal * ((float) $promo['discount_value'] / 100)
            : (float) $promo['discount_value'];
        return ['promo' => $promo, 'discount' => min($subtotal, round($discount, 2))];
    }
}
