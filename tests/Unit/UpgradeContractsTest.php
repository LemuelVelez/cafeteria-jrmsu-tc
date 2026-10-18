<?php
namespace Tests\Unit;
use App\Enums\OrderStatus;
use App\Services\AttendanceService;
use App\Services\AuditLogService;
use App\Services\BarcodeService;
use App\Services\CostingService;
use App\Services\DatabaseBackupService;
use App\Services\PasswordHasher;
use App\Services\TotpService;
use PHPUnit\Framework\TestCase;

final class UpgradeContractsTest extends TestCase
{
    public function testTotpMatchesPublishedRfcTimestampVector(): void
    {
        // RFC 6238 (SHA1 counter at t=59): eight-digit 94287082 => 287082 for 6 digits.
        $secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        self::assertSame('287082',TotpService::code($secret,1));
        self::assertTrue(TotpService::verify($secret,'287082',59));
        self::assertFalse(TotpService::verify($secret,'287082',300));
    }
    public function testPasswordHasherUpgradesLegacyBcrypt(): void
    {
        $legacy=password_hash('River!Quartz27',PASSWORD_BCRYPT,['cost'=>10]);
        self::assertTrue(PasswordHasher::needsRehash($legacy));
        $new=PasswordHasher::hash('River!Quartz27');
        self::assertTrue(password_verify('River!Quartz27',$new));
        self::assertFalse(PasswordHasher::needsRehash($new));
    }
    public function testDeliveredIsDistinctFulfilledTerminalStatus(): void
    {
        self::assertContains('delivered',OrderStatus::OutForDelivery->transitions());
        self::assertNotContains('completed',OrderStatus::OutForDelivery->transitions());
        self::assertTrue(OrderStatus::Delivered->isFulfilled());
        self::assertTrue(OrderStatus::Completed->isFulfilled());
        self::assertFalse(OrderStatus::Cancelled->isFulfilled());
    }
    public function testBarcodeChecksums(): void
    {
        self::assertTrue(BarcodeService::valid('4006381333931'));
        self::assertFalse(BarcodeService::valid('4006381333932'));
        self::assertTrue(BarcodeService::valid('036000291452'));
        self::assertFalse(BarcodeService::valid('036000291453'));
        self::assertTrue(BarcodeService::valid('JRMSU-001'));
    }
    public function testRecipeUnitConversionsAndMismatches(): void
    {
        self::assertSame(1500.0,CostingService::convert(1.5,'kg','g'));
        self::assertSame(1.5,CostingService::convert(1500,'ml','l'));
        $this->expectException(\DomainException::class);
        CostingService::convert(10,'pcs','g');
    }
    public function testAuditDiffScrubbingRemovesNestedPrivateData(): void
    {
        $clean=AuditLogService::scrub(['price'=>100,'profile'=>['phone'=>'09123456789','nested'=>['password'=>'secret','rating'=>5]],'session_token'=>'x']);
        self::assertSame(['price'=>100,'profile'=>['nested'=>['rating'=>5]]],$clean);
    }
    public function testAttendanceNightOvertimeAndWageEstimate(): void
    {
        $r=AttendanceService::estimate('2026-10-19 22:00:00','2026-10-20 07:00:00','22:00','06:00',100,5,.25,.10);
        self::assertSame(8.0,$r['regular_hours']);
        self::assertSame(1.0,$r['overtime_hours']);
        self::assertSame(8.0,$r['night_hours']);
        self::assertSame(1005.0,$r['wage_estimate']);
    }
    public function testBackupChecksumRejectsMissingFilesAndDetectsTampering(): void
    {
        $temp=tempnam(sys_get_temp_dir(),'cafetest');self::assertIsString($temp);
        try {
            file_put_contents($temp,'backup');file_put_contents($temp.'.sha256',hash_file('sha256',$temp));
            self::assertTrue(DatabaseBackupService::checksum($temp));
            file_put_contents($temp,'tampered');self::assertFalse(DatabaseBackupService::checksum($temp));
        } finally {unlink($temp);unlink($temp.'.sha256');}
    }
    public function testSharedInventoryAndPreparationRulesAreEnforcedByServices(): void
    {
        $order=file_get_contents(dirname(__DIR__,2).'/app/Services/OrderService.php');
        $inventory=file_get_contents(dirname(__DIR__,2).'/app/Services/InventoryService.php');
        $notification=file_get_contents(dirname(__DIR__,2).'/app/Services/NotificationService.php');
        foreach(['FOR UPDATE','prepared_at','handoff_verified_at','restockForCancelledOrder','isFulfilled'] as $word)self::assertStringContainsString($word,$order);
        foreach(['cancel_restock','stock_out','inventory_movements','FOR UPDATE'] as $word)self::assertStringContainsString($word,$inventory);
        foreach(['$crossed','$becameZero','staffUsers'] as $word)self::assertStringContainsString($word,$notification);
    }
}
