<?php

namespace App\Database\Migrations;

use App\Database\Seeds\OperationalSettingsSeeder;
use CodeIgniter\Database\Migration;

final class AddInventoryLevelSettings extends Migration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (OperationalSettingsSeeder::DEFAULTS as $key => $value) {
            if (! str_starts_with($key, 'inventory_level_') && $key !== 'inventory_alert_half_enabled') continue;
            if ($this->db->table('settings')->where('setting_key', $key)->countAllResults() > 0) continue;
            $this->db->table('settings')->insert([
                'setting_key' => $key, 'setting_value' => $value, 'setting_type' => 'string',
                'description' => 'Product stock level preference.', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Retain administrator preferences, including values customized after deployment.
    }
}
