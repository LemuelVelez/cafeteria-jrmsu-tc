<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use App\Database\Seeds\OperationalSettingsSeeder;

final class AddOrderQueueNumbers extends Migration
{
    public function up(): void
    {
        // MySQL DDL commits implicitly. A failed deployment may therefore have
        // installed only some columns or indexes; rerunning must finish safely.
        $columns = [
            'queue_number' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'queue_date' => ['type' => 'DATE', 'null' => true],
            'ready_at' => ['type' => 'DATETIME', 'null' => true],
            'queue_called_at' => ['type' => 'DATETIME', 'null' => true],
            'queue_call_count' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
        ];
        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'orders')) {
                $this->forge->addColumn('orders', [$name => $definition]);
            }
        }

        $indexes = $this->db->query('SHOW INDEX FROM orders')->getResultArray();
        $indexNames = array_fill_keys(array_column($indexes, 'Key_name'), true);
        if (! isset($indexNames['uq_order_queue_daily'])) {
            $this->db->query('ALTER TABLE orders ADD UNIQUE KEY uq_order_queue_daily (queue_date, queue_number)');
        }
        if (! isset($indexNames['idx_order_queue_status'])) {
            $this->db->query('ALTER TABLE orders ADD INDEX idx_order_queue_status (queue_date, status)');
        }

        if (! $this->db->tableExists('queue_counters')) {
            $this->forge->addField([
                'queue_date' => ['type' => 'DATE'],
                'last_number' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('queue_date', true);
            $this->forge->createTable('queue_counters', true, ['ENGINE' => 'InnoDB']);
        }

        $now = date('Y-m-d H:i:s');
        foreach (OperationalSettingsSeeder::DEFAULTS as $key => $value) {
            if (! str_starts_with($key, 'queue_')) continue;
            if ($this->db->table('settings')->where('setting_key', $key)->countAllResults() !== 0) continue;
            $this->db->table('settings')->insert(['setting_key' => $key, 'setting_value' => $value,
                'setting_type' => 'string', 'description' => 'Queue display preference.',
                'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('queue_counters', true);
        $indexes = $this->db->query('SHOW INDEX FROM orders')->getResultArray();
        $indexNames = array_fill_keys(array_column($indexes, 'Key_name'), true);
        foreach (['uq_order_queue_daily', 'idx_order_queue_status'] as $index) {
            if (isset($indexNames[$index])) {
                $this->db->query('ALTER TABLE orders DROP INDEX ' . $index);
            }
        }
        foreach (['queue_number', 'queue_date', 'ready_at', 'queue_called_at', 'queue_call_count'] as $column) {
            if ($this->db->fieldExists($column, 'orders')) $this->forge->dropColumn('orders', $column);
        }
        // Do not erase settings: administrators may have changed these values.
    }
}
