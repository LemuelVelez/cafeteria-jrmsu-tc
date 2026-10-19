<?php

namespace Tests\Unit;

use App\Services\CostingService;
use App\Services\SourcedRecipeCatalog;
use PHPUnit\Framework\TestCase;

/** Synthetic test numbers only; these are not production nutrient source data. */
final class SourcedRecipeCatalogTest extends TestCase
{
    private function ingredient(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Synthetic flour', 'purchase_unit' => 'g', 'cost_per_unit' => '0.1',
            'supplier' => 'Demo price – replace with actual receipts',
            'nutrient_basis' => '100g', 'calories' => '100', 'protein_g' => '5',
            'carbohydrates_g' => '20', 'fat_g' => '1', 'sugar_g' => '0',
            'fiber_g' => '2', 'sodium_mg' => '10', 'density_g_per_ml' => '1',
            'grams_per_piece' => '', 'reorder_level' => '3', 'opening_stock' => '20',
            'source_reference' => 'USDA FDC #1000 (Foundation Foods)',
        ], $overrides);
    }

    public function testMissingSourceAndMissingNutrientsAreRejectedInsteadOfDefaultingToZero(): void
    {
        foreach ([['source_reference' => ''], ['source_reference' => 'PhilFCT unknown'],
            ['calories' => ''], ['protein_g' => 'invented'], ['supplier' => 'Local store']] as $fields) {
            try {
                SourcedRecipeCatalog::ingredient($this->ingredient($fields));
                self::fail('Input should have been rejected: ' . implode(',', array_keys($fields)));
            } catch (\DomainException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }
        }
    }

    public function testSourceFormatAndMeasuredPieceWeightAreEnforced(): void
    {
        self::assertTrue(SourcedRecipeCatalog::validReference('PhilFCT 2019, food ID A123 – Example'));
        self::assertTrue(SourcedRecipeCatalog::validReference('USDA FDC #1234 (Foundation Foods)'));
        self::assertFalse(SourcedRecipeCatalog::validReference('USDA FDC source pending'));
        $this->expectException(\DomainException::class);
        SourcedRecipeCatalog::ingredient($this->ingredient(['purchase_unit' => 'pcs']));
    }

    public function testPieceNutritionCalculationAndCookingYield(): void
    {
        $record = SourcedRecipeCatalog::ingredient($this->ingredient([
            'purchase_unit' => 'pcs', 'grams_per_piece' => '50',
        ]));
        self::assertSame(50.0, $record['grams_per_piece']);
        self::assertSame(100.0, CostingService::nutrientQuantity(2, 'pcs', 1, $record['grams_per_piece']));
        // Cooking yield intentionally does not enter per-serving nutrient math.
        self::assertSame(['calories' => 100.0], CostingService::perServingNutrients(['calories' => 400], 4));
    }

    public function testRecipeRejectsFractionalServingsAndUnknownUnits(): void
    {
        $valid = [
            'product_slug' => 'crispy-fries', 'ingredient_name' => 'Synthetic flour',
            'quantity' => '200', 'unit' => 'g', 'wastage_percent' => '5',
            'recipe_servings' => '4', 'cooking_yield' => '0.7', 'packaging_cost' => '2',
        ];
        $recipe = SourcedRecipeCatalog::recipe($valid);
        self::assertSame(4, $recipe['recipe_servings']);
        self::assertSame(0.7, $recipe['cooking_yield']);
        foreach ([['recipe_servings' => '1.5'], ['unit' => 'dozen'], ['cooking_yield' => '0']] as $invalid) {
            try {
                SourcedRecipeCatalog::recipe(array_replace($valid, $invalid));
                self::fail('Invalid recipe should throw.');
            } catch (\DomainException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }
        }
    }

    public function testCsvHeaderAndIncompleteRowsAreRejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'recipe-test-');
        self::assertIsString($path);
        try {
            file_put_contents($path, 'name,purchase_unit' . "\n");
            $this->expectException(\DomainException::class);
            SourcedRecipeCatalog::read($path, SourcedRecipeCatalog::INGREDIENT_COLUMNS);
        } finally {
            @unlink($path);
        }
    }

    public function testSourceCatalogFilesAreWellFormed(): void
    {
        self::assertIsArray(SourcedRecipeCatalog::read(
            APPPATH . 'Database/Seeds/data/ingredients.csv', SourcedRecipeCatalog::INGREDIENT_COLUMNS));
        self::assertIsArray(SourcedRecipeCatalog::read(
            APPPATH . 'Database/Seeds/data/recipes.csv', SourcedRecipeCatalog::RECIPE_COLUMNS));
    }
}
