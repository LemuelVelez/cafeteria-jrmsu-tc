<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateWebPush extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'user_id'=>['type'=>'BIGINT','unsigned'=>true],
            'endpoint_hash'=>['type'=>'CHAR','constraint'=>64],
            'subscription_cipher'=>['type'=>'TEXT'],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);$this->forge->addUniqueKey('endpoint_hash');
        $this->forge->addKey('user_id');$this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('push_subscriptions',true,['ENGINE'=>'InnoDB']);
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'notification_id'=>['type'=>'BIGINT','unsigned'=>true],
            'attempts'=>['type'=>'TINYINT','unsigned'=>true,'default'=>0],
            'delivered_at'=>['type'=>'DATETIME','null'=>true],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);$this->forge->addUniqueKey('notification_id');
        $this->forge->addForeignKey('notification_id','notifications','id','CASCADE','CASCADE');
        $this->forge->createTable('push_outbox',true,['ENGINE'=>'InnoDB']);
    }
    public function down(): void {$this->forge->dropTable('push_outbox',true);$this->forge->dropTable('push_subscriptions',true);}
}
