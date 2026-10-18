<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class NutritionSearchCache extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'cache_key'=>['type'=>'VARCHAR','constraint'=>72],
            'response_json'=>['type'=>'LONGTEXT'],
            'cached_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('cache_key',true);
        $this->forge->createTable('nutrition_search_cache',true,['ENGINE'=>'InnoDB']);
    }
    public function down(): void {$this->forge->dropTable('nutrition_search_cache',true);}
}
