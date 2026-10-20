<?php

namespace Tests\Unit;

use App\Services\StockAlertPolicy;
use App\Services\StockLevelPolicy;
use PHPUnit\Framework\TestCase;

final class StockLevelPolicyTest extends TestCase
{
    public function testStockLevelsAndBoundaries(): void
    {
        $cases = [
            [0, 5, 100, 'empty', 0],
            [5, 5, 100, 'red', 5],
            [25, 5, 100, 'red', 25],
            [50, 5, 100, 'yellow', 50],
            [51, 5, 100, 'green', 51],
            [120, 5, 100, 'green', 100],
            [5, 5, 0, 'red', 0],
            [6, 5, 0, 'green', 0],
        ];
        foreach ($cases as [$stock, $reorder, $max, $level, $percent]) {
            $result = StockLevelPolicy::evaluate($stock, $reorder, $max);
            self::assertSame($level, $result['level']);
            self::assertSame($percent, $result['percent']);
            self::assertNotSame('', $result['label']);
            self::assertSame('stock-level--'.$level, $result['cssClass']);
        }
    }

    public function testAlertsForWorseningLevelsAndHalfSetting(): void
    {
        self::assertSame('stock_half', StockAlertPolicy::transition(80, 50, 5, 100));
        self::assertNull(StockAlertPolicy::transition(50, 30, 5, 100));
        self::assertSame('low_stock', StockAlertPolicy::transition(30, 25, 5, 100));
        self::assertSame('out_of_stock', StockAlertPolicy::transition(25, 0, 5, 100));
        self::assertNull(StockAlertPolicy::transition(0, 80, 5, 100));
        self::assertSame('stock_half', StockAlertPolicy::transition(80, 50, 5, 100));
        self::assertNull(StockAlertPolicy::transition(80, 50, 5, 100, 50, 25, false));
    }
}
