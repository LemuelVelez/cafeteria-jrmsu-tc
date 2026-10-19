<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Remove unsourced sample nutrition claims from historical demo products.
 * Only exact, unchanged seed values are eligible; admin edits are preserved.
 * The old demo numbers are retained below exclusively to identify/undo this
 * change, NOT as nutrition data for new ingredients or products.
 */
final class ClearUnsourcedDemoNutrition extends Migration
{
    private const ORIGINAL = [
        'chicken-adobo-rice' => [480,27,58,15,6,4,760],
        'pork-sisig-rice' => [610,25,56,31,4,3,890],
        'fried-chicken-meal' => [650,32,64,29,3,3,980],
        'cheese-burger' => [430,21,38,22,7,2,720],
        'crispy-fries' => [365,5,52,15,1,5,310],
        'iced-spanish-latte' => [210,7,32,6,28,0,120],
        'hot-americano' => [10,1,2,0,0,0,10],
        'calamansi-juice' => [95,1,24,0,20,1,8],
    ];

    private const FIELDS = [
        'calories', 'protein_g', 'carbohydrates_g', 'fat_g',
        'sugar_g', 'fiber_g', 'sodium_mg',
    ];

    public function up(): void
    {
        // The journal is mandatory: a fresh install that seeds AFTER migration
        // has nothing to clear and must not gain old values on rollback.
        $this->forge->addField([
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 140],
        ]);
        $this->forge->addKey('product_id', true);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('demo_nutrition_resets', true, ['ENGINE' => 'InnoDB']);
        foreach (self::ORIGINAL as $slug => $values) {
            $builder = $this->eligible($slug, 'manual');
            foreach (self::FIELDS as $index => $field) {
                $builder->where($field, $values[$index]);
            }
            $original = $builder->select('products.id')->get()->getRowArray();
            if (! $original) continue;
            $id = (int) $original['id'];
            if ($this->db->table('product_recipes')->where('product_id', $id)->countAllResults()) continue;
            $this->db->table('demo_nutrition_resets')->insert(['product_id' => $id, 'slug' => $slug]);
            $this->db->table('products')->where('id', $id)->update(
                array_fill_keys(self::FIELDS, null) + ['nutrition_source' => 'unsourced']);
        }
    }

    public function down(): void
    {
        foreach ($this->db->table('demo_nutrition_resets')->get()->getResultArray() as $record) {
            $slug = (string) $record['slug'];
            if (! isset(self::ORIGINAL[$slug])) continue;
            $builder = $this->eligible($slug, 'unsourced')->where('products.id', $record['product_id']);
            foreach (self::FIELDS as $field) $builder->where($field, null);
            $current = $builder->select('products.id')->get()->getRowArray();
            if (! $current || $this->db->table('product_recipes')
                ->where('product_id', $record['product_id'])->countAllResults()) continue; // Preserve subsequent admin changes.
            $this->db->table('products')->where('id', $record['product_id'])->update(
                array_combine(self::FIELDS, self::ORIGINAL[$slug]) + ['nutrition_source' => 'manual']);
        }
        $this->forge->dropTable('demo_nutrition_resets', true);
    }

    private function eligible(string $slug, string $source): \CodeIgniter\Database\BaseBuilder
    {
        // Require the original status and absence of a recipe, to avoid
        // overwriting nutrition verified or recalculated after the initial seed.
        return $this->db->table('products')->where('slug', $slug)
            ->where('nutrition_source', $source)
            ->where('nutrition_status', 'estimated')
            ->where('nutrition_verified_by', null)
            ->where('nutrition_verified_at', null);
    }
}
