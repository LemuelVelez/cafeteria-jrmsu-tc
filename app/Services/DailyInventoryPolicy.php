<?php
namespace App\Services;

final class DailyInventoryPolicy
{
    /** Returns physical-sales projection; opening/physical-count adjustments are never treated as purchases or waste. */
    public static function calculate(int $beginning, int $additional, int $withdrawn, int $ending, int $posSold, array $product, ?float $unitCost): array
    {
        $total = $beginning + $additional;
        $sold = $total - $withdrawn - $ending;
        $amount = null;
        $invalidBundle = false;
        try { $amount = BundlePricePolicy::amount($sold,(float)$product['price'],isset($product['bundle_quantity'])?(int)$product['bundle_quantity']:null,$product['bundle_price']??null); }
        catch (\DomainException) { $invalidBundle = true; }
        $cost = $unitCost === null ? null : round($unitCost * $sold,2);
        return ['beginning'=>$beginning,'additional'=>$additional,'total_stock'=>$total,'withdrawn'=>$withdrawn,
            'ending'=>$ending,'sold'=>$sold,'pos_sold'=>$posSold,'variance'=>$sold-$posSold,'unit_cost'=>$unitCost,
            'amount'=>$amount,'total_cost'=>$cost,'profit'=>($cost===null||$amount===null)?null:round($amount-$cost,2),
            'invalid_bundle'=>$invalidBundle];
    }
}
