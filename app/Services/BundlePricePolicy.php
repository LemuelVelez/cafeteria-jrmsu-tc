<?php
namespace App\Services;

final class BundlePricePolicy
{
    public static function amount(int $quantity, string|int|float $singlePrice, ?int $bundleQuantity = null, string|int|float|null $bundlePrice = null): float
    {
        if ($quantity < 0 || (float)$singlePrice < 0) throw new \DomainException('Quantity and price must be nonnegative.');
        if ($bundleQuantity === null) return round($quantity * (float)$singlePrice,2);
        if ($bundleQuantity < 2 || $bundlePrice === null || (float)$bundlePrice < 0) throw new \DomainException('Bundle quantity and price must be valid.');
        $remainder = $quantity % $bundleQuantity;
        if ($remainder !== 0 && (float)$singlePrice == 0.0) throw new \DomainException('This bundle is sold only in groups of '.$bundleQuantity.'; a single-piece price is not set.');
        return round(intdiv($quantity,$bundleQuantity) * (float)$bundlePrice + $remainder * (float)$singlePrice,2);
    }
    public static function label(array $product): string
    {
        return !empty($product['bundle_quantity'])
            ? (int)$product['bundle_quantity'].' for ₱'.number_format((float)$product['bundle_price'],2)
            : '₱'.number_format((float)$product['price'],2);
    }
}
