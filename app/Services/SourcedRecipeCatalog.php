<?php

namespace App\Services;

/**
 * Strict input boundary for adviser-reviewed demo recipe files. No nutrition
 * number is generated, estimated, or silently defaulted by the application.
 */
final class SourcedRecipeCatalog
{
    public const INGREDIENT_COLUMNS = [
        'name', 'purchase_unit', 'cost_per_unit', 'supplier', 'nutrient_basis',
        'calories', 'protein_g', 'carbohydrates_g', 'fat_g', 'sugar_g', 'fiber_g',
        'sodium_mg', 'density_g_per_ml', 'grams_per_piece', 'reorder_level',
        'opening_stock', 'source_reference',
    ];

    public const RECIPE_COLUMNS = [
        'product_slug', 'ingredient_name', 'quantity', 'unit', 'wastage_percent',
        'recipe_servings', 'cooking_yield', 'packaging_cost',
    ];

    /** @return list<array<string, string>> */
    public static function read(string $path, array $columns): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException('The recipe source file could not be read: ' . basename($path));
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Could not open the recipe source file.');
        }
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($header)) {
                throw new \DomainException('The recipe CSV has no header.');
            }
            // BOM removal is for Excel-exported inputs only; do not change field values.
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? ''));
            if ($header !== $columns) {
                throw new \DomainException('Unexpected columns in ' . basename($path) . '.');
            }
            $rows = [];
            while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($cells === [null] || count($cells) === 1 && trim((string) $cells[0]) === '') {
                    continue;
                }
                if (count($cells) !== count($columns)) {
                    throw new \DomainException('Wrong column count at CSV line ' . (count($rows) + 2) . '.');
                }
                $rows[] = array_combine($columns, array_map('trim', $cells));
            }
            return $rows;
        } finally {
            fclose($handle);
        }
    }

    public static function ingredient(array $row): array
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            throw new \DomainException('Ingredient name must be 1–160 characters.');
        }
        $unit = (string) ($row['purchase_unit'] ?? '');
        if (! in_array($unit, ['g', 'kg', 'ml', 'l', 'pcs'], true)) {
            throw new \DomainException('Unrecognized purchase unit for ' . $name);
        }
        $basis = (string) ($row['nutrient_basis'] ?? '');
        if (! in_array($basis, ['100g', 'unit'], true)) {
            throw new \DomainException('Ingredient nutrient basis must be 100g or unit: ' . $name);
        }
        $reference = (string) ($row['source_reference'] ?? '');
        if (! self::validReference($reference)) {
            throw new \DomainException('An actual PhilFCT food ID or USDA FDC food ID is required for ' . $name);
        }
        // Demo prices cannot be mistaken for supplier invoices.
        $supplier = (string) ($row['supplier'] ?? '');
        if (! str_contains($supplier, 'Demo price – replace with actual receipts')) {
            throw new \DomainException('Mark every demo cost as Demo price – replace with actual receipts: ' . $name);
        }
        $values = [
            'name' => $name, 'purchase_unit' => $unit, 'nutrient_basis' => $basis,
            'supplier' => $supplier, 'source_reference' => $reference,
        ];
        foreach (['cost_per_unit', 'calories', 'protein_g', 'carbohydrates_g', 'fat_g',
            'sugar_g', 'fiber_g', 'sodium_mg', 'reorder_level', 'opening_stock'] as $field) {
            $values[$field] = self::number($row, $field, 0, 10000000);
        }
        // A density is not a nutrient and cannot be inferred for dry foods.
        // Zero means *not measured* and is valid only when no volume conversion
        // is used; CostingService refuses any volume-to-mass calculation then.
        $density = trim((string) ($row['density_g_per_ml'] ?? ''));
        if ($density === '' || preg_match('/^0(?:\.0+)?$/', $density) === 1) {
            if (in_array($unit, ['ml', 'l'], true) && $basis === '100g') {
                throw new \DomainException('Measured density_g_per_ml is required for volume-purchased food: ' . $name);
            }
            $values['density_g_per_ml'] = 0.0;
        } else {
            $values['density_g_per_ml'] = self::number($row, 'density_g_per_ml', 0.0001, 25);
        }
        $grams = trim((string) ($row['grams_per_piece'] ?? ''));
        $values['grams_per_piece'] = $grams === '' ? null : self::number($row, 'grams_per_piece', 0.0001, 100000);
        // For a 100g nutrient basis, piece-based recipes cannot be calculated
        // unless a measured edible weight is provided.
        if ($unit === 'pcs' && $basis === '100g' && $values['grams_per_piece'] === null) {
            throw new \DomainException('Measured grams_per_piece is required for: ' . $name);
        }
        if ($values['cost_per_unit'] <= 0) {
            throw new \DomainException('A positive demo purchase cost is required for: ' . $name);
        }
        return $values;
    }

    public static function recipe(array $row): array
    {
        $slug = trim((string) ($row['product_slug'] ?? ''));
        $ingredient = trim((string) ($row['ingredient_name'] ?? ''));
        if (! preg_match('/^[a-z0-9-]{1,140}$/', $slug) || $ingredient === '') {
            throw new \DomainException('Recipe requires a valid product slug and ingredient name.');
        }
        $unit = (string) ($row['unit'] ?? '');
        if (! in_array($unit, ['g', 'kg', 'ml', 'l', 'pcs'], true)) {
            throw new \DomainException('Invalid recipe unit for ' . $slug);
        }
        $quantity = self::number($row, 'quantity', 0.001, 10000000);
        $servings = self::number($row, 'recipe_servings', 1, 10000);
        if ((float) (int) $servings !== $servings) {
            throw new \DomainException('Recipe servings must be a whole number for ' . $slug);
        }
        return [
            'product_slug' => $slug, 'ingredient_name' => $ingredient,
            'quantity' => $quantity, 'unit' => $unit,
            'wastage_percent' => self::number($row, 'wastage_percent', 0, 100),
            'recipe_servings' => (int) $servings,
            'cooking_yield' => self::number($row, 'cooking_yield', 0.001, 10),
            'packaging_cost' => self::number($row, 'packaging_cost', 0, 100000),
        ];
    }

    public static function validReference(string $reference): bool
    {
        return preg_match('/^PhilFCT .+, food ID [A-Za-z0-9._-]+ – .+$/u', $reference) === 1
            || preg_match('/^USDA FDC #[1-9][0-9]* \([A-Za-z][A-Za-z ]+\)$/', $reference) === 1;
    }

    private static function number(array $row, string $field, float $min, float $max): float
    {
        $raw = trim((string) ($row[$field] ?? ''));
        if ($raw === '' || ! is_numeric($raw) || ! is_finite((float) $raw)
            || (float) $raw < $min || (float) $raw > $max) {
            throw new \DomainException('Enter a sourced, finite value for ' . $field . '.');
        }
        return (float) $raw;
    }
}
