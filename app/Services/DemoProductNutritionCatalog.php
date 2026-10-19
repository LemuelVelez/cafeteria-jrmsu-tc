<?php

namespace App\Services;

use App\Database\Seeds\IngredientSeeder;
use App\Database\Seeds\RecipeSeeder;
use CodeIgniter\Database\BaseConnection;

/**
 * Independent, measured recipe gates for the eight cafeteria demo products.
 * The reference library is only a candidate list; this class NEVER invents
 * recipe measurements or treats an FDC identifier as a human approval.
 */
final class DemoProductNutritionCatalog
{
    public const SEEDERS = [
        'ChickenAdoboRiceSeeder' => 'chicken-adobo-rice',
        'PorkSisigRiceSeeder' => 'pork-sisig-rice',
        'FriedChickenMealSeeder' => 'fried-chicken-meal',
        'CheeseBurgerSeeder' => 'cheese-burger',
        'CrispyFriesSeeder' => 'crispy-fries',
        'IcedSpanishLatteSeeder' => 'iced-spanish-latte',
        'HotAmericanoSeeder' => 'hot-americano',
        'CalamansiJuiceSeeder' => 'calamansi-juice',
    ];

    public static function slugForSeeder(string $seeder): ?string
    {
        return self::SEEDERS[$seeder] ?? null;
    }

    /** @return list<array<string, string>> */
    public static function worksheet(): array
    {
        return SourcedRecipeCatalog::read(
            APPPATH . 'Database/Seeds/data/recipe_measurement_workbook.csv',
            NutritionReferenceCatalog::WORKSHEET_FIELDS,
        );
    }

    /**
     * @param list<array<string,mixed>> $ingredients
     * @param list<array<string,mixed>> $recipes
     * @param list<array<string,string>> $worksheet
     * @return array{ready:bool,errors:list<string>,lines:list<array<string,mixed>>}
     */
    public static function assess(string $slug, array $ingredients, array $recipes, array $worksheet): array
    {
        if (! in_array($slug, NutritionCatalogAudit::DEMO_PRODUCT_SLUGS, true)) {
            throw new \DomainException('Not a registered cafeteria demo product: ' . $slug);
        }
        $lines = array_values(array_filter($recipes,
            static fn(array $r): bool => ($r['product_slug'] ?? '') === $slug));
        $approved = array_values(array_filter($worksheet,
            static fn(array $r): bool => ($r['product_slug'] ?? '') === $slug));
        $errors = [];
        if ($lines === []) {
            $errors[] = 'Missing measured recipe lines';
        }
        if (count($approved) !== count($lines)) {
            $errors[] = 'All actual recipe lines require corresponding kitchen measurements and sign-off';
        }
        $byName = [];
        foreach ($ingredients as $ingredient) {
            $key = self::key((string) ($ingredient['name'] ?? ''));
            if (isset($byName[$key])) $errors[] = 'Duplicate ingredient in ingredients.csv';
            $byName[$key] = $ingredient;
        }
        $byApproval = [];
        foreach ($approved as $row) {
            $name = self::key((string) ($row['confirmed_ingredient'] ?? ''));
            if ($name === '') {
                $errors[] = 'Kitchen worksheet contains an unconfirmed ingredient';
            } elseif (isset($byApproval[$name])) {
                $errors[] = 'Duplicate kitchen measurement: ' . $name;
            }
            $byApproval[$name] = $row;
        }
        $seen = [];
        $first = $lines[0] ?? null;
        $signer = null;
        $signedOn = null;
        foreach ($lines as $line) {
            $name = self::key((string) $line['ingredient_name']);
            // The MySQL schema uses DECIMAL(12,3), (5,2), (5,3), (10,2).
            // Reject excess precision instead of silently changing an approved
            // measurement and then failing to match it after insertion.
            foreach (['quantity' => 3, 'wastage_percent' => 2,
                'cooking_yield' => 3, 'packaging_cost' => 2] as $field => $places) {
                if (! self::representable($line[$field], $places)) {
                    $errors[] = 'Measurement precision exceeds the database scale: ' . $name . ' / ' . $field;
                }
            }
            if (isset($seen[$name])) $errors[] = 'Duplicate recipe ingredient: ' . $name;
            $seen[$name] = true;
            $ingredient = $byName[$name] ?? null;
            $approval = $byApproval[$name] ?? null;
            if ($ingredient === null) {
                $errors[] = 'Missing sourced ingredient: ' . $name;
                continue;
            }
            if (! SourcedRecipeCatalog::validReference((string) ($ingredient['source_reference'] ?? ''))) {
                $errors[] = 'Ingredient source ID not recorded: ' . $name;
            }
            if ($first !== null) {
                foreach (['recipe_servings', 'cooking_yield', 'packaging_cost'] as $field) {
                    if (! self::sameNumber($line[$field], $first[$field])) {
                        $errors[] = 'Recipe batch setting differs: ' . $field;
                    }
                }
            }
            try {
                CostingService::ingredientUnits((float) $line['quantity'], (string) $line['unit'],
                    (string) $ingredient['purchase_unit'], (float) $ingredient['density_g_per_ml'],
                    $ingredient['grams_per_piece'] === null ? null : (float) $ingredient['grams_per_piece']);
                if ($ingredient['nutrient_basis'] === '100g') {
                    CostingService::nutrientQuantity((float) $line['quantity'], (string) $line['unit'],
                        (float) $ingredient['density_g_per_ml'],
                        $ingredient['grams_per_piece'] === null ? null : (float) $ingredient['grams_per_piece']);
                }
            } catch (\DomainException $e) {
                $errors[] = 'Measured unit conversion unavailable: ' . $name;
            }
            if ($approval === null) {
                $errors[] = 'Missing signed kitchen measurement: ' . $name;
                continue;
            }
            foreach (['measured_quantity' => 'quantity', 'wastage_percent' => 'wastage_percent',
                'recipe_servings' => 'recipe_servings', 'cooking_yield' => 'cooking_yield',
                'packaging_cost' => 'packaging_cost'] as $approvedField => $recipeField) {
                if ($approval[$approvedField] === '' || ! is_numeric($approval[$approvedField])
                    || ! self::sameNumber($approval[$approvedField], $line[$recipeField])) {
                    $errors[] = 'Worksheet and recipe disagree on ' . $approvedField . ': ' . $name;
                }
            }
            if (($approval['measured_unit'] ?? '') !== $line['unit']) {
                $errors[] = 'Worksheet unit differs: ' . $name;
            }
            $reference = (string) $ingredient['source_reference'];
            if (preg_match('/^USDA FDC #(\d+) /', $reference, $match)) {
                if (($approval['fdc_id'] ?? '') !== $match[1]) {
                    $errors[] = 'USDA FDC ID differs from approved worksheet: ' . $name;
                }
            } elseif (($approval['fdc_id'] ?? '') !== '') {
                $errors[] = 'PhilFCT ingredient must not be labeled with an unrelated USDA ID: ' . $name;
            }
            $approver = trim((string) ($approval['kitchen_approved_by'] ?? ''));
            $date = trim((string) ($approval['kitchen_approved_on'] ?? ''));
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($approver === '' || $dt === false || $dt->format('Y-m-d') !== $date) {
                $errors[] = 'A real kitchen approver and date (YYYY-MM-DD) are required: ' . $name;
            }
            if ($signer !== null && $signer !== $approver) {
                $errors[] = 'Recipe lines have inconsistent kitchen approvers';
            }
            if ($signedOn !== null && $signedOn !== $date) {
                $errors[] = 'Recipe lines have inconsistent approval dates';
            }
            $signer = $approver;
            $signedOn = $date;
        }
        foreach ($byApproval as $name => $_) {
            if (! isset($seen[$name])) $errors[] = 'Worksheet has an unseeded or extra line: ' . $name;
        }
        $errors = array_values(array_unique($errors));
        return ['ready' => $errors === [], 'errors' => $errors, 'lines' => $lines];
    }

