<?php

namespace App\Services;

/** A stock item alerts exactly once when crossing below its reorder level. */
final class StockAlertPolicy
{
    public static function crossed(?int $previous, int $stock, int $level): bool
    {
        return $previous !== null && $previous > $level && $stock <= $level;
    }
}
