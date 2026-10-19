<?php

namespace App\Services;

/**
 * Protects human nutrition sign-off from stale product-form / API edits.
 * Recipe-derived nutrition is changed through CostingService only.
 */
final class NutritionEditPolicy
{
    public const FIELDS = [
        'serving_size', 'calories', 'protein_g', 'carbohydrates_g',
        'fat_g', 'sugar_g', 'fiber_g', 'sodium_mg',
    ];

    /**
     * @param array<string, mixed>|null $existing Current DB row (null on creation)
     * @param array<string, mixed> $data Prepared save payload
     * @return array<string, mixed> Payload with invalidated verification when needed
     */
    public static function apply(?array $existing, array $data, bool $hasRecipe): array
    {
        if ($existing === null) {
            // Database defaults on creation are 'estimated' and 'manual'.
            return $data;
        }

        $changed = [];
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $previous = $existing[$field] ?? null;
            $next = $data[$field];
            if (! self::same($previous, $next, $field)) {
                $changed[] = $field;
            }
        }
        if ($changed === []) {
            return $data;
        }
        if ($hasRecipe) {
            throw new \DomainException('This product has a recipe. Edit its ingredients in Food Costing to recalculate nutrition.');
        }

        // Direct manual edits must never retain a previous verifier or claim
        // that entered numbers were calculated from the recorded recipe sources.
        $data['nutrition_status'] = 'estimated';
        $data['nutrition_source'] = 'manual';
        $data['nutrition_verified_by'] = null;
        $data['nutrition_verified_at'] = null;
        return $data;
    }

    private static function same(mixed $before, mixed $after, string $field): bool
    {
        $before = $before === null || trim((string) $before) === '' ? null : trim((string) $before);
        $after = $after === null || trim((string) $after) === '' ? null : trim((string) $after);
        if ($before === null || $after === null) {
            return $before === $after;
        }
        if ($field === 'serving_size') {
            return $before === $after;
        }
        // CI4/MySQL commonly return DECIMALs with a fixed scale. Do not
        // revoke verification solely because '12.50' is submitted as '12.5'.
        return is_numeric($before) && is_numeric($after) && (float) $before === (float) $after;
    }
}
