<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Allergen reference data and product link updates; the legacy text remains for rollback. */
final class AllergenService
{
    public function __construct(private readonly ?BaseConnection $connection = null) {}

    private function db(): BaseConnection { return $this->connection ?? db_connect(); }

    public function options(): array
    {
        return $this->db()->table('allergens')->orderBy('sort_order')->get()->getResultArray();
    }

    /** Batch hydrate product cards without N+1 queries. */
    public function attach(array &$products): void
    {
        if ($products === []) return;
        $ids = array_map('intval', array_column($products, 'id'));
        $rows = $this->db()->table('product_allergens pa')
            ->select('pa.product_id,a.id,a.code,a.name')->join('allergens a', 'a.id=pa.allergen_id')
            ->whereIn('pa.product_id', $ids)->orderBy('a.sort_order')->get()->getResultArray();
        $byProduct = [];
        foreach ($rows as $row) $byProduct[(int) $row['product_id']][] = $row;
        // Verified claims display the verifier's actual name, not an unverified label.
        $verifierIds = array_values(array_unique(array_filter(array_map('intval',
            array_column($products, 'nutrition_verified_by')))));
        $verifiers = $verifierIds ? array_column($this->db()->table('users')->select('id,name')
            ->whereIn('id', $verifierIds)->get()->getResultArray(), 'name', 'id') : [];
        // Present only sourced recipe references, never infer provenance from nutrient values.
        $sources = [];
        $demoRecipes = [];
        foreach ($this->db()->table('product_recipes r')->select('r.product_id,i.name,i.source_reference')
            ->join('ingredients i', 'i.id=r.ingredient_id')
            ->whereIn('r.product_id', $ids)->get()->getResultArray() as $row) {
            $source = trim((string) ($row['source_reference'] ?? ''));
            if ($source !== '') $sources[(int)$row['product_id']][$source] = $source;
            if (str_starts_with((string) $row['name'], 'DEMO ONLY - ')) {
                $demoRecipes[(int) $row['product_id']] = true;
            }
        }
        foreach ($products as &$product) {
            $product['nutrition_verifier_name'] = $verifiers[(int) ($product['nutrition_verified_by'] ?? 0)] ?? null;
            $product['is_demo_recipe'] = isset($demoRecipes[(int) $product['id']]);
            // A manually edited estimate must not claim the ingredients' USDA/PhilFCT
            // sources as evidence for those manually entered totals.
            $recipeDerived = in_array((string) ($product['nutrition_source'] ?? ''),
                ['recipe_sourced', 'recipe_unverified'], true);
            $product['nutrition_sources'] = $recipeDerived
                ? array_values($sources[(int)$product['id']] ?? []) : [];
            $product['structured_allergens'] = $byProduct[(int) $product['id']] ?? [];
            $product['allergen_names'] = array_column($product['structured_allergens'], 'name');
            $product['allergen_ids'] = array_map('intval', array_column($product['structured_allergens'], 'id'));
        }
        unset($product);
    }

    /** Validates and replaces pivot links using the caller's open transaction. */
    public function sync(int $productId, array $ids): array
    {
        $ids = array_unique(array_map('intval', $ids));
        if (count(array_filter($ids, static fn ($id) => $id <= 0)) > 0) {
            throw new \DomainException('Invalid allergen selected.');
        }
        sort($ids);
        $valid = $ids ? $this->db()->table('allergens')->whereIn('id', $ids)->countAllResults() : 0;
        if ($valid !== count($ids)) throw new \DomainException('Unrecognized allergen selected.');
        $this->db()->table('product_allergens')->where('product_id', $productId)->delete();
        foreach ($ids as $id) $this->db()->table('product_allergens')->insert(['product_id' => $productId, 'allergen_id' => $id]);
        return $ids;
    }
}
