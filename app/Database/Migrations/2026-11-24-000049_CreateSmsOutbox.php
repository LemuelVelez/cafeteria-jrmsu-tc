<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSmsOutbox extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE sms_outbox (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NULL,
            event VARCHAR(40) NOT NULL,
            message VARCHAR(480) NOT NULL,
            status ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            provider_message_id VARCHAR(64) NULL,
            last_error VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            UNIQUE KEY uq_sms_order_event (order_id,event),
            KEY idx_sms_status (status),
            CONSTRAINT fk_sms_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_sms_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if ($this->db->table('settings')->where('setting_key', 'sms_order_notifications')->countAllResults() === 0) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('settings')->insert([
                'setting_key' => 'sms_order_notifications', 'setting_value' => '1', 'setting_type' => 'string',
                'description' => 'Send customers SMS when an order is confirmed or ready.',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('sms_outbox', true);
        $this->db->table('settings')->where('setting_key', 'sms_order_notifications')->delete();
    }
}
