<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Explicit only. The client will add any missing menu items through Admin → Products. */
class CafeteriaMenuCatalogSeeder extends Seeder
{
    public const CATALOG = [
        'Snacks' => [
            ['Banana Cue','pcs',10], ['Chikalang (₱10)','pcs',10], ['Chikalang (₱15)','pcs',15],
            ['Butchi','pcs',5], ['Egg Sandwich','pcs',15], ['Donut','pcs',10], ['Torta','pcs',15],
            ['Puto Maya','pcs',10], ['Maja','pcs',10], ['Turon','pcs',5], ['Lomi','servings',20],
        ],
        'Cold Drinks' => [
            ['C2','pcs',20], ['Mismo','pcs',25], ['Sting','pcs',25], ['Mountain Dew','pcs',25],
            ['Kalamansi Juice','pcs',30], ['Coke 1L','pcs',60],
            ["Nature's Spring 350ml",'pcs',10], ["Nature's Spring 1000ml",'pcs',25],
        ],
        'Desserts' => [
            ['Mango Tapioca','pcs',35], ['Mango Float','pcs',35], ['Cake','pcs',50],
            ['Ice Cream 750ml','pcs',null], ['Cornetto','pcs',25], ['Ice Drop','pcs',14],
        ],
        'Rice Meals' => [
            ['Fried Chicken','pcs',35], ['Adobong Atay with Egg','servings',35], ['Paksiw na Isda','servings',35],
            ['Tinulang Isda','servings',35], ['Boiled Egg','pcs',15], ['Sunny-Side Up Egg','pcs',15],
            ['Pork Paklay','servings',50], ['Sotanghon','servings',30], ['Bihon','servings',20],
            ['Chicken Caldereta','servings',35], ['Pork Menudo','servings',50], ['Chicken Menudo','servings',35],
            ['Chopsuey','servings',25], ['Ginisang Ampalaya','servings',20], ['Ginisang Talong','servings',20],
            ['Pinakbet','servings',20], ['Agar-Agar','pcs',20], ['Adobong Kangkong','servings',20],
            ['Sinigang na Baboy','servings',50], ['Sinigang na Bangus','servings',35],
            ['Ginataang Nangka','servings',20], ['Rice','servings',15],
        ],
    ];

    public function run(): void
    {
        $db = $this->db;
        $db->transBegin();
        try {
            foreach (self::CATALOG as $category => $items) {
                $categorySlug = url_title($category, '-', true);
                $existingCategory = $db->table('categories')->where('slug', $categorySlug)->get()->getRowArray();
                if ($existingCategory) {
                    $categoryId = (int) $existingCategory['id'];
                } else {
                    $db->table('categories')->insert([
                        'name' => $category, 'slug' => $categorySlug,
                        'is_active' => 1, 'sort_order' => array_search($category, array_keys(self::CATALOG), true) + 1,
                        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $categoryId = (int) $db->insertID();
                }
                foreach ($items as [$name, $unit, $price]) {
                    $slug = url_title($name, '-', true);
                    if ($db->table('products')->where('slug', $slug)->countAllResults() > 0) continue;
                    // Calamansi and Kalamansi are the same product; never duplicate it.
                    if ($name === 'Kalamansi Juice' && $db->table('products')->where('slug', 'calamansi-juice')->countAllResults() > 0) continue;
                    $db->table('products')->insert([
                        'category_id' => $categoryId, 'name' => $name, 'slug' => $slug,
                        'stock_unit' => $unit, 'price' => $price ?? 0, 'description' => '',
                        'stock' => 0, 'reorder_level' => 0, 'max_stock' => 50,
                        'is_available' => $price !== null ? 1 : 0,
                        'nutrition_source' => 'manual', 'nutrition_status' => 'estimated',
                        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            if (! $db->transStatus()) throw new \RuntimeException('Cafeteria menu catalog insert failed.');
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }
}
