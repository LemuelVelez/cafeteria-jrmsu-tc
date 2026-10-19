<?php

namespace App\Services;

/**
 * A non-seeding, traceable desk-reference library. These are candidates for
 * review, not the restaurant's actual food specifications or signed-off recipes.
 * Never convert this file to ingredients.csv with guessed stock or cost data.
 */
final class NutritionReferenceCatalog
{
    public const FIELDS = [
        'candidate_name', 'fdc_id', 'fdc_data_type', 'usda_description',
        'calories', 'protein_g', 'carbohydrates_g', 'fat_g', 'sugar_g',
        'fiber_g', 'sodium_mg', 'source_reference', 'source_page', 'usda_detail_url',
    ];
    public const WORKSHEET_FIELDS = [
        'product_slug', 'candidate_ingredient', 'fdc_id', 'confirmed_ingredient',
        'measured_quantity', 'measured_unit', 'wastage_percent', 'recipe_servings',
        'cooking_yield', 'packaging_cost', 'kitchen_approved_by', 'kitchen_approved_on',
    ];

    /** @return array{references:int, products:int, measured_products:int, missing:list<string>} */
    public static function inspect(string $sources, string $worksheet): array
    {
        $references = self::read($sources, self::FIELDS);
        $recipes = self::read($worksheet, self::WORKSHEET_FIELDS);
        $ids = [];
        $names = [];
        foreach ($references as $row) {
            $id = $row['fdc_id'];
            $name = self::key($row['candidate_name']);
            if (! ctype_digit($id) || (int) $id < 1 || isset($ids[$id]) || isset($names[$name])
                || $row['fdc_data_type'] !== 'SR Legacy'
                || $row['source_reference'] !== 'USDA FDC #' . $id . ' (SR Legacy)'
                || ! str_starts_with($row['source_page'], 'https://getfoodfacts.com/food/')
                || ! str_ends_with($row['source_page'], '-' . $id)
                || $row['usda_detail_url'] !== 'https://fdc.nal.usda.gov/food-details/' . $id . '/nutrients') {
                throw new \DomainException('Invalid or duplicate reference: ' . $id);
            }
            foreach (['calories', 'protein_g', 'carbohydrates_g', 'fat_g', 'sugar_g', 'fiber_g', 'sodium_mg'] as $field) {
                if (! is_numeric($row[$field]) || ! is_finite((float) $row[$field]) || (float) $row[$field] < 0) {
                    throw new \DomainException('Unsourced or invalid value for ' . $row['candidate_name'] . ': ' . $field);
                }
            }
            $ids[$id] = true;
            $names[$name] = $id;
        }
        $byProduct = [];
        foreach ($recipes as $row) {
            $slug = $row['product_slug'];
            if (! in_array($slug, NutritionCatalogAudit::DEMO_PRODUCT_SLUGS, true)) {
                throw new \DomainException('Unknown product in worksheet: ' . $slug);
            }
            if ($row['fdc_id'] !== '' && (! isset($ids[$row['fdc_id']])
                || ($names[self::key($row['candidate_ingredient'])] ?? null) !== $row['fdc_id'])) {
                throw new \DomainException('Unmatched source candidate in ' . $slug);
            }
            $byProduct[$slug][] = $row;
        }
        $missing = [];
        $measured = 0;
        foreach (NutritionCatalogAudit::DEMO_PRODUCT_SLUGS as $slug) {
            $lines = $byProduct[$slug] ?? [];
            $complete = $lines !== [];
            foreach ($lines as $row) {
                foreach (['confirmed_ingredient', 'measured_quantity', 'measured_unit', 'wastage_percent',
                    'recipe_servings', 'cooking_yield', 'packaging_cost', 'kitchen_approved_by', 'kitchen_approved_on'] as $required) {
                    if ($row[$required] === '') {
                        $complete = false;
                    }
                }
                if ($row['fdc_id'] === '') $complete = false;
            }
            if ($complete) $measured++;
            else $missing[] = $slug;
        }
        return ['references' => count($references), 'products' => count(NutritionCatalogAudit::DEMO_PRODUCT_SLUGS),
            'measured_products' => $measured, 'missing' => $missing];
    }

    private static function key(string $value): string
    {
        $value = trim($value);
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    /** @param list<string> $columns @return list<array<string,string>> */
    private static function read(string $path, array $columns): array
    {
        return SourcedRecipeCatalog::read($path, $columns);
    }
}
