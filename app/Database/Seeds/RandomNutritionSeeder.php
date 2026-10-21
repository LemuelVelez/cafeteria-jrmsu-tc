<?php

namespace App\Database\Seeds;

use App\Database\Migrations\CreateStructuredAllergens;
use App\Services\AllergenService;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

/** Explicit only: php spark db:seed RandomNutritionSeeder. Demo estimates, not food facts. */
class RandomNutritionSeeder extends Seeder
{
    private const NUTRIENT_COLUMNS = [
        'calories', 'protein_g', 'carbohydrates_g', 'fat_g',
        'sugar_g', 'fiber_g', 'sodium_mg',
    ];

    public function run(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new RuntimeException('Random nutrition demo data cannot be seeded in production.');
        }

        $updated = 0;
        $skipped = ['recipe' => 0, 'verified' => 0, 'already filled' => 0];
        $this->db->transBegin();

        try {
            $products = $this->db->table('products p')
                ->select('p.*, c.slug AS category_slug')
                ->join('categories c', 'c.id = p.category_id', 'left')
                ->get()->getResultArray();
            $recipeIds = array_fill_keys(array_column(
                $this->db->table('product_recipes')->select('product_id')->distinct()->get()->getResultArray(),
                'product_id',
            ), true);
            $linkedAllergenIds = array_fill_keys(array_column(
                $this->db->table('product_allergens')->select('product_id')->distinct()->get()->getResultArray(),
                'product_id',
            ), true);
            $allergens = $this->db->table('allergens')
                ->select('id, code, name')
                ->whereIn('code', array_keys(CreateStructuredAllergens::ALLERGENS))
                ->get()->getResultArray();
            $allergenService = new AllergenService($this->db);

            foreach ($products as $product) {
                $productId = (int) $product['id'];
                if (isset($recipeIds[$productId])) {
                    ++$skipped['recipe'];
                    continue;
                }
                if ($product['nutrition_status'] === 'verified' || $product['nutrition_verified_by'] !== null) {
                    ++$skipped['verified'];
                    continue;
                }

                $missing = array_values(array_filter(
                    self::NUTRIENT_COLUMNS,
                    static fn (string $field): bool => $product[$field] === null,
                ));
                // A completed record is also the marker for a deliberate zero-allergen draw.
                // Do not re-roll missing pivot links on later runs.
                if ($missing === [] && $product['serving_size'] !== null) {
                    ++$skipped['already filled'];
                    continue;
                }

                $updates = [];
                if ($missing !== []) {
                    $sample = self::sampleNutrition((string) ($product['category_slug'] ?? ''));
                    foreach ($missing as $field) {
                        $updates[$field] = $sample[$field];
                    }
                    // The schema defaults to "manual" even for untouched, empty records.
                    // Change that default only for wholly empty demo records, not for
                    // partly entered nutrition whose existing provenance must be kept.
                    if ($product['nutrition_source'] === null
                        || ($product['nutrition_source'] === 'manual'
                            && count($missing) === count(self::NUTRIENT_COLUMNS))) {
                        $updates['nutrition_source'] = 'unsourced';
                    }
                    if ($product['nutrition_status'] === null) {
                        $updates['nutrition_status'] = 'estimated';
                    }
                }
                if ($product['serving_size'] === null) {
                    $updates['serving_size'] = '1 serving';
                }

                if (! isset($linkedAllergenIds[$productId])) {
                    $available = $allergens;
                    $number = random_int(0, min(3, count($available)));
                    $chosen = [];
                    for ($i = 0; $i < $number; ++$i) {
                        $chosen[] = array_splice($available, random_int(0, count($available) - 1), 1)[0];
                    }
                    $allergenService->sync($productId, array_map('intval', array_column($chosen, 'id')));
                    if ($chosen !== [] && trim((string) ($product['allergens'] ?? '')) === '') {
                        $updates['allergens'] = implode(', ', array_column($chosen, 'name'));
                    }
                }

                if ($updates !== []) {
                    $this->db->table('products')->where('id', $productId)->update($updates);
                    ++$updated;
                } else {
                    ++$skipped['already filled'];
                }
            }

            if (! $this->db->transStatus()) {
                throw new RuntimeException('Random nutrition demo seed transaction failed.');
            }
            $this->db->transCommit();
        } catch (Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }

        CLI::write(sprintf(
            'Products updated: %d; products skipped: %d (recipe: %d, verified: %d, already filled: %d).',
            $updated,
            array_sum($skipped),
            $skipped['recipe'],
            $skipped['verified'],
            $skipped['already filled'],
        ));
    }

    /** @return array<string, int|float> */
    private static function sampleNutrition(string $categorySlug): array
    {
        if (str_contains($categorySlug, 'rice')) {
            $ranges = [[400, 700], [15, 35], [45, 95], [10, 30], [1, 18], [1, 10], [350, 1100]];
        } elseif (str_contains($categorySlug, 'snack') || str_contains($categorySlug, 'lunch')) {
            $ranges = [[150, 450], [3, 20], [20, 60], [4, 23], [1, 20], [1, 8], [150, 850]];
        } elseif (str_contains($categorySlug, 'drink') || str_contains($categorySlug, 'coffee')
            || str_contains($categorySlug, 'juice') || str_contains($categorySlug, 'water')) {
            $ranges = [[5, 250], [0, 8], [0, 45], [0, 9], [0, 30], [0, 3], [0, 180]];
        } else {
            $ranges = [[180, 550], [5, 25], [20, 75], [5, 25], [1, 20], [1, 9], [100, 900]];
        }

        $decimal = static fn (array $range): float => round(random_int($range[0] * 10, $range[1] * 10) / 10, 1);
        $carbohydrates = $decimal($ranges[2]);
        return [
            'calories' => random_int(...$ranges[0]),
            'protein_g' => $decimal($ranges[1]),
            'carbohydrates_g' => $carbohydrates,
            'fat_g' => $decimal($ranges[3]),
            'sugar_g' => $decimal([$ranges[4][0] > $carbohydrates ? 0 : $ranges[4][0],
                min($ranges[4][1], (int) floor($carbohydrates))]),
            'fiber_g' => $decimal([$ranges[5][0] > $carbohydrates ? 0 : $ranges[5][0],
                min($ranges[5][1], (int) floor($carbohydrates))]),
            'sodium_mg' => random_int(...$ranges[6]),
        ];
    }
}
