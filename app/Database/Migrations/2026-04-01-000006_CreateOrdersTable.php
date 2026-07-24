<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrdersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'order_number' => ['type' => 'VARCHAR', 'constraint' => 40],
            'customer_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'cashier_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'rider_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'promo_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'order_type' => ['type' => 'ENUM', 'constraint' => ['pickup', 'delivery'], 'default' => 'pickup'],
            'status' => ['type' => 'ENUM', 'constraint' => ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'], 'default' => 'pending'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'discount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'delivery_fee' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'total' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'payment_method' => ['type' => 'ENUM', 'constraint' => ['cash_on_pickup', 'cash_on_delivery'], 'default' => 'cash_on_pickup'],
            'payment_status' => ['type' => 'ENUM', 'constraint' => ['pending', 'paid', 'failed', 'refunded'], 'default' => 'pending'],
            'delivery_address' => ['type' => 'TEXT', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('order_number');
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addForeignKey('customer_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('cashier_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('rider_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('promo_id', 'promos', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('orders', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);
    }

    public function down(): void
    {
        $this->forge->dropTable('orders', true);
    }
}
