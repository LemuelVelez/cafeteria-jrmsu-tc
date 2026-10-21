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

    /** One grouped read, not one recipe query per product. */
    public static function coverage(BaseConnection $db): int
    {
        if (! $db->tableExists('products') || ! $db->tableExists('product_recipes')) {
            return 0;
        }

        $rows = $db->table('products p')
            ->select('p.slug,p.serving_size,p.calories,p.protein_g,p.carbohydrates_g,p.fat_g,p.sugar_g,p.fiber_g,p.sodium_mg')
            ->select('EXISTS (SELECT 1 FROM product_recipes r WHERE r.product_id = p.id) AS has_recipe', false)
            ->whereIn('p.slug', array_keys(self::EXAMPLES))
            ->get()->getResultArray();

        $covered = 0;
        foreach ($rows as $product) {
            if (! (int) $product['has_recipe'] || trim((string) ($product['serving_size'] ?? '')) === '') {
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

        $total = count(self::EXAMPLES);
        CLI::write('DemoRecipePreviewSeeder: checking eight synthetic previews...', 'cyan');
        $covered = self::coverage($this->db);
        if ($covered === $total) {
            CLI::write('All 8/8 demo recipes already have nutrition. Nothing to import.', 'green');
            return;
        }

        $this->limitLockWait();
        $start = microtime(true);
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Could not start the demo recipe transaction.');
        }
        try {
            // Lock the eight parent rows in one statement instead of making
            // separate remote round trips for each product. All writes commit
            // together; a failed import leaves no half-seeded recipes.
            CLI::write('Loading menu products and existing recipes...', 'cyan');
            $slugs = array_keys(self::EXAMPLES);
            $placeholders = implode(',', array_fill(0, $total, '?'));
            $products = array_column($this->db->query(
                'SELECT * FROM products WHERE slug IN (' . $placeholders . ') FOR UPDATE', $slugs,
            )->getResultArray(), null, 'slug');
            foreach ($slugs as $slug) {
                if (! isset($products[$slug])) {
                    throw new RuntimeException('Seed the core menu products first: ' . $slug);
                }
            }

            $productIds = array_map('intval', array_column($products, 'id'));
            $recipeRows = $this->db->table('product_recipes')
                ->select('product_id')->whereIn('product_id', $productIds)
                ->get()->getResultArray();
            $hasRecipe = array_fill_keys(array_map('intval', array_column($recipeRows, 'product_id')), true);
            $missing = [];
            foreach (self::EXAMPLES as $slug => $sample) {
                $product = $products[$slug];
                if (isset($hasRecipe[(int) $product['id']])) {
                    continue; // Never change an existing administrative/kitchen recipe.
                }
                if ($product['nutrition_status'] === 'verified'
                    || $product['nutrition_verified_by'] !== null
                    || $product['nutrition_verified_at'] !== null
                    || $product['nutrition_source'] === 'recipe_sourced') {
                    throw new RuntimeException('Refusing to change verified nutrition for ' . $slug);
                }
                $missing[$slug] = $sample;
            }

            if ($missing !== []) {
                CLI::write(sprintf('Preparing %d missing recipes as one batch...', count($missing)), 'cyan');
                $names = array_map(
                    static fn (array $sample): string => 'DEMO ONLY - ' . $sample['label'] . ' (synthetic serving)',
                    $missing,
                );
                $knownRows = $this->db->table('ingredients')->whereIn('name', array_values($names))
                    ->get()->getResultArray();
                $known = array_column($knownRows, null, 'name');
                $new = [];
                $now = date('Y-m-d H:i:s');
                foreach ($missing as $slug => $sample) {
                    $name = $names[$slug];
                    if (isset($known[$name])) {
                        if ($known[$name]['source_reference'] !== null
                            || ! str_starts_with((string) $known[$name]['supplier'], 'DEMO ONLY')) {
                            throw new RuntimeException('Existing ingredient is not a demo placeholder: ' . $name);
                        }
                        continue;
                    }
                    $product = $products[$slug];
                    $nutrients = $sample['nutrition'];
                    foreach ($nutrients as $field => $value) {
                        $nutrients[$field] = ($product[$field] === null ? $value : (float) $product[$field])
                            * max(1, (int) $product['recipe_servings']);
                    }
                    $new[] = $nutrients + [
                        'name' => $name, 'purchase_unit' => 'g', 'cost_per_unit' => 0,
                        'supplier' => 'DEMO ONLY - not an actual supplier or purchase price',
                        'source_reference' => null, 'nutrient_basis' => '100g',
                        'density_g_per_ml' => 0, 'grams_per_piece' => null,
                        'reorder_level' => 0, 'stock' => 0,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                if ($new !== [] && $this->db->table('ingredients')->insertBatch($new) === false) {
                    throw new RuntimeException('Failed to insert synthetic ingredients.');
                }
                $ingredientRows = $this->db->table('ingredients')
                    ->select('id,name,supplier,source_reference')
                    ->whereIn('name', array_values($names))->get()->getResultArray();
                $ingredients = array_column($ingredientRows, null, 'name');
                $history = [];
                $recipeInserts = [];
                $newNames = array_fill_keys(array_column($new, 'name'), true);
                foreach ($missing as $slug => $sample) {
                    $name = $names[$slug];
                    if (! isset($ingredients[$name])
                        || $ingredients[$name]['source_reference'] !== null
                        || ! str_starts_with((string) $ingredients[$name]['supplier'], 'DEMO ONLY')) {
                        throw new RuntimeException('Synthetic ingredient not available: ' . $name);
                    }
                    $ingredientId = (int) $ingredients[$name]['id'];
                    if (isset($newNames[$name])) {
                        $history[] = [
                            'ingredient_id' => $ingredientId, 'unit_cost' => 0,
                            'effective_at' => $now, 'changed_by' => null,
                        ];
                    }
                    $recipeInserts[] = [
                        'product_id' => (int) $products[$slug]['id'],
                        'ingredient_id' => $ingredientId,
                        'quantity' => 100, 'unit' => 'g', 'wastage_percent' => 0,
                    ];
                }
                if ($history !== [] && $this->db->table('ingredient_cost_history')->insertBatch($history) === false) {
                    throw new RuntimeException('Failed to insert synthetic ingredient cost history.');
                }
                if ($this->db->table('product_recipes')->insertBatch($recipeInserts) === false) {
                    throw new RuntimeException('Failed to insert synthetic product recipes.');
                }

                CLI::write('Linking demo allergen labels and serving sizes...', 'cyan');
                $ids = array_map('intval', array_column($recipeInserts, 'product_id'));
                $linked = array_fill_keys(array_map('intval', array_column(
                    $this->db->table('product_allergens')->select('product_id')
                        ->whereIn('product_id', $ids)->get()->getResultArray(),
                    'product_id',
                )), true);
                $allergenService = new AllergenService($this->db);
                $allergenOptions = $allergenService->options();
                $allergenIds = array_column($allergenOptions, 'id', 'code');
                $allergenNames = array_column($allergenOptions, 'name', 'code');
                $updates = [];
                foreach ($missing as $slug => $sample) {
                    $product = $products[$slug];
                    $id = (int) $product['id'];
                    $fields = ['id' => $id];
                    if (trim((string) ($product['serving_size'] ?? '')) === '') {
                        $fields['serving_size'] = '1 serving (demo)';
                    }
                    if (! isset($linked[$id])) {
                        $allergenList = [];
                        foreach ($sample['allergens'] as $code) {
                            if (! isset($allergenIds[$code])) {
                                throw new RuntimeException('Missing allergen reference code: ' . $code);
                            }
                            $allergenList[] = (int) $allergenIds[$code];
                        }
                        $allergenService->sync($id, $allergenList);
                        if (trim((string) ($product['allergens'] ?? '')) === '') {
                            $fields['allergens'] = implode(', ', array_map(
                                static fn (string $code): string => $allergenNames[$code], $sample['allergens'],
                            ));
                        }
                    }
                    if (count($fields) > 1) {
                        // Different products may need different columns updated;
                        // individual narrow updates avoid NULLing existing text.
                        $updates[] = $fields;
                    }
                }
                foreach ($updates as $fields) {
                    $id = $fields['id'];
                    unset($fields['id']);
                    if (! $this->db->table('products')->where('id', $id)->update($fields)) {
                        throw new RuntimeException('Could not save demo labels for product ' . $id);
                    }
                }

                // Recalculate from the inserted recipe lines in CostingService.
                // One joined read and one batch write replace 8 separate
                // calculate() calls (dozens of remote SQL requests).
                CLI::write('Calculating recipe-based nutrition in one batch...', 'cyan');
                (new CostingService($this->db))->refreshNutritionBatch($ids);
                $audit = new AuditLogService($this->db);
                foreach ($ids as $id) {
                    $audit->record('unverified_demo_recipe_seeded', 'product', $id, [
                        'synthetic' => true, 'approved' => false, 'ingredient_count' => 1,
                    ]);
                }
            }

            if (! $this->db->transStatus()) {
                throw new RuntimeException('Demo recipe database transaction failed.');
            }
            CLI::write('Committing demo recipes...', 'cyan');
            if (! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit demo recipes.');
            }
        } catch (Throwable $error) {
            $this->db->transRollback();
            throw new RuntimeException('Demo recipes could not be imported: ' . $error->getMessage(), 0, $error);
        }

        $covered = self::coverage($this->db);
        CLI::write(sprintf(
            'Demo recipes: %d/%d available; %d newly imported (%d ms).',
            $covered, $total, count($missing), (int) round((microtime(true) - $start) * 1000),
        ), $covered === $total ? 'green' : 'yellow');
        if ($covered !== $total) {
            throw new RuntimeException('Some demo recipes still lack nutrition. Run php spark seed again after checking the database.');
        }
    }

    /** Limit MySQL row-lock waits on this connection only. */
    private function limitLockWait(): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }
        if ($this->db->query('SET SESSION innodb_lock_wait_timeout = 10') === false
            || $this->db->query('SET SESSION lock_wait_timeout = 10') === false) {
            throw new RuntimeException('Could not configure MySQL lock-wait limits.');
        }
    }
}
