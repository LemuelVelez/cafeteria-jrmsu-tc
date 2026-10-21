<?php

namespace Tests\Unit;

use App\Database\Seeds\DatabaseSeeder;
use App\Database\Seeds\DemoRecipePreviewSeeder;
use App\Services\DemoProductNutritionCatalog;
use PHPUnit\Framework\TestCase;

final class DemoRecipePreviewSeederTest extends TestCase
{
    public function testAllEightCoreProductsHaveCompleteIllustrativeNutritionAndAllergenExamples(): void
    {
        self::assertSameCanonicalizing(
            array_values(DemoProductNutritionCatalog::SEEDERS),
            array_keys(DemoRecipePreviewSeeder::EXAMPLES),
        );
        $fields = ['calories', 'protein_g', 'carbohydrates_g', 'fat_g',
            'sugar_g', 'fiber_g', 'sodium_mg'];
        foreach (DemoRecipePreviewSeeder::EXAMPLES as $slug => $example) {
            self::assertSameCanonicalizing($fields, array_keys($example['nutrition']), $slug);
            self::assertNotEmpty($example['allergens'], $slug);
            $numbers = $example['nutrition'];
            foreach ($numbers as $value) {
                self::assertGreaterThanOrEqual(0, $value);
            }
            self::assertLessThanOrEqual($numbers['carbohydrates_g'], $numbers['sugar_g']);
            self::assertLessThanOrEqual($numbers['carbohydrates_g'], $numbers['fiber_g']);
            self::assertLessThanOrEqual(65535, $numbers['calories']);
            self::assertLessThanOrEqual(65535, $numbers['sodium_mg']);
        }
    }

    public function testDemoRecipesFollowApprovedRecipesAndRandomSeederRemainsExplicitOnly(): void
    {
        self::assertContains('DemoRecipePreviewSeeder', DatabaseSeeder::seeders());
        self::assertNotContains('DemoRecipePreviewSeeder', DatabaseSeeder::EXPLICIT_ONLY_SEEDERS);
        self::assertGreaterThan(
            array_search('CalamansiJuiceSeeder', DatabaseSeeder::seeders(), true),
            array_search('DemoRecipePreviewSeeder', DatabaseSeeder::seeders(), true),
        );
        self::assertContains('RandomNutritionSeeder', DatabaseSeeder::EXPLICIT_ONLY_SEEDERS);
        self::assertNotContains('RandomNutritionSeeder', DatabaseSeeder::seeders());
    }
}
