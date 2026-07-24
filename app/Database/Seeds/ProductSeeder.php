<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = array_column($this->db->table('categories')->get()->getResultArray(), 'id', 'slug');
        $now = date('Y-m-d H:i:s');
        $rows = [
            ['category_id' => $categories['rice-meals'], 'name' => 'Chicken Adobo Rice', 'slug' => 'chicken-adobo-rice', 'description' => 'Tender chicken adobo with steamed rice and vegetables.', 'price' => 95, 'stock' => 40, 'is_featured' => 1],
            ['category_id' => $categories['rice-meals'], 'name' => 'Pork Sisig Rice', 'slug' => 'pork-sisig-rice', 'description' => 'Sizzling-style pork sisig served with rice.', 'price' => 105, 'stock' => 35, 'is_featured' => 1],
            ['category_id' => $categories['rice-meals'], 'name' => 'Fried Chicken Meal', 'slug' => 'fried-chicken-meal', 'description' => 'Crispy fried chicken, gravy, and steamed rice.', 'price' => 110, 'stock' => 30, 'is_featured' => 1],
            ['category_id' => $categories['snacks'], 'name' => 'Cheese Burger', 'slug' => 'cheese-burger', 'description' => 'Beef patty, cheese, lettuce, and house dressing.', 'price' => 75, 'stock' => 25, 'is_featured' => 0],
            ['category_id' => $categories['snacks'], 'name' => 'Crispy Fries', 'slug' => 'crispy-fries', 'description' => 'Golden fries with ketchup or cheese dip.', 'price' => 55, 'stock' => 50, 'is_featured' => 0],
            ['category_id' => $categories['coffee'], 'name' => 'Iced Spanish Latte', 'slug' => 'iced-spanish-latte', 'description' => 'Espresso, creamy milk, and lightly sweetened condensed milk.', 'price' => 85, 'stock' => 45, 'is_featured' => 1],
            ['category_id' => $categories['coffee'], 'name' => 'Hot Americano', 'slug' => 'hot-americano', 'description' => 'Bold espresso with hot water.', 'price' => 60, 'stock' => 45, 'is_featured' => 0],
            ['category_id' => $categories['cold-drinks'], 'name' => 'Calamansi Juice', 'slug' => 'calamansi-juice', 'description' => 'Fresh local calamansi juice served chilled.', 'price' => 45, 'stock' => 60, 'is_featured' => 0],
        ];
        foreach ($rows as &$row) {
            $row += ['image' => null, 'is_available' => 1, 'created_at' => $now, 'updated_at' => $now];
        }
        $this->db->table('products')->insertBatch($rows);

        $products = array_column($this->db->table('products')->get()->getResultArray(), 'id', 'slug');
        $addons = [
            ['product_id' => $products['chicken-adobo-rice'], 'name' => 'Extra Rice', 'price' => 15],
            ['product_id' => $products['pork-sisig-rice'], 'name' => 'Extra Egg', 'price' => 15],
            ['product_id' => $products['iced-spanish-latte'], 'name' => 'Extra Espresso Shot', 'price' => 25],
            ['product_id' => $products['crispy-fries'], 'name' => 'Cheese Dip', 'price' => 15],
        ];
        foreach ($addons as &$addon) {
            $addon += ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now];
        }
        $this->db->table('product_addons')->insertBatch($addons);
    }
}
