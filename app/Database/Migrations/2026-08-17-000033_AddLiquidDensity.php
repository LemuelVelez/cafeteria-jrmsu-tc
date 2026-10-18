<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Liquids are priced by volume but USDA nutrient labels describe 100 g of food. */
final class AddLiquidDensity extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('ingredients', [
            'density_g_per_ml' => [
                'type' => 'DECIMAL', 'constraint' => '8,4', 'default' => '1.0000',
                'comment' => 'Required conversion from mL of liquid to g for nutrition.',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('ingredients', 'density_g_per_ml');
    }
}