    /** Only the product in question is required to be ready. */
    public static function status(string $slug): array
    {
        return self::assess($slug, IngredientSeeder::rows(), RecipeSeeder::rows(), self::worksheet());
    }

    /** Do not consider an existing, different admin recipe to be successfully seeded. */
    public static function isSatisfied(BaseConnection $db, string $slug): bool
    {
        $status = self::status($slug);
        if (! $status['ready'] || ! $db->tableExists('product_recipes')) return false;
        $product = $db->table('products')->where('slug', $slug)->get()->getRowArray();
        if ($product === null) return false;
        $lines = $status['lines'];
        $first = $lines[0];
        foreach (['recipe_servings', 'cooking_yield', 'packaging_cost'] as $setting) {
            if (! self::sameNumber($product[$setting], $first[$setting])) return false;
        }
        $saved = $db->table('product_recipes r')
            ->select('r.quantity,r.unit,r.wastage_percent,i.name,i.source_reference')
            ->join('ingredients i', 'i.id=r.ingredient_id')
            ->where('r.product_id', $product['id'])->get()->getResultArray();
        if (count($saved) !== count($lines)) return false;
        $byName = [];
        foreach ($saved as $row) {
            $key = self::key((string) $row['name']);
            if (isset($byName[$key])) return false;
            $byName[$key] = $row;
        }
        $sourceMap = [];
        foreach (IngredientSeeder::rows() as $ingredient) {
            $sourceMap[self::key($ingredient['name'])] = $ingredient['source_reference'];
        }
        foreach ($lines as $line) {
            $name = self::key($line['ingredient_name']);
            $savedLine = $byName[$name] ?? null;
            if ($savedLine === null || $savedLine['unit'] !== $line['unit']
                || ! self::sameNumber($savedLine['quantity'], $line['quantity'])
                || ! self::sameNumber($savedLine['wastage_percent'], $line['wastage_percent'])
                || $savedLine['source_reference'] !== ($sourceMap[$name] ?? null)) return false;
        }
        return true;
    }

    private static function key(string $name): string
    {
        $name = trim($name);
        return function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    }

    private static function representable(mixed $number, int $places): bool
    {
        return is_numeric($number) && abs((float) $number - round((float) $number, $places)) < 0.00000001;
    }

    private static function sameNumber(mixed $a, mixed $b): bool
    {
        return is_numeric($a) && is_numeric($b) && abs((float) $a - (float) $b) < 0.00001;
    }
}
