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

            ['setting_key' => 'backup_keep_daily', 'setting_value' => '7', 'description' => 'Backups retained by day.'],
            ['setting_key' => 'backup_keep_weekly', 'setting_value' => '4', 'description' => 'Backups retained by week.'],
            ['setting_key' => 'backup_keep_monthly', 'setting_value' => '6', 'description' => 'Backups retained by month.'],
            ['setting_key' => 'attendance_overtime_premium', 'setting_value' => '0.25', 'description' => 'Additional pay fraction for overtime; not a statutory rule.'],
            ['setting_key' => 'attendance_night_differential', 'setting_value' => '0.10', 'description' => 'Additional pay fraction for night work; not a statutory rule.'],
            ['setting_key' => 'notification_read_retention_days', 'setting_value' => '90', 'description' => 'Days to keep read notifications.'],
            ['setting_key' => 'inventory_ingredient_tracking_enabled', 'setting_value' => '0', 'description' => 'Deduct ingredient stock from sale recipes; enable only after stocking ingredients.'],
            ['setting_key' => 'web_push_enabled', 'setting_value' => '0', 'description' => 'Optional Web Push notifications; keys are generated locally and stored encrypted.'],
            ['setting_key' => 'low_stock_email_digest', 'setting_value' => '0', 'description' => 'Enable the optional daily low-stock email digest.'],

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
