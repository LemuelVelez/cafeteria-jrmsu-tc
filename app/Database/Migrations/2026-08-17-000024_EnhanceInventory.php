<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class EnhanceInventory extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE inventory_movements MODIFY movement_type ENUM('opening','stock_in','stock_out','sale','cancel_restock','adjustment','waste') NOT NULL");
        $this->forge->addColumn('products', ['max_stock' => ['type'=>'INT','unsigned'=>true,'default'=>50,'after'=>'reorder_level']]);
    }
    public function down(): void
    {
        // Convert stock-out movements to equivalent adjustments before narrowing the enum.
        $this->db->query("UPDATE inventory_movements SET movement_type='adjustment' WHERE movement_type='stock_out'");
        $this->db->query("ALTER TABLE inventory_movements MODIFY movement_type ENUM('opening','stock_in','sale','cancel_restock','adjustment','waste') NOT NULL");
        $this->forge->dropColumn('products', 'max_stock');
    }
}
