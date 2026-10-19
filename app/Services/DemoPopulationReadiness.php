<?php

namespace App\Services;

/** Pure, stable checks for the non-interactive demo-population command. */
final class DemoPopulationReadiness
{
    /**
     * Nutrition is separately approved and can be deferred. Core seeders are
     * considered applied when registered in seeder_runs, matching SeedAll.
     *
     * @param list<string> $registered
     * @return list<string>
     */
    public static function coreSeeders(array $registered): array
    {
        return array_values(array_filter($registered, static fn(string $name): bool =>
            ! in_array($name, ['IngredientSeeder', 'RecipeSeeder'], true)
            && DemoProductNutritionCatalog::slugForSeeder($name) === null
        ));
    }

    /**
     * @param list<string> $registered Application seeders in dependency order
     * @param list<string> $recorded Seeder names from seeder_runs
     * @param list<string> $productSlugs Existing product slugs
     */
    public static function isReady(array $registered, array $recorded, array $productSlugs): bool
    {
        $core = self::coreSeeders($registered);
        return array_diff($core, $recorded) === []
            && array_diff(array_values(DemoProductNutritionCatalog::SEEDERS), $productSlugs) === [];
    }
}
