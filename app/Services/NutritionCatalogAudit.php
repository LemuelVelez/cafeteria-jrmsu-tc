<?php

namespace App\Services;

/**
 * Checks sourced CSV coverage before the registered seeders are run.
 * It does not certify that the cited food ID actually matches the food:
 * that requires the team's source verification and human nutrition sign-off.
 */
final class NutritionCatalogAudit
{
    public const DEMO_PRODUCT_SLUGS = [
        'chicken-adobo-rice', 'pork-sisig-rice', 'fried-chicken-meal',
        'cheese-burger', 'crispy-fries', 'iced-spanish-latte',
        'hot-americano', 'calamansi-juice',
    ];

    private static function key(string $name): string
    {
        $name = trim($name);
        return function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    }

    /**
     * @param list<array<string, mixed>> $ingredients validated IngredientSeeder rows
     * @param list<array<string, mixed>> $recipes validated RecipeSeeder rows
     * @return array{ready: bool, errors: list<string>, covered: int, required: int}
     */
    public static function assess(array $ingredients, array $recipes): array
    {
        $issues = [];
        $byName = [];
        foreach ($ingredients as $ingredient) {
            $name = self::key((string) $ingredient['name']);
            if (isset($byName[$name])) {
                $issues[] = 'Duplicate ingredient: ' . $ingredient['name'];
            }
            $byName[$name] = $ingredient;
        }
        $grouped = [];
        foreach ($recipes as $line) {
            $slug = (string) $line['product_slug'];
            $name = self::key((string) $line['ingredient_name']);
            if (! in_array($slug, self::DEMO_PRODUCT_SLUGS, true)) {
                $issues[] = 'Unknown demo product in recipes.csv: ' . $slug;
                continue;
            }
            if (isset($grouped[$slug][$name])) {
                $issues[] = 'Duplicate recipe line: ' . $slug . ' / ' . $name;
            }
            $grouped[$slug][$name] = $line;
        }

        $covered = 0;
        foreach (self::DEMO_PRODUCT_SLUGS as $slug) {
            if (empty($grouped[$slug])) {
                $issues[] = 'No sourced recipe lines: ' . $slug;
                continue;
            }
            $complete = true;
            $first = reset($grouped[$slug]);
            foreach ($grouped[$slug] as $name => $line) {
                if (! isset($byName[$name])) {
                    $issues[] = 'Ingredient missing from ingredients.csv: ' . $slug . ' / ' . $line['ingredient_name'];
                    $complete = false;
                    continue;
                }
                foreach (['recipe_servings', 'cooking_yield', 'packaging_cost'] as $field) {
                    if ($line[$field] !== $first[$field]) {
                        $issues[] = 'Inconsistent ' . $field . ': ' . $slug;
                        $complete = false;
                    }
                }
                $ingredient = $byName[$name];
                try {
                    CostingService::ingredientUnits((float) $line['quantity'], (string) $line['unit'],
                        (string) $ingredient['purchase_unit'], (float) $ingredient['density_g_per_ml'],
                        $ingredient['grams_per_piece'] === null ? null : (float) $ingredient['grams_per_piece']);
                    if ($ingredient['nutrient_basis'] === '100g') {
                        CostingService::nutrientQuantity((float) $line['quantity'], (string) $line['unit'],
                            (float) $ingredient['density_g_per_ml'],
                            $ingredient['grams_per_piece'] === null ? null : (float) $ingredient['grams_per_piece']);
                    }
                } catch (\DomainException $error) {
                    $issues[] = 'Invalid measured unit conversion: ' . $slug . ' / ' . $line['ingredient_name'];
                    $complete = false;
                }
            }
            if ($complete) $covered++;
        }
        $issues = array_values(array_unique($issues));
        return ['ready' => $issues === [] && $covered === count(self::DEMO_PRODUCT_SLUGS),
            'errors' => $issues, 'covered' => $covered,
            'required' => count(self::DEMO_PRODUCT_SLUGS)];
    }
}
