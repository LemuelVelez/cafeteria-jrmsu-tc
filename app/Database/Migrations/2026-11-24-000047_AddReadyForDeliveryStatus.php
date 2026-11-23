<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReadyForDeliveryStatus extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','preparing','ready_for_pickup','ready_for_delivery','out_for_delivery','completed','delivered','cancelled') NOT NULL DEFAULT 'pending'");
        $this->db->table('orders')->where('order_type', 'delivery')->where('status', 'ready_for_pickup')->update(['status' => 'ready_for_delivery']);
    }

    public function down(): void
    {
        $this->db->table('orders')->where('status', 'ready_for_delivery')->update(['status' => 'ready_for_pickup']);
        $this->db->query("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','delivered','cancelled') NOT NULL DEFAULT 'pending'");
    }
}
