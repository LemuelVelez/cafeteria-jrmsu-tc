<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Keep the original free-text allergen column for reversible migration and unmapped data. */
final class CreateStructuredAllergens extends Migration
{
    public const ALLERGENS = [
        'milk' => 'Milk', 'egg' => 'Egg', 'fish' => 'Fish',
        'crustacean_shellfish' => 'Crustacean shellfish', 'molluscs' => 'Molluscs',
        'peanuts' => 'Peanuts', 'tree_nuts' => 'Tree nuts',
        'wheat_gluten' => 'Wheat / gluten', 'soy' => 'Soy',
        'sesame' => 'Sesame', 'sulphites' => 'Sulphites',
    ];

    public static function normalize(string $name): ?string
    {
        $key = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        return match ($key) {
            'dairy', 'lactose' => 'milk', 'eggs' => 'egg', 'fishes' => 'fish',
            'shrimp', 'prawn', 'crab', 'lobster', 'shellfish' => 'crustacean_shellfish',
            'clam', 'oyster', 'mussel', 'squid' => 'molluscs',
            'peanut', 'groundnut', 'groundnuts' => 'peanuts',
            'almond', 'cashew', 'walnut', 'nuts' => 'tree_nuts',
            'wheat', 'gluten', 'flour' => 'wheat_gluten',
            'soya', 'soybeans', 'soybean' => 'soy',
            'sesame seeds' => 'sesame', 'sulfites', 'sulphite', 'sulfite' => 'sulphites',
            default => array_key_exists(str_replace(' ', '_', $key), self::ALLERGENS)
                ? str_replace(' ', '_', $key) : null,
        };
    }

    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 48],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'sort_order' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('allergens', true, ['ENGINE' => 'InnoDB']);
        $index = 0;
        foreach (self::ALLERGENS as $code => $name) {
            $this->db->table('allergens')->insert(['code' => $code, 'name' => $name, 'sort_order' => ++$index]);
        }
        $this->forge->addField([
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'allergen_id' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['product_id', 'allergen_id'], true);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('allergen_id', 'allergens', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('product_allergens', true, ['ENGINE' => 'InnoDB']);

        $ids = array_column($this->db->table('allergens')->get()->getResultArray(), 'id', 'code');
        foreach ($this->db->table('products')->select('id,slug,allergens')->get()->getResultArray() as $product) {
            $seen = [];
            foreach (explode(',', (string) ($product['allergens'] ?? '')) as $token) {
                if (trim($token) === '') continue;
                $code = self::normalize($token);
                if ($code === null) {
                    // Preserve the source column; it remains visible on the admin form.
                    log_message('warning', 'Unmapped allergen on {slug}: {token}', ['slug' => $product['slug'], 'token' => $token]);
                    if (is_cli()) echo 'Unmapped allergen (' . $product['slug'] . '): ' . $token . PHP_EOL;
                    continue;
                }
                if (!isset($seen[$code])) {
                    $this->db->table('product_allergens')->insert(['product_id' => $product['id'], 'allergen_id' => $ids[$code]]);
                    $seen[$code] = true;
                }
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('product_allergens', true);
        $this->forge->dropTable('allergens', true);
    }
}
