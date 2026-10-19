<?php

namespace App\Services;

/**
 * Interactive, source-verified CSV writer for individual cafeteria recipes.
 * Does not touch the database, invent kitchen quantities, or overwrite recipes.
 */
final class NutritionPopulateService
{
    private const FILES = [
        'ingredients' => 'ingredients.csv',
        'recipes' => 'recipes.csv',
        'worksheet' => 'recipe_measurement_workbook.csv',
        'references' => 'usda_reference_candidates.csv',
    ];

    /**
     * @param callable(string):string $ask Reads a real operator response (no default approvals).
     * @param callable(int):array<string,mixed> $lookup Reads a USDA FDC details record.
     * @return string saved|skipped|already_complete
     */
    public static function populate(string $folder, string $slug, callable $ask, callable $lookup): string
    {
        if (! in_array($slug, NutritionCatalogAudit::DEMO_PRODUCT_SLUGS, true)) {
            throw new \DomainException('Unknown cafeteria product: ' . $slug);
        }
        $paths = [];
        foreach (self::FILES as $key => $filename) {
            $paths[$key] = rtrim($folder, '/\\') . DIRECTORY_SEPARATOR . $filename;
        }
        $lock = fopen(rtrim($folder, '/\\') . DIRECTORY_SEPARATOR . '.populate.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Could not acquire the CSV catalog writer lock.');
        }
        try {
            $ingredients = SourcedRecipeCatalog::read($paths['ingredients'], SourcedRecipeCatalog::INGREDIENT_COLUMNS);
            $recipes = SourcedRecipeCatalog::read($paths['recipes'], SourcedRecipeCatalog::RECIPE_COLUMNS);
            $worksheet = SourcedRecipeCatalog::read($paths['worksheet'], NutritionReferenceCatalog::WORKSHEET_FIELDS);
            $references = SourcedRecipeCatalog::read($paths['references'], NutritionReferenceCatalog::FIELDS);
            $status = DemoProductNutritionCatalog::assess($slug, $ingredients, $recipes, $worksheet);
            if ($status['ready']) {
                return 'already_complete';
            }
            if ($status['lines'] !== []) {
                throw new \DomainException('Existing incomplete recipe for ' . $slug .
                    ' must be reviewed manually. No file was changed.');
            }
            $suggested = array_values(array_filter($worksheet,
                static fn (array $r): bool => $r['product_slug'] === $slug));
            if ($suggested === []) {
                throw new \DomainException('The measurement worksheet has no candidate rows for ' . $slug);
            }
            if (! self::affirm($ask('Populate ' . $slug . '? Type YES to begin (otherwise skip): '))) {
                return 'skipped';
            }
            $sourceById = [];
            foreach ($references as $row) {
                $sourceById[$row['fdc_id']] = $row;
            }
            $byName = [];
            foreach ($ingredients as $row) {
                $byName[self::key($row['name'])] = $row;
            }
            $servings = self::value($ask, 'Measured batch servings (positive whole number): ');
            $yield = self::value($ask, 'Measured cooking yield ratio (e.g. 0.85): ');
            $packaging = self::value($ask, 'Packaging cost for the recipe batch (PHP): ');
            $signer = self::value($ask, 'Actual kitchen approver full name: ');
            $date = self::value($ask, 'Actual approval date (YYYY-MM-DD): ');
            $approved = [];
            $newRecipe = [];
            $newIngredients = [];
            $usedNames = [];
            foreach ($suggested as $suggestion) {
                $name = $suggestion['candidate_ingredient'];
                $id = $suggestion['fdc_id'];
                if (! self::affirm($ask('Is "' . $name . '" actually used in this product? Type YES or skip: '))) {
                    continue;
                }
                if (! ctype_digit($id) || ! isset($sourceById[$id])
                    || $sourceById[$id]['candidate_name'] !== $name) {
                    throw new \DomainException('No USDA candidate source for "' . $name .
                        '". Find and confirm its source before including it. No file was changed.');
                }
                $source = $sourceById[$id];
                $food = $lookup((int) $id);
                if ((int) ($food['fdcId'] ?? 0) !== (int) $id
                    || ($food['dataType'] ?? '') !== $source['fdc_data_type']) {
                    throw new \DomainException('USDA returned an unexpected source ID or data type: ' . $id);
                }
                // Live/cache USDA values are the sole source of nutrients; never
                // transfer unchecked numbers from the candidate worksheet.
                if (! self::affirm($ask('USDA FDC #' . $id . ' "' . (string) ($food['description'] ?? '') .
                    '" matches the food used by your kitchen? Type YES: '))) {
                    throw new \DomainException('Ingredient source was not approved; no files changed.');
                }
                $confirmed = self::value($ask, 'Confirmed ingredient name (match "' . $name . '", or enter your exact name): ');
                $key = self::key($confirmed);
                if (isset($usedNames[$key])) {
                    throw new \DomainException('Duplicate ingredient in product: ' . $confirmed);
                }
                $usedNames[$key] = true;
                $quantity = self::value($ask, 'Measured ingredient quantity: ');
                $unit = self::value($ask, 'Measured ingredient unit (g/kg/ml/l/pcs): ');
                $wastage = self::value($ask, 'Measured wastage percent (enter 0 only if confirmed): ');
                $existing = $byName[$key] ?? $newIngredients[$key] ?? null;
                if ($existing !== null) {
                    if ($existing['source_reference'] !== $source['source_reference']) {
                        throw new \DomainException('Ingredient already has another source: ' . $confirmed);
                    }
                } else {
                    $purchaseUnit = self::value($ask, 'Purchase unit (g/kg/ml/l/pcs): ');
                    $cost = self::value($ask, 'Actual purchase cost per purchase unit (PHP): ');
                    $reorder = self::value($ask, 'Measured reorder threshold in purchase units: ');
                    $opening = self::value($ask, 'Actual opening stock in purchase units: ');
                    $density = trim($ask('Measured density in g/ml if used in ml/l (blank if unknown): '));
                    $grams = trim($ask('Measured grams per piece if using pcs (blank if unknown): '));
                    $newIngredients[$key] = NutritionCatalogImport::ingredientFromUsda($food, [
                        'name' => $confirmed, 'purchase_unit' => $purchaseUnit,
                        'cost_per_unit' => $cost, 'reorder_level' => $reorder,
                        'opening_stock' => $opening, 'density_g_per_ml' => $density,
                        'grams_per_piece' => $grams,
                    ]);
                }
                $line = [
                    'product_slug' => $slug, 'ingredient_name' => $confirmed,
                    'quantity' => $quantity, 'unit' => $unit,
                    'wastage_percent' => $wastage, 'recipe_servings' => $servings,
                    'cooking_yield' => $yield, 'packaging_cost' => $packaging,
                ];
                SourcedRecipeCatalog::recipe($line);
                $newRecipe[] = $line;
                $approved[] = [
                    'product_slug' => $slug, 'candidate_ingredient' => $name,
                    'fdc_id' => $id, 'confirmed_ingredient' => $confirmed,
                    'measured_quantity' => $quantity, 'measured_unit' => $unit,
                    'wastage_percent' => $wastage, 'recipe_servings' => $servings,
                    'cooking_yield' => $yield, 'packaging_cost' => $packaging,
                    'kitchen_approved_by' => $signer, 'kitchen_approved_on' => $date,
                ];
            }
            if ($newRecipe === []) {
                throw new \DomainException('No actual ingredient selected; all CSVs were left unchanged.');
            }
            $allIngredients = array_merge($ingredients, array_values($newIngredients));
            $allRecipes = array_merge($recipes, $newRecipe);
            $allWorksheet = array_merge(array_values(array_filter($worksheet,
                static fn (array $r): bool => $r['product_slug'] !== $slug)), $approved);
            foreach ($allIngredients as $item) {
                SourcedRecipeCatalog::ingredient($item);
            }
            foreach ($allRecipes as $line) {
                SourcedRecipeCatalog::recipe($line);
            }
            $check = DemoProductNutritionCatalog::assess($slug, $allIngredients, $allRecipes, $allWorksheet);
            if (! $check['ready']) {
                throw new \DomainException('Approved recipe did not pass validation: ' .
                    implode('; ', $check['errors']) . '. No CSV changed.');
            }
            // Ask only after complete validation, so incomplete or unapproved
            // measurements can never be silently written to the seed files.
            if (! self::affirm($ask('Confirm ALL actual recipe ingredients have been included ' .
                '(including water, seasonings, sauces and frying oil where applicable). Type YES: '))) {
                return 'skipped';
            }
            if (! self::affirm($ask('Write the 3 CSV files for ' . $slug . '? Type YES to confirm: '))) {
                return 'skipped';
            }
            self::writeAll([
                $paths['ingredients'] => [SourcedRecipeCatalog::INGREDIENT_COLUMNS, $allIngredients],
                $paths['recipes'] => [SourcedRecipeCatalog::RECIPE_COLUMNS, $allRecipes],
                $paths['worksheet'] => [NutritionReferenceCatalog::WORKSHEET_FIELDS, $allWorksheet],
            ]);
            return 'saved';
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function key(string $text): string
    {
        $text = trim($text);
        return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    }

    private static function affirm(string $answer): bool
    {
        return strtoupper(trim($answer)) === 'YES';
    }

    private static function value(callable $ask, string $question): string
    {
        $answer = trim($ask($question));
        if ($answer === '') {
            throw new \DomainException('A required measured or confirmed value was not entered: ' . $question);
        }
        return $answer;
    }

    /**
     * Atomically replace each file and restore previously replaced CSVs on any
     * write error. Call only under the shared .populate.lock.
     * @param array<string,array{0:list<string>,1:list<array<string,mixed>>}> $updates
     */
    private static function writeAll(array $updates): void
    {
        $staged = [];
        $original = [];
        try {
            foreach ($updates as $path => [$columns, $rows]) {
                if (! is_file($path) || ! is_writable($path)) {
                    throw new \RuntimeException('Seed CSV missing or not writable: ' . basename($path));
                }
                $original[$path] = file_get_contents($path);
                if ($original[$path] === false) {
                    throw new \RuntimeException('Cannot back up CSV: ' . basename($path));
                }
                $temp = tempnam(dirname($path), '.populate-');
                if ($temp === false) throw new \RuntimeException('Could not stage CSV file.');
                $staged[$path] = $temp;
                $file = fopen($temp, 'wb');
                if ($file === false) throw new \RuntimeException('Could not open staged CSV.');
                try {
                    if (fputcsv($file, $columns, ',', '"', '') === false) {
                        throw new \RuntimeException('Cannot write CSV header.');
                    }
                    foreach ($rows as $row) {
                        if (fputcsv($file, array_map(
                            static fn(string $field): string => (string) ($row[$field] ?? ''), $columns
                        ), ',', '"', '') === false) {
                            throw new \RuntimeException('Cannot write recipe CSV row.');
                        }
                    }
                    if (! fflush($file)) throw new \RuntimeException('Cannot flush CSV.');
                } finally {
                    fclose($file);
                }
                chmod($temp, fileperms($path) & 0777);
            }
            $committed = [];
            try {
                foreach ($staged as $path => $temp) {
                    if (! rename($temp, $path)) {
                        throw new \RuntimeException('Could not replace CSV: ' . basename($path));
                    }
                    $committed[] = $path;
                }
            } catch (\Throwable $e) {
                foreach (array_reverse($committed) as $path) {
                    // Restore original bytes, not a reconstructed CSV.
                    file_put_contents($path, $original[$path], LOCK_EX);
                }
                throw $e;
            }
        } finally {
            foreach ($staged as $temp) {
                if (is_file($temp)) unlink($temp);
            }
        }
    }
}
