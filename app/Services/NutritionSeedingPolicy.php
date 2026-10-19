<?php

namespace App\Services;

/**
 * An unavailable nutrition catalog must not break otherwise-complete demo
 * setup or consume a one-time seeder_runs marker. A full acceptance run can
 * demand strict completion instead.
 */
final class NutritionSeedingPolicy
{
    private const NUTRITION_SEEDERS = ['IngredientSeeder', 'RecipeSeeder'];

    /**
     * @return 'normal'|'deferred'|'invalid_record'
     */
    public static function disposition(string $seeder, bool $catalogReady, bool $recorded): string
    {
        if (! in_array($seeder, self::NUTRITION_SEEDERS, true)) {
            return 'normal';
        }

        // Never trust an old seeder_runs marker when the nutrition source is
        // missing. Correct previously seeded records with a new migration.
        if ($recorded && ! $catalogReady) {
            return 'invalid_record';
        }

        return $catalogReady ? 'normal' : 'deferred';
    }

    public static function completed(int $missingSeeders, int $deferredNutrition, bool $strict): bool
    {
        return $missingSeeders === 0 && (! $strict || $deferredNutrition === 0);
    }
}
