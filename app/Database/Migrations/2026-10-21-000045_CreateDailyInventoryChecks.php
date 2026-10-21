<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDailyInventoryChecks extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'business_date'=>['type'=>'DATE'],
            'prepared_by'=>['type'=>'BIGINT','unsigned'=>true],
            'prepared_at'=>['type'=>'DATETIME'],
            'checked_by'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'checked_at'=>['type'=>'DATETIME','null'=>true],
            'checked_movement_id'=>['type'=>'BIGINT','unsigned'=>true,'default'=>0],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey('business_date');
        $this->forge->createTable('daily_inventory_checks',true);
    }
    public function down(): void { $this->forge->dropTable('daily_inventory_checks',true); }
}
