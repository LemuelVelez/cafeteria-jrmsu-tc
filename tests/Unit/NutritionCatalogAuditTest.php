<?php

namespace Tests\Unit;

use App\Services\NutritionCatalogAudit;
use PHPUnit\Framework\TestCase;

/** Synthetic quantities below are test fixtures, NOT actual food nutrient data. */
final class NutritionCatalogAuditTest extends TestCase
{
    private function ingredient(): array
    {
        return ['name' => 'Synthetic ingredient', 'purchase_unit' => 'g',
            'density_g_per_ml' => 1.0, 'grams_per_piece' => null, 'nutrient_basis' => '100g'];
    }

    private function recipe(string $slug): array
    {
        return ['product_slug' => $slug, 'ingredient_name' => 'Synthetic ingredient',
            'quantity' => 200.0, 'unit' => 'g', 'wastage_percent' => 0.0,
            'recipe_servings' => 2, 'cooking_yield' => 0.7, 'packaging_cost' => 2.0];
    }

    public function testEveryDemoProductMustHaveMeasurableRecipeLines(): void
    {
        $recipes = array_map($this->recipe(...), NutritionCatalogAudit::DEMO_PRODUCT_SLUGS);
        $ready = NutritionCatalogAudit::assess([$this->ingredient()], $recipes);
        self::assertTrue($ready['ready']);
        self::assertSame(8, $ready['covered']);
        self::assertSame([], $ready['errors']);

        array_pop($recipes);
        $blocked = NutritionCatalogAudit::assess([$this->ingredient()], $recipes);
        self::assertFalse($blocked['ready']);
        self::assertSame(7, $blocked['covered']);
        self::assertContains('No sourced recipe lines: calamansi-juice', $blocked['errors']);
    }

    public function testUnknownIngredientsAndConflictingBatchSettingsBlockCatalog(): void
    {
        $first = $this->recipe('chicken-adobo-rice');
        $other = array_replace($first, ['ingredient_name' => 'Unknown ingredient', 'recipe_servings' => 3]);
        $result = NutritionCatalogAudit::assess([$this->ingredient()], [$first, $other]);
        self::assertFalse($result['ready']);
        self::assertContains('Ingredient missing from ingredients.csv: chicken-adobo-rice / Unknown ingredient', $result['errors']);
    }

    public function testInvalidPieceConversionBlocksCatalog(): void
    {
        $first = array_replace($this->recipe('chicken-adobo-rice'), ['unit' => 'pcs']);
        $result = NutritionCatalogAudit::assess([$this->ingredient()], [$first]);
        self::assertFalse($result['ready']);
        self::assertContains('Invalid measured unit conversion: chicken-adobo-rice / Synthetic ingredient', $result['errors']);
    }

    public function testUnknownDensityIsValidForWeighedFoodButBlocksVolumeRecipes(): void
    {
        $dry = array_replace($this->ingredient(), ['density_g_per_ml' => 0.0]);
        $massRecipes = array_map($this->recipe(...), NutritionCatalogAudit::DEMO_PRODUCT_SLUGS);
        $ready = NutritionCatalogAudit::assess([$dry], $massRecipes);
        self::assertTrue($ready['ready']);
        $massRecipes[0]['unit'] = 'ml';
        $blocked = NutritionCatalogAudit::assess([$dry], $massRecipes);
        self::assertFalse($blocked['ready']);
        self::assertContains('Invalid measured unit conversion: chicken-adobo-rice / Synthetic ingredient', $blocked['errors']);
    }

    public function testPartiallyPreparedCatalogMustNotBeSeeded(): void
    {
        // One complete ingredient record does not authorize recording a one-time
        // seed marker while seven of the eight product recipes are still absent.
        $result = NutritionCatalogAudit::assess([$this->ingredient()], [
            $this->recipe('chicken-adobo-rice'),
        ]);
        self::assertFalse($result['ready']);
        self::assertSame(1, $result['covered']);
        self::assertCount(7, $result['errors']);
    }

    public function testCurrentEmptyCatalogDoesNotPretendToHaveNutrition(): void
    {
        $rows = NutritionCatalogAudit::assess([], []);
        self::assertFalse($rows['ready']);
        self::assertSame(0, $rows['covered']);
        self::assertSame(8, count($rows['errors']));
    }
}
