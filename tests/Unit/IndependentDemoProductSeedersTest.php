<?php

namespace Tests\Unit;

use App\Database\Seeds\DatabaseSeeder;
use App\Services\DemoProductNutritionCatalog;
use PHPUnit\Framework\TestCase;

/** Synthetic measurements exist ONLY in tests, never in production CSVs. */
final class IndependentDemoProductSeedersTest extends TestCase
{
    public function testEightRegisteredDistinctSeederClassesMatchEightProducts(): void
    {
        self::assertCount(8, DemoProductNutritionCatalog::SEEDERS);
        self::assertCount(8, array_unique(array_values(DemoProductNutritionCatalog::SEEDERS)));
        foreach (DemoProductNutritionCatalog::SEEDERS as $seeder => $slug) {
            self::assertContains($seeder, DatabaseSeeder::SEEDERS);
            self::assertSame($slug, DemoProductNutritionCatalog::slugForSeeder($seeder));
        }
        self::assertNull(DemoProductNutritionCatalog::slugForSeeder('ProductSeeder'));
        self::assertTrue(array_search('CalamansiJuiceSeeder', DatabaseSeeder::SEEDERS, true)
            < array_search('IngredientSeeder', DatabaseSeeder::SEEDERS, true));
    }

    public function testCurrentEmptyCatalogDefersAllEightIndependently(): void
    {
        foreach (DemoProductNutritionCatalog::SEEDERS as $slug) {
            $result = DemoProductNutritionCatalog::assess($slug, [], [], []);
            self::assertFalse($result['ready']);
            self::assertNotEmpty($result['errors']);
        }
    }

    public function testOneApprovedProductDoesNotUnlockOtherSeven(): void
    {
        [$ingredients, $recipes, $worksheet] = self::syntheticFixtures();
        self::assertTrue(DemoProductNutritionCatalog::assess('chicken-adobo-rice', $ingredients, $recipes, $worksheet)['ready']);
        foreach (DemoProductNutritionCatalog::SEEDERS as $slug) {
            if ($slug !== 'chicken-adobo-rice') {
                self::assertFalse(DemoProductNutritionCatalog::assess($slug, $ingredients, $recipes, $worksheet)['ready']);
            }
        }
    }

    public function testUnsignedOrMismatchedKitchenMeasurementIsRejected(): void
    {
        [$ingredients, $recipes, $worksheet] = self::syntheticFixtures();
        $worksheet[0]['kitchen_approved_by'] = '';
        self::assertFalse(DemoProductNutritionCatalog::assess('chicken-adobo-rice', $ingredients, $recipes, $worksheet)['ready']);
        $worksheet[0]['kitchen_approved_by'] = 'Kitchen test approver';
        $worksheet[0]['measured_quantity'] = '50';
        self::assertFalse(DemoProductNutritionCatalog::assess('chicken-adobo-rice', $ingredients, $recipes, $worksheet)['ready']);
        $worksheet[0]['measured_quantity'] = '100';
        $worksheet[0]['fdc_id'] = '999';
        self::assertFalse(DemoProductNutritionCatalog::assess('chicken-adobo-rice', $ingredients, $recipes, $worksheet)['ready']);
    }

    public function testOverPreciseMeasurementIsRejectedBeforeDatabaseInsertion(): void
    {
        [$ingredients, $recipes, $worksheet] = self::syntheticFixtures();
        $recipes[0]['quantity'] = 1.2345;
        $worksheet[0]['measured_quantity'] = '1.2345';
        self::assertFalse(DemoProductNutritionCatalog::assess('chicken-adobo-rice',
            $ingredients, $recipes, $worksheet)['ready']);
    }

    public function testMissingPieceConversionRejectsOtherwiseSignedRecipe(): void
    {
        [$ingredients, $recipes, $worksheet] = self::syntheticFixtures();
        $ingredients[0]['purchase_unit'] = 'pcs';
        $recipes[0]['unit'] = 'pcs';
        $worksheet[0]['measured_unit'] = 'pcs';
        self::assertFalse(DemoProductNutritionCatalog::assess('chicken-adobo-rice', $ingredients, $recipes, $worksheet)['ready']);
    }

    private static function syntheticFixtures(): array
    {
        // The source identifier is an illustrative fixture, not a claim that
        // 100g is used in the actual cafeteria chicken-adobo recipe.
        $ingredients = [[
            'name' => 'Fixture Ingredient', 'purchase_unit' => 'g', 'nutrient_basis' => '100g',
            'density_g_per_ml' => 0.0, 'grams_per_piece' => null,
            'source_reference' => 'USDA FDC #173619 (SR Legacy)',
        ]];
        $recipes = [[
            'product_slug' => 'chicken-adobo-rice', 'ingredient_name' => 'Fixture Ingredient',
            'quantity' => 100.0, 'unit' => 'g', 'wastage_percent' => 0.0,
            'recipe_servings' => 1, 'cooking_yield' => 1.0, 'packaging_cost' => 0.0,
        ]];
        $worksheet = [[
            'product_slug' => 'chicken-adobo-rice', 'confirmed_ingredient' => 'Fixture Ingredient',
            'fdc_id' => '173619', 'measured_quantity' => '100', 'measured_unit' => 'g',
            'wastage_percent' => '0', 'recipe_servings' => '1', 'cooking_yield' => '1',
            'packaging_cost' => '0', 'kitchen_approved_by' => 'Kitchen test approver',
            'kitchen_approved_on' => '2026-10-19',
        ]];
        return [$ingredients, $recipes, $worksheet];
    }
}
