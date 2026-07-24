<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $rows = [
            ['name' => 'Rice Meals', 'slug' => 'rice-meals', 'description' => 'Campus favorites served with rice.', 'sort_order' => 1],
            ['name' => 'Snacks', 'slug' => 'snacks', 'description' => 'Quick bites between classes.', 'sort_order' => 2],
            ['name' => 'Coffee', 'slug' => 'coffee', 'description' => 'Hot and iced coffee drinks.', 'sort_order' => 3],
            ['name' => 'Cold Drinks', 'slug' => 'cold-drinks', 'description' => 'Refreshing juices and beverages.', 'sort_order' => 4],
        ];
        foreach ($rows as &$row) {
            $row += ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now];
        }
        $this->db->table('categories')->insertBatch($rows);
    }
}
