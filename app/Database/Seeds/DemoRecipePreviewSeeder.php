<?php

namespace App\Database\Seeds;

use App\Services\AllergenService;
use App\Services\AuditLogService;
use App\Services\CostingService;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

/** Development-only illustration: synthetic whole-serving ingredients, NOT approved kitchen recipes. */
final class DemoRecipePreviewSeeder extends Seeder
{
    /**
     * Sample per-serving figures; these are fabricated UI placeholders, not food facts.
     * The matching ingredient represents an entire sample serving, NOT its actual components.
     *
     * @var array<string, array{label: string, nutrition: array<string, int|float>, allergens: list<string>}>
     */
    public const EXAMPLES = [
        'chicken-adobo-rice' => [
            'label' => 'Chicken Adobo Rice',
            'nutrition' => ['calories' => 550, 'protein_g' => 29.5, 'carbohydrates_g' => 67.0,
                'fat_g' => 18.2, 'sugar_g' => 5.1, 'fiber_g' => 3.6, 'sodium_mg' => 890],
            'allergens' => ['soy'],
        ],
        'pork-sisig-rice' => [
            'label' => 'Pork Sisig Rice',
            'nutrition' => ['calories' => 630, 'protein_g' => 26.0, 'carbohydrates_g' => 65.5,
                'fat_g' => 29.0, 'sugar_g' => 3.2, 'fiber_g' => 3.5, 'sodium_mg' => 980],
            'allergens' => ['soy', 'egg'],
        ],
        'fried-chicken-meal' => [
            'label' => 'Fried Chicken Meal',
            'nutrition' => ['calories' => 670, 'protein_g' => 34.0, 'carbohydrates_g' => 70.0,
                'fat_g' => 28.0, 'sugar_g' => 4.5, 'fiber_g' => 3.1, 'sodium_mg' => 1020],
            'allergens' => ['wheat_gluten', 'milk', 'egg'],
        ],
        'cheese-burger' => [
            'label' => 'Cheese Burger',
            'nutrition' => ['calories' => 410, 'protein_g' => 19.0, 'carbohydrates_g' => 37.5,
                'fat_g' => 20.5, 'sugar_g' => 6.5, 'fiber_g' => 2.1, 'sodium_mg' => 790],
            'allergens' => ['wheat_gluten', 'milk', 'egg'],
        ],
        'crispy-fries' => [
            'label' => 'Crispy Fries',
            'nutrition' => ['calories' => 300, 'protein_g' => 4.0, 'carbohydrates_g' => 41.5,
                'fat_g' => 13.5, 'sugar_g' => 1.2, 'fiber_g' => 4.1, 'sodium_mg' => 410],
            'allergens' => ['wheat_gluten'], // Illustrative only: actual fries may not contain wheat.
        ],
        'iced-spanish-latte' => [
            'label' => 'Iced Spanish Latte',
            'nutrition' => ['calories' => 215, 'protein_g' => 6.2, 'carbohydrates_g' => 32.5,
                'fat_g' => 6.7, 'sugar_g' => 29.0, 'fiber_g' => 0.0, 'sodium_mg' => 125],
            'allergens' => ['milk'],
        ],
        'hot-americano' => [
            'label' => 'Hot Americano',
            'nutrition' => ['calories' => 8, 'protein_g' => 0.2, 'carbohydrates_g' => 1.4,
                'fat_g' => 0.0, 'sugar_g' => 0.0, 'fiber_g' => 0.0, 'sodium_mg' => 8],
            'allergens' => ['milk'], // Demonstration/cross-contact example, not a statement about black coffee.
        ],
        'calamansi-juice' => [
            'label' => 'Calamansi Juice',
            'nutrition' => ['calories' => 115, 'protein_g' => 0.6, 'carbohydrates_g' => 28.0,
                'fat_g' => 0.1, 'sugar_g' => 25.0, 'fiber_g' => 0.5, 'sodium_mg' => 12],
            'allergens' => ['sulphites'], // Demonstration only: not evidence of a preservative in the drink.
        ],
    ];

    /** Number of core menu products with a linked recipe and complete displayed nutrition. */
    public static function coverage(BaseConnection $db): int
    {
        if (! $db->tableExists('products') || ! $db->tableExists('product_recipes')) {
            return 0;
        }
        $products = $db->table('products')->whereIn('slug', array_keys(self::EXAMPLES))
            ->get()->getResultArray();
        $covered = 0;
        foreach ($products as $product) {
            if (! $db->table('product_recipes')->where('product_id', $product['id'])->countAllResults()) {
                continue;
            }
            if (trim((string) ($product['serving_size'] ?? '')) === '') {
                continue;
            }
            foreach (array_keys(self::EXAMPLES[$product['slug']]['nutrition']) as $field) {
                if ($product[$field] === null) {
                    continue 2;
                }
            }
            ++$covered;
        }
        return $covered;
    }

    public function run(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new RuntimeException('Synthetic demo recipes must never be seeded in production.');
        }

