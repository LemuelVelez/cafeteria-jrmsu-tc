<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCashCounts extends Migration
{
    public function up(): void
    {
        // The nine denominations and manually entered credit details form a single, atomic dated count.
        // JSON avoids partially persisted denomination rows and preserves a complete auditable revision.
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'business_date'=>['type'=>'DATE'],
            'denominations_json'=>['type'=>'JSON'],
            'credit_entries_json'=>['type'=>'JSON'],
            'total_cash'=>['type'=>'DECIMAL','constraint'=>'12,2','default'=>0],
            'credit_total'=>['type'=>'DECIMAL','constraint'=>'12,2','default'=>0],
            'prepared_by'=>['type'=>'BIGINT','unsigned'=>true],
            'checked_by'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'checked_at'=>['type'=>'DATETIME','null'=>true],
            'created_at'=>['type'=>'DATETIME','null'=>true],
            'updated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey('business_date');
        $this->forge->createTable('cash_counts',true);
    }
    public function down(): void { $this->forge->dropTable('cash_counts',true); }
}
