<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PromoSeeder extends Seeder
{
    public function run(): void
    {
        $promos = $this->db->table('promos');
        if ($promos->where('code', 'WELCOME10')->countAllResults() > 0) {
            return;
        }

        $promos->insert([
            'code' => 'WELCOME10',
            'description' => '10% welcome discount for orders of at least ₱150.',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'minimum_order' => 150,
            'usage_limit' => 100,
            'used_count' => 0,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
