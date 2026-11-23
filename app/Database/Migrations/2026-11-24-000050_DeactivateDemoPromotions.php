<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DeactivateDemoPromotions extends Migration
{
    public function up(): void
    {
        $this->db->table('promos')->where('code', 'WELCOME10')->update(['is_active' => 0]);
    }

    public function down(): void
    {
        $this->db->table('promos')->where('code', 'WELCOME10')->update(['is_active' => 1]);
    }
}
