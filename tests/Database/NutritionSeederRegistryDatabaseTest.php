<?php

namespace Tests\Database;

use App\Database\Seeds\DatabaseSeeder;
use App\Database\Seeds\IngredientSeeder;
use App\Database\Seeds\RecipeSeeder;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

/** Empty source catalogs must never count as completed data migrations. */
#[Group('database')]
final class NutritionSeederRegistryDatabaseTest extends DatabaseTestCase
{
    public function testSeederReadinessReflectsActualSourcedRowsAfterFreshSeed(): void
    {
        $db = db_connect('tests');
        self::assertSame(IngredientSeeder::rows() !== [],
            DatabaseSeeder::isSatisfied('IngredientSeeder', $db));
        self::assertSame(RecipeSeeder::rows() !== [],
            DatabaseSeeder::isSatisfied('RecipeSeeder', $db));
    }
}
