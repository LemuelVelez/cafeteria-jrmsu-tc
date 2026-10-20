<?php

namespace App\Services;

/** Alert only on worsening stock level; a better level re-arms the next episode. */
final class StockAlertPolicy
{
    public static function transition(?int $previous, int $stock, int $reorderLevel, int $maxStock,
        int $yellowPct = 50, int $redPct = 25, bool $halfEnabled = true): ?string
    {
        if ($previous === null) return null;
        $before = StockLevelPolicy::evaluate($previous, $reorderLevel, $maxStock, $yellowPct, $redPct)['level'];
        $after = StockLevelPolicy::evaluate($stock, $reorderLevel, $maxStock, $yellowPct, $redPct)['level'];
        $severity = ['green' => 0, 'yellow' => 1, 'red' => 2, 'empty' => 3];
        if ($severity[$after] <= $severity[$before]) return null;
        return match ($after) {
            'yellow' => $halfEnabled ? 'stock_half' : null,
            'red' => 'low_stock',
            'empty' => 'out_of_stock',
            default => null,
        };
    }
}
