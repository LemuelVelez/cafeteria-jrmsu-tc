<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Stage verification evidence is immutable; orders.handoff_* remains last-stage compatibility data. */
final class CreateOrderHandoffs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'order_id'=>['type'=>'BIGINT','unsigned'=>true],
            'stage'=>['type'=>'ENUM','constraint'=>['pickup','dispatch','delivery']],
            'verified_by'=>['type'=>'BIGINT','unsigned'=>true],
            'rider_id'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'method'=>['type'=>'ENUM','constraint'=>['qr','pickup_code','delivery_code']],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey(['order_id','stage']);
        $this->forge->addForeignKey('order_id','orders','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('verified_by','users','id','RESTRICT','RESTRICT');
        $this->forge->addForeignKey('rider_id','users','id','RESTRICT','RESTRICT');
        $this->forge->createTable('order_handoffs',true,['ENGINE'=>'InnoDB']);
        $this->forge->addColumn('orders',[
            'delivery_code_hash'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('orders','delivery_code_hash');
        $this->forge->dropTable('order_handoffs',true);
    }
}
