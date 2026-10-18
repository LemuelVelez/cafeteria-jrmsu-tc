<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateRecipesAndPreparation extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'name'=>['type'=>'VARCHAR','constraint'=>160],
            'purchase_unit'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'g'],
            'cost_per_unit'=>['type'=>'DECIMAL','constraint'=>'12,4','default'=>0],
            'supplier'=>['type'=>'VARCHAR','constraint'=>160,'null'=>true],
            'source_reference'=>['type'=>'VARCHAR','constraint'=>250,'null'=>true],
            'nutrient_basis'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'100g'],
            'calories'=>['type'=>'DECIMAL','constraint'=>'10,3','default'=>0],
            'protein_g'=>['type'=>'DECIMAL','constraint'=>'10,3','default'=>0],
            'carbohydrates_g'=>['type'=>'DECIMAL','constraint'=>'10,3','default'=>0],
            'fat_g'=>['type'=>'DECIMAL','constraint'=>'10,3','default'=>0],
            'sugar_g'=>['type'=>'DECIMAL','constraint'=>'10,3','default'=>0],
            'fiber_g'=>['type'=>'DECIMAL','constraint'=>'10,3','default'=>0],
            'sodium_mg'=>['type'=>'DECIMAL','constraint'=>'10,3','default'=>0],
            'created_at'=>['type'=>'DATETIME','null'=>true],
            'updated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true); $this->forge->addUniqueKey('name');
        $this->forge->createTable('ingredients', true, ['ENGINE'=>'InnoDB']);
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'ingredient_id'=>['type'=>'BIGINT','unsigned'=>true],
            'unit_cost'=>['type'=>'DECIMAL','constraint'=>'12,4'],
            'effective_at'=>['type'=>'DATETIME'],
            'changed_by'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true]
        ]);
        $this->forge->addKey('id',true); $this->forge->addKey(['ingredient_id','effective_at']);
        $this->forge->addForeignKey('ingredient_id','ingredients','id','CASCADE','CASCADE');
        $this->forge->createTable('ingredient_cost_history',true,['ENGINE'=>'InnoDB']);
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'product_id'=>['type'=>'BIGINT','unsigned'=>true],
            'ingredient_id'=>['type'=>'BIGINT','unsigned'=>true],
            'quantity'=>['type'=>'DECIMAL','constraint'=>'12,3'],
            'unit'=>['type'=>'VARCHAR','constraint'=>12,'default'=>'g'],
            'wastage_percent'=>['type'=>'DECIMAL','constraint'=>'5,2','default'=>0],
        ]);
        $this->forge->addKey('id',true); $this->forge->addUniqueKey(['product_id','ingredient_id']);
        $this->forge->addForeignKey('product_id','products','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('ingredient_id','ingredients','id','CASCADE','CASCADE');
        $this->forge->createTable('product_recipes',true,['ENGINE'=>'InnoDB']);
        $this->forge->addColumn('products',[
            'packaging_cost'=>['type'=>'DECIMAL','constraint'=>'10,2','default'=>0],
            'recipe_servings'=>['type'=>'SMALLINT','unsigned'=>true,'default'=>1],
            'cooking_yield'=>['type'=>'DECIMAL','constraint'=>'5,3','default'=>1],
            'nutrition_source'=>['type'=>'VARCHAR','constraint'=>24,'default'=>'manual'],
            'nutrition_status'=>['type'=>'VARCHAR','constraint'=>24,'default'=>'estimated'],
            'nutrition_verified_by'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'nutrition_verified_at'=>['type'=>'DATETIME','null'=>true],
            'is_vegetarian'=>['type'=>'TINYINT','constraint'=>1,'default'=>0],
        ]);
        $this->forge->addColumn('order_items',[
            'unit_cost'=>['type'=>'DECIMAL','constraint'=>'12,2','default'=>0],
            'prepared_at'=>['type'=>'DATETIME','null'=>true],
            'prepared_by'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
        ]);
        $this->forge->addColumn('orders',[
            'pickup_code_hash'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],
            'handoff_verified_by'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'handoff_verified_at'=>['type'=>'DATETIME','null'=>true],
            'handoff_method'=>['type'=>'VARCHAR','constraint'=>16,'null'=>true],
        ]);
    }
    public function down(): void
    {
        $this->forge->dropColumn('orders',['pickup_code_hash','handoff_verified_by','handoff_verified_at','handoff_method']);
        $this->forge->dropColumn('order_items',['unit_cost','prepared_at','prepared_by']);
        $this->forge->dropColumn('products',['packaging_cost','recipe_servings','cooking_yield','nutrition_source','nutrition_status','nutrition_verified_by','nutrition_verified_at','is_vegetarian']);
        $this->forge->dropTable('product_recipes',true);
        $this->forge->dropTable('ingredient_cost_history',true);
        $this->forge->dropTable('ingredients',true);
    }
}
