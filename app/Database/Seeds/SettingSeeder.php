<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $rows = [
            ['setting_key' => 'cafeteria_name', 'setting_value' => 'JRMSU-TC Cafeteria', 'description' => 'Public cafeteria name.'],
            ['setting_key' => 'delivery_fee', 'setting_value' => '40.00', 'description' => 'Default delivery fee in PHP.'],
            ['setting_key' => 'operating_hours', 'setting_value' => 'Monday-Friday, 7:00 AM-6:00 PM', 'description' => 'Customer-facing operating hours.'],
            ['setting_key' => 'contact_number', 'setting_value' => '0917 000 0000', 'description' => 'Customer support number.'],
            ['setting_key' => 'pickup_enabled', 'setting_value' => '1', 'description' => 'Enable pickup orders.'],
            ['setting_key' => 'delivery_enabled', 'setting_value' => '1', 'description' => 'Enable delivery orders.'],
            ['setting_key' => 'admin_totp_required', 'setting_value' => '0', 'description' => 'Require admin two-factor authentication.'],
            ['setting_key' => 'attendance_enabled', 'setting_value' => '0', 'description' => 'Enable attendance and wage estimates only when panel approval is recorded.'],
            ['setting_key' => 'cost_overhead_percent', 'setting_value' => '0', 'description' => 'Overhead surcharge used in food costing.'],
            ['setting_key' => 'target_food_cost_percent', 'setting_value' => '35', 'description' => 'Target food cost percentage.'],
            ['setting_key' => 'email_order_notifications', 'setting_value' => '1', 'description' => 'Enable customer email notifications for important order statuses.'],
        ];
        $settings = $this->db->table('settings');

        foreach ($rows as $row) {
            if ($settings->where('setting_key', $row['setting_key'])->countAllResults() > 0) {
                continue;
            }

            $row += ['setting_type' => 'string', 'created_at' => $now, 'updated_at' => $now];
            $settings->insert($row);
        }
    }
}
