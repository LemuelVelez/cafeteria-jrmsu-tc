<?php

namespace App\Database\Seeds;

use App\Services\IngredientInventoryService;
use App\Services\SourcedRecipeCatalog;
use CodeIgniter\Database\Seeder;

/** Imports only team-supplied, sourced nutrition records; never invents data. */
final class IngredientSeeder extends Seeder
{
    public static function rows(): array
    {
        $path = APPPATH . 'Database/Seeds/data/ingredients.csv';
        return array_map([SourcedRecipeCatalog::class, 'ingredient'],
            SourcedRecipeCatalog::read($path, SourcedRecipeCatalog::INGREDIENT_COLUMNS));
    }

    public function run(): void
    {
        // Validate all rows first; partial imports from malformed CSV are forbidden.
        $rows = self::rows();
        $names = [];
        foreach ($rows as $row) {
            $key = mb_strtolower($row['name']);
            if (isset($names[$key])) throw new \DomainException('Duplicate ingredient in CSV: ' . $row['name']);
            $names[$key] = true;
        }
        $this->db->transBegin();
        try {
            $inventory = new IngredientInventoryService($this->db);
            foreach ($rows as $row) {
                $opening = $row['opening_stock'];
                unset($row['opening_stock']);
                if ($this->db->table('ingredients')->where('name', $row['name'])->countAllResults()) {
                    continue; // Existing/admin-managed ingredient: never update or post stock.
                }
                $row['stock'] = 0;
                $row['created_at'] = $row['updated_at'] = date('Y-m-d H:i:s');
                if (! $this->db->table('ingredients')->insert($row)) {
                    throw new \RuntimeException('Could not insert sourced ingredient.');
                }
                $id = (int) $this->db->insertID();
                $this->db->table('ingredient_cost_history')->insert([
                    'ingredient_id' => $id, 'unit_cost' => $row['cost_per_unit'],
                    'effective_at' => date('Y-m-d H:i:s'), 'changed_by' => null,
                ]);
                if ($opening > 0) $inventory->opening($id, $opening);
            }
            if (! $this->db->transStatus()) throw new \RuntimeException('Ingredient seed transaction failed.');
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }
}
