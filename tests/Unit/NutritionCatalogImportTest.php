<?php

namespace Tests\Unit;

use App\Services\NutritionCatalogImport;
use App\Services\SourcedRecipeCatalog;
use PHPUnit\Framework\TestCase;

/** Entirely synthetic fixtures: no example values here are real nutrition evidence. */
final class NutritionCatalogImportTest extends TestCase
{
    private string $folder;
    private string $ingredients;
    private string $recipes;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/nutrition-test-' . bin2hex(random_bytes(8));
        mkdir($this->folder);
        $this->ingredients = $this->folder . '/ingredients.csv';
        $this->recipes = $this->folder . '/recipes.csv';
        foreach ([$this->ingredients => SourcedRecipeCatalog::INGREDIENT_COLUMNS,
            $this->recipes => SourcedRecipeCatalog::RECIPE_COLUMNS] as $path => $columns) {
            file_put_contents($path, implode(',', $columns) . "\n");
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . '/*') ?: [] as $file) @unlink($file);
        @rmdir($this->folder);
    }

    private function food(): array
    {
        $ids = [1008 => 200, 1003 => 4, 1005 => 20, 1004 => 3, 2000 => 1,
            1079 => 2, 1093 => 30];
        $rows = [];
        foreach ($ids as $id => $amount) $rows[] = ['nutrient' => ['id' => $id], 'amount' => $amount];
        return ['fdcId' => 123456, 'description' => 'Synthetic test ingredient',
            'dataType' => 'Foundation', 'foodNutrients' => $rows];
    }

    private function inputs(): array
    {
        return ['name' => 'Test ingredient', 'purchase_unit' => 'g',
            'cost_per_unit' => '0.5', 'density_g_per_ml' => '1.3',
            'grams_per_piece' => '', 'reorder_level' => '20', 'opening_stock' => '100'];
    }

    private function recipe(string $slug = 'chicken-adobo-rice'): array
    {
        return ['product_slug' => $slug, 'ingredient_name' => 'Test ingredient',
            'quantity' => '75', 'unit' => 'g', 'wastage_percent' => '2',
            'recipe_servings' => '2', 'cooking_yield' => '0.8', 'packaging_cost' => '5'];
    }

    public function testActualFdcPayloadFieldsAreCopiedAndAnExplicitSourceIsPreserved(): void
    {
        NutritionCatalogImport::addIngredient($this->ingredients, $this->food(), $this->inputs());
        $rows = SourcedRecipeCatalog::read($this->ingredients, SourcedRecipeCatalog::INGREDIENT_COLUMNS);
        self::assertCount(1, $rows);
        self::assertSame('USDA FDC #123456 (Foundation)', $rows[0]['source_reference']);
        self::assertSame('200', $rows[0]['calories']);
        self::assertSame('30', $rows[0]['sodium_mg']);
        self::assertSame('Demo price – replace with actual receipts', $rows[0]['supplier']);
        self::assertSame('100g', $rows[0]['nutrient_basis']);
    }

    public function testRejectsBrandedProductsMissingNutrientsAndMissingHumanMeasurements(): void
    {
        $cases = [
            [array_replace($this->food(), ['dataType' => 'Branded']), $this->inputs()],
            [array_replace($this->food(), ['foodNutrients' => []]), $this->inputs()],
            [$this->food(), array_replace($this->inputs(), ['cost_per_unit' => ''])],
            [$this->food(), array_replace($this->inputs(), ['density_g_per_ml' => ''])],
            [$this->food(), array_replace($this->inputs(), ['purchase_unit' => 'pcs'])],
        ];
        foreach ($cases as [$food, $inputs]) {
            try {
                NutritionCatalogImport::addIngredient($this->ingredients, $food, $inputs);
                self::fail('Invalid import unexpectedly succeeded.');
            } catch (\DomainException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }
        }
        self::assertSame([], SourcedRecipeCatalog::read($this->ingredients, SourcedRecipeCatalog::INGREDIENT_COLUMNS));
    }

    public function testDuplicateImportDoesNotAlterOriginalCsv(): void
    {
        NutritionCatalogImport::addIngredient($this->ingredients, $this->food(), $this->inputs());
        $original = file_get_contents($this->ingredients);
        $this->expectException(\DomainException::class);
        try {
            NutritionCatalogImport::addIngredient($this->ingredients, $this->food(), $this->inputs());
        } finally {
            self::assertSame($original, file_get_contents($this->ingredients));
        }
    }

    public function testRecipeRequiresAnImportedIngredientAndConsistentBatchSettings(): void
    {
        $this->expectException(\DomainException::class);
        NutritionCatalogImport::addRecipe($this->ingredients, $this->recipes, $this->recipe());
    }

    public function testDuplicateRecipeLineIsNotAppended(): void
    {
        NutritionCatalogImport::addIngredient($this->ingredients, $this->food(), $this->inputs());
        NutritionCatalogImport::addRecipe($this->ingredients, $this->recipes, $this->recipe());
        self::assertCount(1, SourcedRecipeCatalog::read($this->recipes, SourcedRecipeCatalog::RECIPE_COLUMNS));
        $this->expectException(\DomainException::class);
        NutritionCatalogImport::addRecipe($this->ingredients, $this->recipes, $this->recipe());
    }

    public function testRecipeBatchConflictCannotChangeCsvAndAllEightCanBeEntered(): void
    {
        NutritionCatalogImport::addIngredient($this->ingredients, $this->food(), $this->inputs());
        foreach (\App\Services\NutritionCatalogAudit::DEMO_PRODUCT_SLUGS as $slug) {
            NutritionCatalogImport::addRecipe($this->ingredients, $this->recipes, $this->recipe($slug));
        }
        $secondFood = array_replace($this->food(), ['fdcId' => 123457]);
        $secondInput = array_replace($this->inputs(), ['name' => 'Test ingredient 2']);
        NutritionCatalogImport::addIngredient($this->ingredients, $secondFood, $secondInput);
        $prior = file_get_contents($this->recipes);
        $conflict = array_replace($this->recipe(), [
            'ingredient_name' => 'Test ingredient 2', 'recipe_servings' => '3',
        ]);
        try {
            NutritionCatalogImport::addRecipe($this->ingredients, $this->recipes, $conflict);
            self::fail('Inconsistent servings should have been rejected.');
        } catch (\DomainException $expected) {
            self::assertSame($prior, file_get_contents($this->recipes));
        }
        self::assertCount(8, SourcedRecipeCatalog::read($this->recipes, SourcedRecipeCatalog::RECIPE_COLUMNS));
    }
}