        $created = 0;
        $existing = 0;
        $this->db->transBegin();
        try {
            $costing = new CostingService($this->db);
            $allergenService = new AllergenService($this->db);
            $allergenOptions = $allergenService->options();
            $allergens = array_column($allergenOptions, 'id', 'code');
            $names = array_column($allergenOptions, 'name', 'code');
            $audit = new AuditLogService($this->db);
            $now = date('Y-m-d H:i:s');
            foreach (self::EXAMPLES as $slug => $sample) {
                $product = $this->db->query('SELECT * FROM products WHERE slug = ? FOR UPDATE', [$slug])->getRowArray();
                if ($product === null) {
                    throw new RuntimeException('Seed the core products before adding demo recipes: ' . $slug);
                }
                $productId = (int) $product['id'];
                if ($this->db->table('product_recipes')->where('product_id', $productId)->countAllResults()) {
                    ++$existing; // Never replace a kitchen/admin recipe.
                    continue;
                }
                if ($product['nutrition_status'] === 'verified' || $product['nutrition_verified_by'] !== null
                    || $product['nutrition_verified_at'] !== null || $product['nutrition_source'] === 'recipe_sourced') {
                    throw new RuntimeException('Refusing to change verified nutrition for ' . $slug);
                }
                // A synthetic whole-serving ingredient, not an ingredient list or a
                // kitchen measurement. Its empty source_reference yields recipe_unverified.
                $name = 'DEMO ONLY - ' . $sample['label'] . ' (synthetic serving)';
                $ingredient = $this->db->table('ingredients')->where('name', $name)->get()->getRowArray();
                if ($ingredient === null) {
                    // Preserve every pre-existing non-NULL nutrient value. The
                    // synthetic recipe is chosen to reproduce those values through
                    // CostingService; only missing values use the demo example.
                    $ingredientData = $sample['nutrition'];
                    foreach ($ingredientData as $field => $value) {
                        $perServing = $product[$field] === null ? $value : (float) $product[$field];
                        $ingredientData[$field] = $perServing * max(1, (int) $product['recipe_servings']);
                    }
                    $ingredientData += [
                        'name' => $name, 'purchase_unit' => 'g', 'cost_per_unit' => 0,
                        'supplier' => 'DEMO ONLY - not an actual supplier or purchase price',
                        'source_reference' => null, 'nutrient_basis' => '100g',
                        'density_g_per_ml' => 0, 'grams_per_piece' => null,
                        'reorder_level' => 0, 'stock' => 0,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                    if (! $this->db->table('ingredients')->insert($ingredientData)) {
                        throw new RuntimeException('Unable to insert demo ingredient: ' . $name);
                    }
                    $ingredientId = (int) $this->db->insertID();
                    if (! $this->db->table('ingredient_cost_history')->insert([
                        'ingredient_id' => $ingredientId, 'unit_cost' => 0,
                        'effective_at' => $now, 'changed_by' => null,
                    ])) {
                        throw new RuntimeException('Unable to insert demo ingredient cost history.');
                    }
                } else {
                    if ($ingredient['source_reference'] !== null || ! str_starts_with((string) $ingredient['supplier'], 'DEMO ONLY')) {
                        throw new RuntimeException('Existing ingredient is not a demo placeholder: ' . $name);
                    }
                    $ingredientId = (int) $ingredient['id'];
                }
                if (! $this->db->table('product_recipes')->insert([
                    'product_id' => $productId, 'ingredient_id' => $ingredientId,
                    'quantity' => 100, 'unit' => 'g', 'wastage_percent' => 0,
                ])) {
                    throw new RuntimeException('Unable to insert demo recipe: ' . $slug);
                }

                if (trim((string) ($product['serving_size'] ?? '')) === '') {
                    if (! $this->db->table('products')->where('id', $productId)
                        ->update(['serving_size' => '1 serving (demo)'])) {
                        throw new RuntimeException('Unable to set demo serving size: ' . $slug);
                    }
                }
                if (! $this->db->table('product_allergens')->where('product_id', $productId)->countAllResults()) {
                    $ids = [];
                    foreach ($sample['allergens'] as $code) {
                        if (! isset($allergens[$code])) {
                            throw new RuntimeException('Missing allergen reference code: ' . $code);
                        }
                        $ids[] = (int) $allergens[$code];
                    }
                    $allergenService->sync($productId, $ids);
                    if (trim((string) ($product['allergens'] ?? '')) === '') {
                        if (! $this->db->table('products')->where('id', $productId)
                            ->update(['allergens' => implode(', ', array_map(
                                static fn (string $code): string => $names[$code], $sample['allergens'],
                            ))])) {
                            throw new RuntimeException('Unable to set demo allergen names: ' . $slug);
                        }
                    }
                }
                $costing->refreshNutrition($productId);
                $audit->record('unverified_demo_recipe_seeded', 'product', $productId, [
                    'synthetic' => true, 'approved' => false, 'ingredient_count' => 1,
                ]);
                ++$created;
            }
            if (! $this->db->transStatus()) {
                throw new RuntimeException('The demo recipe seed transaction failed.');
            }
            $this->db->transCommit();
        } catch (Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        CLI::write(sprintf('Unverified demo recipes: %d/%d available; %d created, %d existing left unchanged.',
            self::coverage($this->db), count(self::EXAMPLES), $created, $existing));
    }
}
