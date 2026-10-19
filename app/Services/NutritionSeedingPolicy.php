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
     * @return 'normal'|'deferred'|'legacy_deferred'
     */
    public static function disposition(string $seeder, bool $catalogReady, bool $recorded): string
    {
        if (! in_array($seeder, self::NUTRITION_SEEDERS, true)) {
            return 'normal';
        }

        // Legacy installations may have recorded a placeholder nutrition
        // seeder before the catalog was available. Preserve its registry row
        // but do not treat that marker as proof of sourced nutrition data.
        // The original seeder must not be replayed; correct that existing
        // installation with an append-only data migration when sources arrive.
        if (! $catalogReady) {
            return $recorded ? 'legacy_deferred' : 'deferred';
        }

        return 'normal';
    }

    public static function completed(int $missingSeeders, int $deferredNutrition, bool $strict): bool
    {
        return $missingSeeders === 0 && (! $strict || $deferredNutrition === 0);
    }
}
