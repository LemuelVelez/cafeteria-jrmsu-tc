<?php

namespace App\Database\Seeds;

use App\Services\AuditLogService;
use App\Services\CostingService;
use App\Services\SourcedRecipeCatalog;
use CodeIgniter\Database\Seeder;

/** Never replaces existing admin recipe lines or nutrition verification. */
final class RecipeSeeder extends Seeder
{
    public static function rows(): array
    {
        return array_map([SourcedRecipeCatalog::class, 'recipe'],
            SourcedRecipeCatalog::read(APPPATH . 'Database/Seeds/data/recipes.csv', SourcedRecipeCatalog::RECIPE_COLUMNS));
    }

    public function run(): void
    {
        $groups = [];
        foreach (self::rows() as $row) {
            $slug = $row['product_slug'];
            $ingredient = mb_strtolower($row['ingredient_name']);
            if (isset($groups[$slug][$ingredient])) {
                throw new \DomainException('Duplicate recipe ingredient: ' . $slug . '/' . $ingredient);
            }
            $groups[$slug][$ingredient] = $row;
        }
        $this->db->transBegin();
        try {
            $costing = new CostingService($this->db);
            $audit = new AuditLogService($this->db);
            foreach ($groups as $slug => $ingredients) {
                $product = $this->db->query('SELECT * FROM products WHERE slug=? FOR UPDATE', [$slug])->getRowArray();
                if (! $product) throw new \DomainException('Unknown seeded product: ' . $slug);
                $productId = (int) $product['id'];
                if ($this->db->table('product_recipes')->where('product_id', $productId)->countAllResults()) {
                    continue; // Leave all existing recipe lines and their product settings alone.
                }
                $first = reset($ingredients);
                foreach ($ingredients as $line) {
                    foreach (['recipe_servings', 'cooking_yield', 'packaging_cost'] as $setting) {
                        if ($line[$setting] !== $first[$setting]) {
                            throw new \DomainException('Conflicting batch settings in recipe for ' . $slug);
                        }
                    }
                    $ingredient = $this->db->table('ingredients')->where('name', $line['ingredient_name'])->get()->getRowArray();
                    if (! $ingredient || !SourcedRecipeCatalog::validReference((string) $ingredient['source_reference'])) {
                        throw new \DomainException('Missing sourced ingredient for ' . $slug . ': ' . $line['ingredient_name']);
                    }
                    $gramsPerPiece = $ingredient['grams_per_piece'] === null ? null : (float) $ingredient['grams_per_piece'];
                    CostingService::ingredientUnits($line['quantity'], $line['unit'],
                        (string) $ingredient['purchase_unit'], (float) $ingredient['density_g_per_ml'], $gramsPerPiece);
                    if ($line['unit'] === 'pcs' && $ingredient['nutrient_basis'] === '100g') {
                        CostingService::nutrientQuantity($line['quantity'], 'pcs', 1, $gramsPerPiece);
                    }
                    $this->db->table('product_recipes')->insert([
                        'product_id' => $productId, 'ingredient_id' => $ingredient['id'],
                        'quantity' => $line['quantity'], 'unit' => $line['unit'],
                        'wastage_percent' => $line['wastage_percent'],
                    ]);
                }
                $this->db->table('products')->where('id', $productId)->update([
                    'recipe_servings' => $first['recipe_servings'],
                    'cooking_yield' => $first['cooking_yield'],
                    'packaging_cost' => $first['packaging_cost'],
                ]);
                $costing->refreshNutrition($productId);
                $audit->record('recipe_seeded', 'product', $productId, [
                    'ingredient_count' => count($ingredients), 'nutrition_status' => 'estimated',
                ]);
            }
            if (! $this->db->transStatus()) throw new \RuntimeException('Recipe seeding failed.');
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }
}
