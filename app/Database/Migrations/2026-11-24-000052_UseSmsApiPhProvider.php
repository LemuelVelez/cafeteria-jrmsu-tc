<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UseSmsApiPhProvider extends Migration
{
    public function up(): void
    {
        $this->db->table('settings')->where('setting_key', 'sms_order_notifications')->update([
            'description' => 'Order SMS through SMS API PH.',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->db->table('settings')->where('setting_key', 'sms_order_notifications')->update([
            'description' => 'Order SMS (disabled).',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
