<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCounterReportFields extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('products', [
            'standard_unit_cost' => ['type'=>'DECIMAL','constraint'=>'12,2','null'=>true],
            'stock_unit' => ['type'=>'VARCHAR','constraint'=>12,'default'=>'pcs'],
            'bundle_quantity' => ['type'=>'INT','unsigned'=>true,'null'=>true],
            'bundle_price' => ['type'=>'DECIMAL','constraint'=>'12,2','null'=>true],
        ]);
        $this->forge->addColumn('inventory_movements', ['reason_code'=>['type'=>'VARCHAR','constraint'=>40,'null'=>true]]);
    }
    public function down(): void
    {
        $this->forge->dropColumn('inventory_movements','reason_code');
        foreach (['standard_unit_cost','stock_unit','bundle_quantity','bundle_price'] as $column) $this->forge->dropColumn('products',$column);
    }
}
