<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DatabaseBackupService;
use Config\Filters;
use Config\Pager;
use PHPUnit\Framework\TestCase;

final class RuntimeRegressionTest extends TestCase
{
    public function testPagerConfigurationIsAvailableForModelPagination(): void
    {
        $config = config(Pager::class);
        self::assertInstanceOf(Pager::class, $config);
        self::assertArrayHasKey('default_full', $config->templates);
        self::assertArrayHasKey('default_simple', $config->templates);
        self::assertGreaterThan(0, $config->perPage);
    }

    public function testCspIncompatibleDebugToolbarIsNotInjected(): void
    {
        $filters = new Filters();
        self::assertNotContains('toolbar', $filters->globals['after'] ?? []);
        self::assertArrayNotHasKey('toolbar', $filters->globals['after'] ?? []);
    }

    public function testBackupExecutableCanBeAbsentWithoutUsingShell(): void
    {
        self::assertNull(DatabaseBackupService::findDumpExecutable('/unavailable/mysql/dump/directory'));
    }

    public function testPhpBackupFallbackWritesCompleteGzipWithBoundedQueries(): void
    {
        $db = new class {
            /** @var list<string> */
            public array $queries = [];

            public function query(string $sql): object
            {
                $this->queries[] = $sql;
                if ($sql === 'SHOW FULL TABLES') {
                    return new class {
                        public function getResultArray(): array
                        {
                            return [['Tables_in_cafeteria' => 'products', 'Table_type' => 'BASE TABLE']];
                        }
                    };
                }
                if ($sql === 'SHOW CREATE TABLE `products`') {
                    return new class {
                        public function getRowArray(): array
                        {
                            return ['Create Table' => 'CREATE TABLE `products` (`id` int NOT NULL, `name` varchar(40))'];
                        }
                    };
                }
                if ($sql === 'SELECT * FROM `products` LIMIT 200 OFFSET 0') {
                    return new class {
                        public function getResultArray(): array
                        {
                            return [['id' => 1, 'name' => 'Chicken adobo']];
                        }
                    };
                }
                throw new \RuntimeException('Unexpected backup query: ' . $sql);
            }

            public function escape(mixed $value): string
            {
                return $value === null ? 'NULL' : "'" . str_replace("'", "''", (string) $value) . "'";
            }
        };

        $path = tempnam(sys_get_temp_dir(), 'cafe-backup-test-');
        self::assertNotFalse($path);
        try {
            $stream = gzopen($path, 'wb9');
            self::assertNotFalse($stream);
            try {
                (new \ReflectionMethod(DatabaseBackupService::class, 'dumpPhp'))
                    ->invoke(new DatabaseBackupService(), $db, $stream);
            } finally {
                gzclose($stream);
            }
            $sql = gzdecode((string) file_get_contents($path));
            self::assertIsString($sql);
            self::assertStringContainsString('CREATE TABLE `products`', $sql);
            self::assertStringContainsString("INSERT INTO `products` (`id`,`name`) VALUES ('1','Chicken adobo');", $sql);
            self::assertStringContainsString('SET FOREIGN_KEY_CHECKS=1;', $sql);
            self::assertContains('SELECT * FROM `products` LIMIT 200 OFFSET 0', $db->queries);
        } finally {
            @unlink($path);
        }
    }
}
