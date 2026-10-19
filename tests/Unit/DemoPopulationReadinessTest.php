<?php

namespace Tests\Unit;

use App\Services\DemoPopulationReadiness;
use App\Services\DemoProductNutritionCatalog;
use PHPUnit\Framework\TestCase;

final class DemoPopulationReadinessTest extends TestCase
{
    private const REGISTERED = [
        'UserSeeder', 'CategorySeeder', 'ProductSeeder',
        'ChickenAdoboRiceSeeder', 'PorkSisigRiceSeeder',
        'IngredientSeeder', 'RecipeSeeder',
        'SettingSeeder', 'NotificationSeeder',
    ];

    public function testDeferredNutritionDoesNotInvalidateAppliedCoreSeeders(): void
    {
        $recorded = ['UserSeeder', 'CategorySeeder', 'ProductSeeder',
            'SettingSeeder', 'NotificationSeeder'];
        self::assertTrue(DemoPopulationReadiness::isReady(
            self::REGISTERED, $recorded, array_values(DemoProductNutritionCatalog::SEEDERS),
        ));
    }

    public function testMissingCoreSeederMarkerCannotBeReportedAsComplete(): void
    {
        $recorded = ['UserSeeder', 'CategorySeeder', 'ProductSeeder', 'SettingSeeder'];
        self::assertFalse(DemoPopulationReadiness::isReady(
            self::REGISTERED, $recorded, array_values(DemoProductNutritionCatalog::SEEDERS),
        ));
    }

    public function testMissingDemoProductCannotBeReportedAsComplete(): void
    {
        $recorded = ['UserSeeder', 'CategorySeeder', 'ProductSeeder',
            'SettingSeeder', 'NotificationSeeder'];
        $products = array_values(DemoProductNutritionCatalog::SEEDERS);
        array_pop($products);
        self::assertFalse(DemoPopulationReadiness::isReady(self::REGISTERED, $recorded, $products));
    }

    public function testOldNutritionMarkersDoNotCountAsCoreRequirements(): void
    {
        self::assertSame(
            ['UserSeeder', 'CategorySeeder', 'ProductSeeder', 'SettingSeeder', 'NotificationSeeder'],
            DemoPopulationReadiness::coreSeeders(self::REGISTERED),
        );
    }
}
