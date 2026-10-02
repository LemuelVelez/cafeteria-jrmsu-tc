<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeOrderStatuses extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','preparing','ready','ready_for_pickup','out_for_delivery','delivered','completed','cancelled') NOT NULL DEFAULT 'pending'");
        $this->db->query("UPDATE orders SET status = 'ready_for_pickup' WHERE status = 'ready'");
        $this->db->query("UPDATE orders SET status = 'completed' WHERE status = 'delivered'");
        $this->db->query("UPDATE order_status_history SET from_status = 'ready_for_pickup' WHERE from_status = 'ready'");
        $this->db->query("UPDATE order_status_history SET to_status = 'ready_for_pickup' WHERE to_status = 'ready'");
        $this->db->query("UPDATE order_status_history SET from_status = 'completed' WHERE from_status = 'delivered'");
        $this->db->query("UPDATE order_status_history SET to_status = 'completed' WHERE to_status = 'delivered'");
        $this->db->query("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','preparing','ready','ready_for_pickup','out_for_delivery','delivered','completed','cancelled') NOT NULL DEFAULT 'pending'");
        $this->db->query("UPDATE orders SET status = 'ready' WHERE status = 'ready_for_pickup'");
        $this->db->query("UPDATE orders SET status = 'delivered' WHERE status = 'completed'");
        $this->db->query("UPDATE order_status_history SET from_status = 'ready' WHERE from_status = 'ready_for_pickup'");
        $this->db->query("UPDATE order_status_history SET to_status = 'ready' WHERE to_status = 'ready_for_pickup'");
        $this->db->query("UPDATE order_status_history SET from_status = 'delivered' WHERE from_status = 'completed'");
        $this->db->query("UPDATE order_status_history SET to_status = 'delivered' WHERE to_status = 'completed'");
        $this->db->query("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending'");
    }
}
