<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductBarcodeAndNutrition extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('products', [
            'sku' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'slug'],
            'barcode' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'sku'],
            'serving_size' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'description'],
            'calories' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true, 'after' => 'serving_size'],
            'protein_g' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true, 'after' => 'calories'],
            'carbohydrates_g' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true, 'after' => 'protein_g'],
            'fat_g' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true, 'after' => 'carbohydrates_g'],
            'sugar_g' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true, 'after' => 'fat_g'],
            'fiber_g' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true, 'after' => 'sugar_g'],
            'sodium_mg' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true, 'after' => 'fiber_g'],
            'allergens' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'sodium_mg'],
            'is_healthy_choice' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'allergens'],
        ]);
        $this->db->query('ALTER TABLE products ADD UNIQUE KEY products_sku_unique (sku)');
        $this->db->query('ALTER TABLE products ADD UNIQUE KEY products_barcode_unique (barcode)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE products DROP INDEX products_sku_unique');
        $this->db->query('ALTER TABLE products DROP INDEX products_barcode_unique');
        $this->forge->dropColumn('products', [
            'sku', 'barcode', 'serving_size', 'calories', 'protein_g', 'carbohydrates_g', 'fat_g', 'sugar_g', 'fiber_g', 'sodium_mg', 'allergens', 'is_healthy_choice',
        ]);
    }
}
