<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Add optional correction settings to upgraded databases without resetting admin choices. */
final class OperationalSettingsSeeder extends Seeder
{
    public const DEFAULTS = [
        'admin_totp_required' => '0',
        'attendance_enabled' => '0',
        'cost_overhead_percent' => '0',
        'target_food_cost_percent' => '35',
        'backup_keep_daily' => '7',
        'backup_keep_weekly' => '4',
        'backup_keep_monthly' => '6',
        'attendance_overtime_premium' => '0.25',
        'attendance_night_differential' => '0.10',
        'notification_read_retention_days' => '90',
        'inventory_ingredient_tracking_enabled' => '0',
        'low_stock_email_digest' => '0',
        'web_push_enabled' => '0',
        'inventory_level_yellow_percent' => '50',
        'inventory_level_red_percent' => '25',
        'inventory_alert_half_enabled' => '1',
        'queue_board_enabled' => '1',
        'queue_sound_enabled' => '1',
        'queue_voice_enabled' => '1',
        'queue_ready_hide_minutes' => '30',
        'queue_number_prefix' => '',
    ];

    public function run(): void
    {
        $table = $this->db->table('settings');
        $now = date('Y-m-d H:i:s');
        foreach (self::DEFAULTS as $name => $default) {
            if ($table->where('setting_key', $name)->countAllResults() > 0) continue;
            $table->insert([
                'setting_key' => $name,
                'setting_value' => $default,
                'setting_type' => 'string',
                'description' => 'Optional operational correction setting.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
