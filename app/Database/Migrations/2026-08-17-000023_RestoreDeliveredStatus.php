<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class RestoreDeliveredStatus extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','delivered','cancelled') NOT NULL DEFAULT 'pending'");
        $this->db->query("UPDATE orders SET status='delivered' WHERE status='completed' AND order_type='delivery'");
        $this->db->query("UPDATE order_status_history h JOIN orders o ON o.id=h.order_id SET h.to_status='delivered' WHERE o.order_type='delivery' AND h.to_status='completed'");
        $this->db->query("UPDATE order_status_history h JOIN orders o ON o.id=h.order_id SET h.from_status='delivered' WHERE o.order_type='delivery' AND h.from_status='completed'");
    }
    public function down(): void
    {
        $this->db->query("UPDATE order_status_history h JOIN orders o ON o.id=h.order_id SET h.to_status='completed' WHERE o.order_type='delivery' AND h.to_status='delivered'");
        $this->db->query("UPDATE order_status_history h JOIN orders o ON o.id=h.order_id SET h.from_status='completed' WHERE o.order_type='delivery' AND h.from_status='delivered'");
        $this->db->query("UPDATE orders SET status='completed' WHERE status='delivered'");
        $this->db->query("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }
}
