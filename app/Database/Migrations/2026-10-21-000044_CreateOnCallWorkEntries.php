<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOnCallWorkEntries extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'staff_name'=>['type'=>'VARCHAR','constraint'=>150],
            'work_date'=>['type'=>'DATE'],
            'hours_rendered'=>['type'=>'INT','unsigned'=>true],
            'additional_minutes'=>['type'=>'TINYINT','unsigned'=>true],
            'hourly_rate'=>['type'=>'DECIMAL','constraint'=>'12,2'],
            'total_payment'=>['type'=>'DECIMAL','constraint'=>'12,2'],
            'remarks'=>['type'=>'TEXT','null'=>true],
            'recorded_by'=>['type'=>'BIGINT','unsigned'=>true],
            'created_at'=>['type'=>'DATETIME','null'=>true],
            'updated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addKey('work_date');
        $this->forge->createTable('on_call_work_entries',true);
        // No fabricated rate. An administrator must approve and enter it first.
        $this->db->table('settings')->ignore(true)->insert(['setting_key'=>'on_call_hourly_rate','setting_value'=>'','description'=>'Approved on-call hourly pay (PHP). Blank means not approved.','setting_type'=>'string','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
    }
    public function down(): void
    {
        $this->forge->dropTable('on_call_work_entries',true);
        $this->db->table('settings')->where('setting_key','on_call_hourly_rate')->delete();
    }
}
