<?php

namespace App\Services;

use App\Database\Seeds\IngredientSeeder;
use CodeIgniter\Database\BaseConnection;

/** A single transaction imports one approved recipe and its missing ingredients. */
final class DemoProductRecipeSeedService
{
    public function __construct(private readonly BaseConnection $db) {}

    public function seed(string $slug): void
    {
        $status = DemoProductNutritionCatalog::status($slug);
        if (! $status['ready']) {
            throw new \DomainException('Cannot seed ' . $slug . ': ' . implode('; ', $status['errors']));
        }
        // Only source rows used by this product are imported, permitting one
        // approved menu item at a time without waiting for the other seven.
        $sources = [];
        foreach (IngredientSeeder::rows() as $row) {
            $sources[self::key($row['name'])] = $row;
        }
        $this->db->transBegin();
        try {
            $product = $this->db->query('SELECT * FROM products WHERE slug=? FOR UPDATE', [$slug])->getRowArray();
            if ($product === null) throw new \DomainException('Demo product does not exist: ' . $slug);
            if (DemoProductNutritionCatalog::isSatisfied($this->db, $slug)) {
                $this->db->transCommit();
                return; // Idempotent: never overwrite an admin-approved recipe.
            }
            $productId = (int) $product['id'];
            if ($this->db->table('product_recipes')->where('product_id', $productId)->countAllResults() > 0) {
                throw new \DomainException('Existing recipe for ' . $slug . ' differs from signed measurements; manual review required.');
            }
            $audit = new AuditLogService($this->db);
            $inventory = new IngredientInventoryService($this->db);
            foreach ($status['lines'] as $line) {
                $source = $sources[self::key($line['ingredient_name'])];
                $ingredient = $this->db->query('SELECT * FROM ingredients WHERE name=? FOR UPDATE', [$source['name']])->getRowArray();
                if ($ingredient === null) {
                    $new = $source;
                    $opening = (float) $new['opening_stock'];
                    unset($new['opening_stock']);
                    $new['stock'] = 0;
                    $new['created_at'] = $new['updated_at'] = date('Y-m-d H:i:s');
                    if (! $this->db->table('ingredients')->insert($new)) {
                        throw new \RuntimeException('Cannot insert sourced ingredient: ' . $source['name']);
                    }
                    $ingredientId = (int) $this->db->insertID();
                    if (! $this->db->table('ingredient_cost_history')->insert([
                        'ingredient_id' => $ingredientId,
                        'unit_cost' => $source['cost_per_unit'],
                        'effective_at' => date('Y-m-d H:i:s'),
                        'changed_by' => null,
                    ])) throw new \RuntimeException('Cannot record ingredient cost history.');
                    $audit->record('sourced_ingredient_seeded', 'ingredient', $ingredientId, [
                        'source_reference' => $source['source_reference'], 'recipe_product_id' => $productId,
                    ]);
                    if ($opening > 0) $inventory->opening($ingredientId, $opening);
                } else {
                    $ingredientId = (int) $ingredient['id'];
                    if ((string) $ingredient['source_reference'] !== (string) $source['source_reference']
                        || (string) $ingredient['nutrient_basis'] !== (string) $source['nutrient_basis']) {
                        throw new \DomainException('Existing ingredient source or nutrient basis conflicts with signed recipe: ' . $source['name']);
                    }
                }
                if (! $this->db->table('product_recipes')->insert([
                    'product_id' => $productId,
                    'ingredient_id' => $ingredientId,
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'wastage_percent' => $line['wastage_percent'],
                ])) throw new \RuntimeException('Cannot insert approved recipe ingredient.');
            }
            $first = $status['lines'][0];
            $old = array_intersect_key($product, array_flip(['recipe_servings', 'cooking_yield', 'packaging_cost']));
            $settings = array_intersect_key($first, array_flip(['recipe_servings', 'cooking_yield', 'packaging_cost']));
            if (! $this->db->table('products')->where('id', $productId)->update($settings)) {
                throw new \RuntimeException('Cannot update recipe batch settings.');
            }
            $audit->recordChange('recipe_seed_settings_changed', 'product', $productId, $old, $settings);
            (new CostingService($this->db))->refreshNutrition($productId);
            $newProduct = $this->db->table('products')->where('id', $productId)->get()->getRowArray();
            $nutritionKeys = ['calories', 'protein_g', 'carbohydrates_g', 'fat_g', 'sugar_g',
                'fiber_g', 'sodium_mg', 'nutrition_source', 'nutrition_status',
                'nutrition_verified_by', 'nutrition_verified_at'];
            $audit->recordChange('recipe_nutrition_recalculated', 'product', $productId,
                array_intersect_key($product, array_flip($nutritionKeys)),
                array_intersect_key($newProduct, array_flip($nutritionKeys)));
            $audit->record('sourced_demo_recipe_seeded', 'product', $productId, [
                'ingredient_count' => count($status['lines']), 'nutrition_status' => 'estimated',
            ]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Recipe seed transaction failed.');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private static function key(string $name): string
    {
        $name = trim($name);
        return function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    }
}
