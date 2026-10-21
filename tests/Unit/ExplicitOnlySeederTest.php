<?php

namespace Tests\Unit;

use App\Database\Seeds\DatabaseSeeder;
use PHPUnit\Framework\TestCase;

final class ExplicitOnlySeederTest extends TestCase
{
    public function testManualCounterCatalogDoesNotBlockDefaultSeedingOrGetRunAutomatically(): void
    {
        self::assertContains('CounterItemCatalogSeeder', DatabaseSeeder::EXPLICIT_ONLY_SEEDERS);
        self::assertNotContains('CounterItemCatalogSeeder', DatabaseSeeder::seeders());
        self::assertSame([], DatabaseSeeder::unregisteredSeeders([
            ...DatabaseSeeder::seeders(),
            'CounterItemCatalogSeeder',
        ]));
    }

    public function testRandomNutritionSeederIsExplicitOnly(): void
    {
        self::assertContains('RandomNutritionSeeder', DatabaseSeeder::EXPLICIT_ONLY_SEEDERS);
        self::assertNotContains('RandomNutritionSeeder', DatabaseSeeder::seeders());
        self::assertSame([], DatabaseSeeder::unregisteredSeeders([
            ...DatabaseSeeder::seeders(),
            'RandomNutritionSeeder',
        ]));
    }

    public function testUnknownSeederStillBlocksPreflight(): void
    {
        self::assertSame(['UnexpectedSeeder'], DatabaseSeeder::unregisteredSeeders([
            ...DatabaseSeeder::seeders(),
            'CounterItemCatalogSeeder',
            'UnexpectedSeeder',
        ]));
    }

    public function testCurrentSeederFilesAreEitherRegisteredOrExplicitOnly(): void
    {
        $seederFiles = glob(dirname(__DIR__, 2) . '/app/Database/Seeds/*Seeder.php');
        self::assertIsArray($seederFiles);
        $names = array_map(static fn (string $file): string => basename($file, '.php'), $seederFiles);
        $names = array_values(array_diff($names, ['DatabaseSeeder']));
        self::assertSame([], DatabaseSeeder::unregisteredSeeders($names));
        self::assertSame([], array_intersect(DatabaseSeeder::SEEDERS, DatabaseSeeder::EXPLICIT_ONLY_SEEDERS));
    }
}
