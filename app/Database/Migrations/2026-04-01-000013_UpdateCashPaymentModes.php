<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateCashPaymentModes extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('orders') || ! $this->db->tableExists('payments')) {
            return;
        }

        $this->db->query("UPDATE orders SET order_type = 'pickup' WHERE order_type = 'dine_in'");
        $this->db->query("ALTER TABLE orders MODIFY order_type ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup'");
        $this->db->query("ALTER TABLE orders MODIFY payment_method ENUM('cash','gcash','card','cash_on_pickup','cash_on_delivery') NOT NULL DEFAULT 'cash_on_pickup'");
        $this->db->query("UPDATE orders SET payment_method = CASE WHEN order_type = 'delivery' THEN 'cash_on_delivery' ELSE 'cash_on_pickup' END");
        $this->db->query("ALTER TABLE orders MODIFY payment_method ENUM('cash_on_pickup','cash_on_delivery') NOT NULL DEFAULT 'cash_on_pickup'");

        $this->db->query("ALTER TABLE payments MODIFY method ENUM('cash','gcash','card','cash_on_pickup','cash_on_delivery') NOT NULL");
        $this->db->query("UPDATE payments p INNER JOIN orders o ON o.id = p.order_id SET p.method = o.payment_method");
        $this->db->query("ALTER TABLE payments MODIFY method ENUM('cash_on_pickup','cash_on_delivery') NOT NULL");
    }

    public function down(): void
    {
        if (! $this->db->tableExists('orders') || ! $this->db->tableExists('payments')) {
            return;
        }

        $this->db->query("ALTER TABLE orders MODIFY payment_method ENUM('cash_on_pickup','cash_on_delivery','cash') NOT NULL DEFAULT 'cash'");
        $this->db->query("UPDATE orders SET payment_method = 'cash'");
        $this->db->query("ALTER TABLE orders MODIFY payment_method ENUM('cash','gcash','card') NOT NULL DEFAULT 'cash'");
        $this->db->query("ALTER TABLE orders MODIFY order_type ENUM('dine_in','pickup','delivery') NOT NULL DEFAULT 'pickup'");

        $this->db->query("ALTER TABLE payments MODIFY method ENUM('cash_on_pickup','cash_on_delivery','cash') NOT NULL");
        $this->db->query("UPDATE payments SET method = 'cash'");
        $this->db->query("ALTER TABLE payments MODIFY method ENUM('cash','gcash','card') NOT NULL");
    }
}
