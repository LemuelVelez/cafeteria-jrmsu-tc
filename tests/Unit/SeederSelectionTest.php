<?php

namespace Tests\Unit;

use App\Database\Seeds\DatabaseSeeder;
use App\Services\DemoProductNutritionCatalog;
use PHPUnit\Framework\TestCase;

final class SeederSelectionTest extends TestCase
{
    public function testDefaultSeedingFinishesWithNoKitchenApprovedRecipes(): void
    {
        self::assertSame(DatabaseSeeder::requiredSeeders(), DatabaseSeeder::runnableSeeders([]));
        self::assertCount(8, DatabaseSeeder::requiredSeeders());
        self::assertContains('DemoRecipePreviewSeeder', DatabaseSeeder::requiredSeeders());
        self::assertNotContains('IngredientSeeder', DatabaseSeeder::runnableSeeders([]));
        self::assertNotContains('RecipeSeeder', DatabaseSeeder::runnableSeeders([]));
    }

    public function testOneApprovedRecipeCanBeImportedIndependently(): void
    {
        $selected = DatabaseSeeder::runnableSeeders(['chicken-adobo-rice']);
        self::assertContains('ChickenAdoboRiceSeeder', $selected);
        self::assertNotContains('PorkSisigRiceSeeder', $selected);
        self::assertCount(count(DatabaseSeeder::requiredSeeders()) + 1, $selected);
    }

    public function testStrictAcceptanceSelectsAllEightAndNeverReplaysLegacyBulkSeeders(): void
    {
        $strict = DatabaseSeeder::runnableSeeders([], true);
        foreach (DemoProductNutritionCatalog::SEEDERS as $seeder => $slug) {
            self::assertContains($seeder, $strict, $slug);
        }
        self::assertCount(count(DatabaseSeeder::requiredSeeders()) + 8, $strict);
        self::assertNotContains('IngredientSeeder', $strict);
        self::assertNotContains('RecipeSeeder', $strict);
        self::assertNotContains('CounterItemCatalogSeeder', $strict);
    }

    public function testUnknownOrUnapprovedRecipeNamesCannotTriggerSeeding(): void
    {
        self::assertSame(DatabaseSeeder::requiredSeeders(), DatabaseSeeder::runnableSeeders([
            'not-in-demo-menu', '', 'IngredientSeeder',
        ]));
        self::assertSame(DatabaseSeeder::SEEDERS, DatabaseSeeder::seeders());
    }
}
