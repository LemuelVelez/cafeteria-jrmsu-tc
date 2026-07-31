<?php

namespace App\Services;

use App\Models\PromoModel;
use CodeIgniter\Database\BaseConnection;

class PromoService
{
    private BaseConnection $db;

    public function __construct(private readonly PromoModel $promos = new PromoModel())
    {
        $this->db = db_connect();
    }

    public function calculate(string $code, float $subtotal, bool $lockForUpdate = false): array
    {
        $promo = $lockForUpdate
            ? $this->findValidCodeForUpdate($code)
            : $this->promos->validCode($code);

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

    private function findValidCodeForUpdate(string $code): ?array
    {
        $now = date('Y-m-d H:i:s');
        $promo = $this->db->query(
            <<<'SQL'
            SELECT *
            FROM promos
            WHERE code = ?
              AND is_active = 1
              AND (starts_at IS NULL OR starts_at <= ?)
              AND (ends_at IS NULL OR ends_at >= ?)
            FOR UPDATE
            SQL,
            [strtoupper(trim($code)), $now, $now],
        )->getRowArray();

        return $promo ?: null;
    }
}
