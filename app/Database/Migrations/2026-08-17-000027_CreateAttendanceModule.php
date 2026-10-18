<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateAttendanceModule extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'user_id'=>['type'=>'BIGINT','unsigned'=>true],
            'kind'=>['type'=>'ENUM','constraint'=>['in','out']],
            'at'=>['type'=>'DATETIME'],
            'method'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'dashboard'],
            'ip_address'=>['type'=>'VARCHAR','constraint'=>45,'null'=>true],
            'edited_by'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'edit_reason'=>['type'=>'TEXT','null'=>true],
        ]);
        $this->forge->addKey('id',true);$this->forge->addKey(['user_id','at']);
        $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('attendance_punches',true,['ENGINE'=>'InnoDB']);
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'user_id'=>['type'=>'BIGINT','unsigned'=>true],
            'weekday'=>['type'=>'TINYINT','unsigned'=>true],
            'start_time'=>['type'=>'TIME'],
            'end_time'=>['type'=>'TIME'],
            'grace_minutes'=>['type'=>'SMALLINT','unsigned'=>true,'default'=>5],
        ]);
        $this->forge->addKey('id',true);$this->forge->addUniqueKey(['user_id','weekday']);
        $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('attendance_shifts',true,['ENGINE'=>'InnoDB']);
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'user_id'=>['type'=>'BIGINT','unsigned'=>true],
            'hourly_rate'=>['type'=>'DECIMAL','constraint'=>'10,2','default'=>0],
            'effective_from'=>['type'=>'DATE'],
        ]);
        $this->forge->addKey('id',true);$this->forge->addKey(['user_id','effective_from']);
        $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('attendance_pay_rates',true,['ENGINE'=>'InnoDB']);
    }
    public function down(): void
    {
        $this->forge->dropTable('attendance_pay_rates',true);
        $this->forge->dropTable('attendance_shifts',true);
        $this->forge->dropTable('attendance_punches',true);
    }
}
