<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddOrderIssuesAndPreferences extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'order_id'=>['type'=>'BIGINT','unsigned'=>true],
            'user_id'=>['type'=>'BIGINT','unsigned'=>true],
            'reason'=>['type'=>'ENUM','constraint'=>['missing_item','wrong_item','quality']],
            'details'=>['type'=>'VARCHAR','constraint'=>500,'null'=>true],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);$this->forge->addKey('order_id');
        $this->forge->addForeignKey('order_id','orders','id','CASCADE','CASCADE');
        $this->forge->createTable('order_issues',true,['ENGINE'=>'InnoDB']);
        $this->forge->addColumn('users',['notification_email_prefs'=>['type'=>'JSON','null'=>true]]);
    }
    public function down(): void
    {
        $this->forge->dropColumn('users','notification_email_prefs');
        $this->forge->dropTable('order_issues',true);
    }
}
