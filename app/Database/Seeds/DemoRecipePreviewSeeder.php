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

        // Read once rather than issuing another round trip for every product.
        $products = $db->table('products')
            ->whereIn('slug', array_keys(self::EXAMPLES))
            ->get()->getResultArray();
        if ($products === []) {
            return 0;
        }
        $recipeIds = array_fill_keys(array_map('intval', array_column(
            $db->table('product_recipes')
                ->select('product_id')->distinct()
                ->whereIn('product_id', array_column($products, 'id'))
                ->get()->getResultArray(),
            'product_id',
        )), true);
        $covered = 0;
        foreach ($products as $product) {
            if (! isset($recipeIds[(int) $product['id']])
                || trim((string) ($product['serving_size'] ?? '')) === '') {
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
        $created = 0;
        $existing = 0;
        CLI::write(sprintf('DemoRecipePreviewSeeder: inspecting %d products (synthetic examples only).', $total), 'cyan');
        CLI::write('Checking database access and configuring a short MySQL lock wait...', 'cyan');
        $this->limitLockWait();

        // Do not hold an eight-product transaction open over dozens of remote
        // database calls. A product is committed atomically and can be safely
        // skipped on a retry if the process is interrupted halfway through.
        CLI::write('Loading allergen reference data...', 'cyan');
        $costing = new CostingService($this->db);
        $allergenService = new AllergenService($this->db);
        $allergenOptions = $allergenService->options();
        $allergens = array_column($allergenOptions, 'id', 'code');
        $names = array_column($allergenOptions, 'name', 'code');
        $audit = new AuditLogService($this->db);

        foreach (self::EXAMPLES as $slug => $sample) {
            $index = $created + $existing + 1;
            CLI::write(sprintf('   [%d/%d] %s: checking product and recipe...', $index, $total, $sample['label']), 'yellow');

            if (! $this->db->transBegin()) {
                throw new RuntimeException('Unable to begin demo recipe transaction for ' . $slug);
            }
            try {
                // Lock only the current product, and release that lock before
                // processing the next one. MySQL's lock-wait limit is set above.
                $product = $this->db->query('SELECT * FROM products WHERE slug = ? FOR UPDATE', [$slug])->getRowArray();
                if ($product === null) {
                    throw new RuntimeException('Seed the core products before adding demo recipes: ' . $slug);
                }
                $productId = (int) $product['id'];
                if ($this->db->table('product_recipes')->where('product_id', $productId)->countAllResults()) {
                    ++$existing; // Never replace a kitchen/admin recipe.
                    if (! $this->db->transStatus()) {
                        throw new RuntimeException('Could not inspect existing recipe for ' . $slug);
                    }
                    if (! $this->db->transCommit()) {
                        throw new RuntimeException('Could not commit existing-recipe check for ' . $slug);
                    }
                    CLI::write(sprintf('   [%d/%d] %s: existing recipe preserved.', $index, $total, $sample['label']), 'green');
                    continue;
                }
                if ($product['nutrition_status'] === 'verified' || $product['nutrition_verified_by'] !== null
                    || $product['nutrition_verified_at'] !== null || $product['nutrition_source'] === 'recipe_sourced') {
                    throw new RuntimeException('Refusing to change verified nutrition for ' . $slug);
                }

                // This is one made-up whole-serving ingredient, NOT a real
                // ingredient list or authenticated kitchen measurement.
                $name = 'DEMO ONLY - ' . $sample['label'] . ' (synthetic serving)';
                $ingredient = $this->db->table('ingredients')->where('name', $name)->get()->getRowArray();
                if ($ingredient === null) {
                    $ingredientData = $sample['nutrition'];
                    foreach ($ingredientData as $field => $value) {
                        $perServing = $product[$field] === null ? $value : (float) $product[$field];
                        $ingredientData[$field] = $perServing * max(1, (int) $product['recipe_servings']);
                    }
                    $now = date('Y-m-d H:i:s');
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
                    if ($ingredient['source_reference'] !== null
                        || ! str_starts_with((string) $ingredient['supplier'], 'DEMO ONLY')) {
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
                CLI::write(sprintf('   [%d/%d] %s: calculating nutrition...', $index, $total, $sample['label']), 'cyan');
                $costing->refreshNutrition($productId);
                $audit->record('unverified_demo_recipe_seeded', 'product', $productId, [
                    'synthetic' => true, 'approved' => false, 'ingredient_count' => 1,
                ]);
                if (! $this->db->transStatus()) {
                    throw new RuntimeException('The demo recipe seed transaction failed for ' . $slug);
                }
                if (! $this->db->transCommit()) {
                    throw new RuntimeException('Could not commit demo recipe for ' . $slug);
                }
                ++$created;
                CLI::write(sprintf('   [%d/%d] %s: demo recipe saved.', $index, $total, $sample['label']), 'green');
            } catch (Throwable $error) {
                $this->db->transRollback();
                throw new RuntimeException(sprintf(
                    'Demo recipe %d/%d (%s) failed: %s. Earlier completed products remain saved; rerun php spark seed to resume.',
                    $index, $total, $slug, $error->getMessage(),
                ), 0, $error);
            }
        }

        CLI::write('Verifying all eight demo recipes...', 'cyan');
        CLI::write(sprintf('Unverified demo recipes: %d/%d available; %d created, %d existing left unchanged.',
            self::coverage($this->db), $total, $created, $existing));
    }

    /** Fail promptly instead of waiting for MySQL's long default InnoDB lock timeout. */
    private function limitLockWait(): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }
        // This changes only the current CLI connection, not a global database setting.
        // A failure to configure the limit should not silently turn into a hang.
        try {
            if ($this->db->query('SET SESSION innodb_lock_wait_timeout = 10') === false
                || $this->db->query('SET SESSION lock_wait_timeout = 10') === false) {
                throw new RuntimeException('MySQL rejected the session lock-wait limits.');
            }
        } catch (Throwable $error) {
            throw new RuntimeException('Cannot configure MySQL lock-wait timeout: ' . $error->getMessage(), 0, $error);
        }
    }
}
