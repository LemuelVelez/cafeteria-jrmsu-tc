<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryLedger extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('products', [
            'reorder_level' => ['type' => 'INT', 'unsigned' => true, 'default' => 5, 'after' => 'stock'],
        ]);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'movement_type' => ['type' => 'ENUM', 'constraint' => ['opening', 'stock_in', 'sale', 'cancel_restock', 'adjustment', 'waste']],
            'quantity' => ['type' => 'INT'],
            'stock_before' => ['type' => 'INT', 'unsigned' => true],
            'stock_after' => ['type' => 'INT', 'unsigned' => true],
            'order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'reference' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'note' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['product_id', 'created_at']);
        $this->forge->addKey('order_id');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('inventory_movements', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        $now = date('Y-m-d H:i:s');
        $this->db->query(
            "INSERT INTO inventory_movements (product_id, movement_type, quantity, stock_before, stock_after, note, created_at, updated_at) " .
            "SELECT id, 'opening', stock, 0, stock, 'Opening balance created by inventory ledger migration', ?, ? FROM products WHERE deleted_at IS NULL",
            [$now, $now],
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_movements', true);
        $this->forge->dropColumn('products', 'reorder_level');
    }
}
