<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNutritionReference extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('products', [
            'nutrition_reference' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'nutrition_source'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('products', 'nutrition_reference');
    }
}
