<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCostTraceability extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('ingredients', [
            'grams_per_piece' => [
                'type' => 'DECIMAL', 'constraint' => '10,3', 'null' => true,
                'comment' => 'Required when a recipe uses pieces and nutrient_basis is 100g.',
            ],
        ]);
        $this->forge->addColumn('order_items', [
            'cost_status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'no_recipe'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('order_items', 'cost_status');
        $this->forge->dropColumn('ingredients', 'grams_per_piece');
    }
}
