<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateIngredientInventory extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('ingredients', [
            'stock'=>['type'=>'DECIMAL','constraint'=>'14,3','default'=>0],
            'reorder_level'=>['type'=>'DECIMAL','constraint'=>'14,3','default'=>0],
        ]);
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'ingredient_id'=>['type'=>'BIGINT','unsigned'=>true],
            'movement_type'=>['type'=>'VARCHAR','constraint'=>24],
            'quantity'=>['type'=>'DECIMAL','constraint'=>'14,3'],
            'stock_before'=>['type'=>'DECIMAL','constraint'=>'14,3'],
            'stock_after'=>['type'=>'DECIMAL','constraint'=>'14,3'],
            'order_id'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'user_id'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'note'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addKey(['ingredient_id','created_at']);
        $this->forge->addUniqueKey(['order_id','ingredient_id','movement_type']);
        $this->forge->addForeignKey('ingredient_id','ingredients','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('order_id','orders','id','CASCADE','CASCADE');
        $this->forge->createTable('ingredient_movements',true,['ENGINE'=>'InnoDB']);
    }
    public function down(): void
    {
        $this->forge->dropTable('ingredient_movements',true);
        $this->forge->dropColumn('ingredients',['stock','reorder_level']);
    }
}
