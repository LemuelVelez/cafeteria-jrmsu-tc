<?php
namespace Tests\Unit;

use App\Services\BundlePricePolicy;
use App\Services\CashCountPolicy;
use App\Services\DailyInventoryPolicy;
use App\Services\InventoryService;
use App\Services\OnCallWagePolicy;
use PHPUnit\Framework\TestCase;

final class CounterFormsPolicyTest extends TestCase
{
    public function testDailyLedgerArithmeticAndVariance(): void
    {
        // 150+20=170, not 165. The panel's test example contains an arithmetic typo.
        // Use the explicit field definitions instead of silently subtracting withdrawn stock twice.
        $row=DailyInventoryPolicy::calculate(150,20,5,30,125,['price'=>10,'bundle_quantity'=>null,'bundle_price'=>null],6.0);
        self::assertSame(170,$row['total_stock']);
        self::assertSame(135,$row['sold']);
        self::assertSame(10,$row['variance']);
        self::assertSame(1350.0,$row['amount']);
        self::assertSame(810.0,$row['total_cost']);
        self::assertSame(540.0,$row['profit']);
        // Consistent fixture gives 130 sold, ₱1,300 revenue, ₱780 cost, ₱520 profit.
        $corrected=DailyInventoryPolicy::calculate(150,20,5,35,125,['price'=>10],6.0);
        self::assertSame(130,$corrected['sold']);self::assertSame(5,$corrected['variance']);
        self::assertSame(1300.0,$corrected['amount']);self::assertSame(780.0,$corrected['total_cost']);
        self::assertSame(520.0,$corrected['profit']);
    }
    public function testUncostedRevenueIsNotMisrepresentedAsProfit(): void
    {
        $row=DailyInventoryPolicy::calculate(15,0,0,0,15,['price'=>15],null);
        self::assertSame(225.0,$row['amount']);self::assertNull($row['total_cost']);self::assertNull($row['profit']);
    }
    public function testSheetAmountsUseSoldNotEndingStock(): void
    {
        foreach([[24,35,840],[29,35,1015],[150,10,1500],[20,15,300]] as [$quantity,$price,$expected]) {
            self::assertSame((float)$expected,BundlePricePolicy::amount($quantity,$price));
        }
    }
    public function testBundlePricingAndRemainder(): void
    {
        self::assertSame(160.0,BundlePricePolicy::amount(24,0,3,20));
        self::assertSame(167.0,BundlePricePolicy::amount(25,7,3,20));
        $this->expectException(\DomainException::class);
        BundlePricePolicy::amount(25,0,3,20);
    }
    public function testCashCountsAndReconciliation(): void
    {
        $cash=CashCountPolicy::count([1000=>4,5=>3]);
        self::assertSame(4015.0,$cash['total']);
        self::assertSame(4000,$cash['lines']['1000']['subtotal']);
        self::assertSame(15,$cash['lines']['5']['subtotal']);
        self::assertSame(-85.0,CashCountPolicy::variance(4100.0,4015.0));
    }
    public function testNegativeCashPiecesRejected(): void
    {
        $this->expectException(\DomainException::class);
        CashCountPolicy::count([1000=>-1]);
    }
    public function testOnCallWagesAndMissingRate(): void
    {
        self::assertSame(505.0,OnCallWagePolicy::calculate(8,25,'60.00'));
        foreach([[8,60,'60.00'],[8,25,null]] as [$hours,$minutes,$rate]) {
            try { OnCallWagePolicy::calculate($hours,$minutes,$rate);self::fail('Invalid wage input was accepted.'); }
            catch(\DomainException $e){self::assertNotEmpty($e->getMessage());}
        }
    }
    public function testWithdrawalsRequireReasonAndRemarks(): void
    {
        foreach([['','past date'],['expired',''],['other','past date']] as [$code,$remarks]) {
            try { InventoryService::validateWithdrawal($code,$remarks,5);self::fail('Missing withdrawal evidence accepted.'); }
            catch(\DomainException $e){self::assertNotEmpty($e->getMessage());}
        }
        InventoryService::validateWithdrawal('expired','past date',5);
        self::assertTrue(true);
    }
}
