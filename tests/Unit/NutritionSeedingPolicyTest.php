<?php

namespace Tests\Unit;

use App\Services\NutritionSeedingPolicy;
use PHPUnit\Framework\TestCase;

final class NutritionSeedingPolicyTest extends TestCase
{
    public function testEmptyNutritionCatalogDefersBothUnrecordedSeedersButNotCoreSeeders(): void
    {
        foreach (['IngredientSeeder', 'RecipeSeeder'] as $seeder) {
            self::assertSame('deferred', NutritionSeedingPolicy::disposition($seeder, false, false));
        }

        foreach (['UserSeeder', 'CategorySeeder', 'ProductSeeder', 'NotificationSeeder'] as $seeder) {
            self::assertSame('normal', NutritionSeedingPolicy::disposition($seeder, false, false));
        }
    }

    public function testCatalogReadinessEnablesBothNutritionSeeders(): void
    {
        self::assertSame('normal', NutritionSeedingPolicy::disposition('IngredientSeeder', true, false));
        self::assertSame('normal', NutritionSeedingPolicy::disposition('RecipeSeeder', true, false));
    }

    public function testRecordedNutritionSeedersWithMissingSourceNeverAppearComplete(): void
    {
        foreach (['IngredientSeeder', 'RecipeSeeder'] as $seeder) {
            self::assertSame('invalid_record', NutritionSeedingPolicy::disposition($seeder, false, true));
        }
    }

    public function testCoreSeedCanSucceedWhileStrictAcceptanceRefusesIncompleteNutrition(): void
    {
        self::assertTrue(NutritionSeedingPolicy::completed(0, 2, false));
        self::assertFalse(NutritionSeedingPolicy::completed(0, 2, true));
        self::assertTrue(NutritionSeedingPolicy::completed(0, 0, true));
        self::assertFalse(NutritionSeedingPolicy::completed(1, 0, false));
        self::assertFalse(NutritionSeedingPolicy::completed(1, 2, false));
    }
}
