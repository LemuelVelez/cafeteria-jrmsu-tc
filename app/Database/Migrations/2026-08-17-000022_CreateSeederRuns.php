<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSeederRuns extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'seeder_name' => [
                'type' => 'VARCHAR',
                'constraint' => 191,
            ],
            'file_hash' => [
                'type' => 'CHAR',
                'constraint' => 64,
                'null' => true,
            ],
            'run_mode' => [
                'type' => 'ENUM',
                'constraint' => ['executed', 'adopted_existing'],
                'default' => 'executed',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('seeder_name');
        $this->forge->createTable('seeder_runs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('seeder_runs', true);
    }
}
