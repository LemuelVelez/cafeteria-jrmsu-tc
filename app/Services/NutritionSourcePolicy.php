<?php

namespace App\Services;

final class NutritionSourcePolicy
{
    public const SOURCES = [
        'label_google_lens' => 'Product nutrition label (scanned with Google Lens)',
        'manufacturer_label' => 'Manufacturer nutrition label / official product page',
        'fnri_philfct' => 'FNRI Philippine Food Composition Tables (PhilFCT)',
        'usda_fdc' => 'USDA FoodData Central',
        'lab_analysis' => 'Laboratory analysis report',
    ];

    public const REQUIRED_FIELDS = ['serving_size', 'calories', 'protein_g', 'carbohydrates_g', 'fat_g', 'sodium_mg'];

    public static function label(?string $source): string
    {
        return self::SOURCES[$source ?? ''] ?? 'Not recorded';
    }

    public static function isVerified(array $product): bool
    {
        return ($product['nutrition_status'] ?? '') === 'verified';
    }
}
