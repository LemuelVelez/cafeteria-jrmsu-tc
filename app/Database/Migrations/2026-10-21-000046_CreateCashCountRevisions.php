<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCashCountRevisions extends Migration
{
    public function up(): void
    {
        // Keeps the required written correction explanation even though audit logs redact free-text reasons.
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'cash_count_id'=>['type'=>'BIGINT','unsigned'=>true],
            'reopened_by'=>['type'=>'BIGINT','unsigned'=>true],
            'reason'=>['type'=>'TEXT'],
            'prior_checked_by'=>['type'=>'BIGINT','unsigned'=>true],
            'prior_checked_at'=>['type'=>'DATETIME'],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addKey('cash_count_id');
        $this->forge->addForeignKey('cash_count_id','cash_counts','id','CASCADE','CASCADE');
        $this->forge->createTable('cash_count_revisions',true);
    }
    public function down(): void { $this->forge->dropTable('cash_count_revisions',true); }
}
