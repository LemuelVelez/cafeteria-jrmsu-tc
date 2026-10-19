<?php

namespace Tests\Unit;

use App\Services\BackupRestoreDrillService;
use PHPUnit\Framework\TestCase;

final class BackupRestoreDrillTest extends TestCase
{
    public function testScratchDatabaseIsNotTheLiveDatabase(): void
    {
        self::assertSame('jrmsu_tc_cafeteria_restore_check',
            BackupRestoreDrillService::scratchDatabaseName('jrmsu_tc_cafeteria'));
        self::assertNotSame('jrmsu_tc_cafeteria',
            BackupRestoreDrillService::scratchDatabaseName('jrmsu_tc_cafeteria'));
    }

    public function testUnsafeSchemasAndTestDatabaseAreRejected(): void
    {
        foreach (['database_test', 'x` DROP DATABASE live', '', str_repeat('a', 60),
            'a-db', '../db', '12database'] as $input) {
            try {
                BackupRestoreDrillService::scratchDatabaseName($input);
                self::fail('Expected rejection for ' . $input);
            } catch (\DomainException $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }
    }

    public function testRowCountComparisonDetectsChangesMissingAndExtraTables(): void
    {
        self::assertSame([], BackupRestoreDrillService::mismatches(
            ['orders' => 10, 'payments' => 7], ['payments' => 7, 'orders' => 10]));
        self::assertSame([
            'orders' => ['source' => 10, 'restored' => 8],
            'payments' => ['source' => 7, 'restored' => null],
            'extra' => ['source' => null, 'restored' => 1],
        ], BackupRestoreDrillService::mismatches(
            ['orders' => 10, 'payments' => 7], ['orders' => 8, 'extra' => 1]));
    }

    public function testDrillCountsKnownRelationalTables(): void
    {
        self::assertContains('product_recipes', BackupRestoreDrillService::TABLES);
        self::assertContains('order_handoffs', BackupRestoreDrillService::TABLES);
        self::assertNotContains('audit_logs', BackupRestoreDrillService::TABLES,
            'Backup creation itself adds a live audit event after the dump; that is not a lost row.');
    }
}
