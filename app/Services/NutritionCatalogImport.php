<?php

namespace App\Services;

/**
 * Offline/online catalog editing boundary. Nutrients come only from an actual
 * USDA record; all other quantities are supplied explicitly by the operator.
 * Writes validated rows to the existing seed CSVs without touching the DB.
 */
final class NutritionCatalogImport
{
    private const DEMO_SUPPLIER = 'Demo price – replace with actual receipts';
    private const ACCEPTED_FDC_TYPES = ['Foundation', 'SR Legacy'];

    /**
     * @param array<string, mixed> $food  Result of NutritionLookupService::details()
     * @param array<string, string> $inputs Operator-supplied cost and measurements
     * @return array<string, mixed> Validated ingredient fields
     */
    public static function ingredientFromUsda(array $food, array $inputs): array
    {
        $id = filter_var($food['fdcId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $type = (string) ($food['dataType'] ?? '');
        if ($id === false || $id === null || ! in_array($type, self::ACCEPTED_FDC_TYPES, true)) {
            throw new \DomainException('Select an actual USDA Foundation or SR Legacy record with a valid FDC ID.');
        }
        if (trim((string) ($food['description'] ?? '')) === '') {
            throw new \DomainException('The USDA record has no food description; check the FDC ID.');
        }

        // Foundation and SR Legacy report nutrients per 100 g. Branded foods
        // can use serving-based values, so they are intentionally rejected.
        // Never substitute zeros for omitted FDC nutrients.
        $nutrients = NutritionLookupService::nutrients($food);
        $raw = array_merge([
            'name' => trim((string) ($inputs['name'] ?? '')),
            'purchase_unit' => (string) ($inputs['purchase_unit'] ?? ''),
            'cost_per_unit' => (string) ($inputs['cost_per_unit'] ?? ''),
            'supplier' => self::DEMO_SUPPLIER,
            'nutrient_basis' => '100g',
            'density_g_per_ml' => (string) ($inputs['density_g_per_ml'] ?? ''),
            'grams_per_piece' => (string) ($inputs['grams_per_piece'] ?? ''),
            'reorder_level' => (string) ($inputs['reorder_level'] ?? ''),
            'opening_stock' => (string) ($inputs['opening_stock'] ?? ''),
            'source_reference' => 'USDA FDC #' . $id . ' (' . $type . ')',
        ], $nutrients);

        return SourcedRecipeCatalog::ingredient($raw);
    }

    /** @param array<string, mixed> $food @param array<string, string> $inputs */
    public static function addIngredient(string $path, array $food, array $inputs): void
    {
        $ingredient = self::ingredientFromUsda($food, $inputs);
        self::append($path, SourcedRecipeCatalog::INGREDIENT_COLUMNS, $ingredient,
            static function (array $existing) use ($ingredient): void {
                foreach ($existing as $row) {
                    if (self::key($row['name']) === self::key($ingredient['name'])) {
                        throw new \DomainException('Ingredient already exists in source CSV: ' . $ingredient['name']);
                    }
                }
            });
    }

    /** @param array<string, string> $inputs */
    public static function addRecipe(string $ingredientsPath, string $recipesPath, array $inputs): void
    {
        $line = SourcedRecipeCatalog::recipe($inputs);
        if (! in_array($line['product_slug'], NutritionCatalogAudit::DEMO_PRODUCT_SLUGS, true)) {
            throw new \DomainException('Recipe slug is not one of the eight demo products.');
        }
        $ingredients = SourcedRecipeCatalog::read($ingredientsPath, SourcedRecipeCatalog::INGREDIENT_COLUMNS);
        $found = false;
        foreach ($ingredients as $record) {
            // Validate even source rows added outside the importer before using them.
            $record = SourcedRecipeCatalog::ingredient($record);
            if (self::key($record['name']) === self::key($line['ingredient_name'])) {
                $found = true;
                CostingService::ingredientUnits((float) $line['quantity'], $line['unit'],
                    $record['purchase_unit'], (float) $record['density_g_per_ml'], $record['grams_per_piece']);
                if ($record['nutrient_basis'] === '100g') {
                    CostingService::nutrientQuantity((float) $line['quantity'], (string) $line['unit'],
                        (float) $record['density_g_per_ml'], $record['grams_per_piece']);
                }
                break;
            }
        }
        if (! $found) {
            throw new \DomainException('Import the sourced ingredient before adding its recipe line: ' . $line['ingredient_name']);
        }

        self::append($recipesPath, SourcedRecipeCatalog::RECIPE_COLUMNS, $line,
            static function (array $existing) use ($line): void {
                foreach ($existing as $current) {
                    $current = SourcedRecipeCatalog::recipe($current);
                    if ($current['product_slug'] !== $line['product_slug']) continue;
                    if (self::key($current['ingredient_name']) === self::key($line['ingredient_name'])) {
                        throw new \DomainException('Recipe line already exists; edit the CSV only after team review.');
                    }
                    foreach (['recipe_servings', 'cooking_yield', 'packaging_cost'] as $field) {
                        if ($current[$field] !== $line[$field]) {
                            throw new \DomainException('Recipe batch ' . $field . ' must match other lines for this product.');
                        }
                    }
                }
            });
    }

    private static function key(string $value): string
    {
        $value = trim($value);
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    /**
     * Serializes concurrent imports using a stable sidecar lock and an atomic
     * rename. Existing CSV content is retained, and failures do not truncate it.
     *
     * @param list<string> $columns
     * @param array<string,mixed> $newRow
     * @param callable(list<array<string,string>>):void $validate
     */
    private static function append(string $path, array $columns, array $newRow, callable $validate): void
    {
        if (! is_file($path) || ! is_writable($path)) {
            throw new \RuntimeException('Catalog CSV is missing or not writable: ' . basename($path));
        }
        // Coordinate individual CSV import commands with `php spark populate`,
        // which commits ingredients, recipes, and the approval worksheet as a unit.
        $globalLock = fopen(dirname($path) . '/.populate.lock', 'c');
        if ($globalLock === false) throw new \RuntimeException('Cannot create shared nutrition catalog lock.');
        try {
            if (! flock($globalLock, LOCK_EX)) throw new \RuntimeException('Cannot lock shared nutrition catalog.');
            $lock = fopen($path . '.lock', 'c');
            if ($lock === false) throw new \RuntimeException('Cannot create catalog import lock.');
            try {
                if (! flock($lock, LOCK_EX)) throw new \RuntimeException('Cannot lock catalog CSV.');
            $rows = SourcedRecipeCatalog::read($path, $columns);
            $validate($rows);
            $rows[] = array_combine($columns, array_map(
                static fn (string $column): string => (string) ($newRow[$column] ?? ''), $columns));
            $temp = tempnam(dirname($path), '.nutrition-');
            if ($temp === false) throw new \RuntimeException('Cannot create temporary source CSV.');
            try {
                $out = fopen($temp, 'wb');
                if ($out === false) throw new \RuntimeException('Cannot write temporary source CSV.');
                try {
                    if (fputcsv($out, $columns, ',', '"', '') === false) throw new \RuntimeException('CSV header write failed.');
                    foreach ($rows as $row) {
                        $values = array_map(static fn (string $column): string => (string) ($row[$column] ?? ''), $columns);
                        if (fputcsv($out, $values, ',', '"', '') === false) throw new \RuntimeException('CSV row write failed.');
                    }
                    if (! fflush($out)) throw new \RuntimeException('CSV flush failed.');
                } finally {
                    fclose($out);
                }
                // Preserve the original file's access mode when the temporary file replaces it.
                chmod($temp, fileperms($path) & 0777);
                if (! rename($temp, $path)) throw new \RuntimeException('CSV replacement failed.');
            } finally {
                if (is_file($temp)) unlink($temp);
            }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        } finally {
            flock($globalLock, LOCK_UN);
            fclose($globalLock);
        }
    }
}
