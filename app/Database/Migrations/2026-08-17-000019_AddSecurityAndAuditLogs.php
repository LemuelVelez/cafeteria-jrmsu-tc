<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSecurityAndAuditLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'failed_login_attempts' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0, 'after' => 'last_login_at'],
            'locked_until' => ['type' => 'DATETIME', 'null' => true, 'after' => 'failed_login_attempts'],
            'session_version' => ['type' => 'INT', 'unsigned' => true, 'default' => 1, 'after' => 'locked_until'],
        ]);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 80],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'entity_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'details_json' => ['type' => 'JSON', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addKey(['action', 'created_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('audit_logs', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_logs', true);
        $this->forge->dropColumn('users', ['failed_login_attempts', 'locked_until', 'session_version']);
    }
}
