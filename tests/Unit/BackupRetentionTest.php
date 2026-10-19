<?php

namespace Tests\Unit;

use App\Services\DatabaseBackupService;
use PHPUnit\Framework\TestCase;

final class BackupRetentionTest extends TestCase
{
    public function testOlderBackupsAndSameDayDuplicatesAreNotRetained(): void
    {
        $names = [
            'cafeteria-20261019-090000-aaaaaa.sql.gz',
            'cafeteria-20261019-080000-bbbbbb.sql.gz',
            'cafeteria-20261018-090000-cccccc.sql.gz',
            'cafeteria-20261012-090000-dddddd.sql.gz',
            'cafeteria-20260909-090000-eeeeee.sql.gz',
            'cafeteria-20260801-090000-ffffff.sql.gz',
        ];
        $retained = DatabaseBackupService::retainedNames($names, 1, 1, 1);
        self::assertSame([
            'cafeteria-20261019-090000-aaaaaa.sql.gz', // most recent day
            'cafeteria-20261018-090000-cccccc.sql.gz', // previous distinct ISO week
            'cafeteria-20260909-090000-eeeeee.sql.gz', // previous distinct month
        ], $retained);
        self::assertNotContains($names[1], $retained);
        self::assertNotContains($names[5], $retained);
    }

    public function testZeroWeeklyAndMonthlyRetentionStillKeepsMostRecentDailyBackup(): void
    {
        $names = [
            'cafeteria-20261019-090000-aaaaaa.sql.gz',
            'cafeteria-20261018-090000-bbbbbb.sql.gz',
        ];
        self::assertSame([$names[0]], DatabaseBackupService::retainedNames($names, 1, 0, 0));
        self::assertSame([$names[0]], DatabaseBackupService::retainedNames($names, 0, 0, 0));
    }

    public function testBadDatesAndUnexpectedNamesAreNotIncluded(): void
    {
        self::assertSame(['cafeteria-20261019-090000-aaaaaa.sql.gz'],
            DatabaseBackupService::retainedNames([
                'cafeteria-20261019-090000-aaaaaa.sql.gz',
                'cafeteria-20260231-090000-cccccc.sql.gz',
                'cafeteria-20261019-090000-??????.sql.gz',
            ], 5, 1, 1));
    }

    public function testTamperingWithBackupFailsChecksumVerification(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cafe-checksum-');
        self::assertIsString($path);
        try {
            file_put_contents($path, 'known-backup-data');
            file_put_contents($path . '.sha256', hash_file('sha256', $path));
            self::assertTrue(DatabaseBackupService::checksum($path));
            file_put_contents($path, 'tampered-backup-data');
            self::assertFalse(DatabaseBackupService::checksum($path));
        } finally {
            @unlink($path);
            @unlink($path . '.sha256');
        }
    }
}
