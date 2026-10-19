<?php

namespace Tests\Database;

use App\Database\Seeds\DatabaseSeeder;
use App\Services\DemoProductNutritionCatalog;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

/** Runs on MySQL; detects differences between database rows and signed CSVs. */
#[Group('database')]
final class IndependentDemoProductSeedersDatabaseTest extends DatabaseTestCase
{
    public function testIndependentSeedersAreIdempotentOrUnapprovedAndUnrecorded(): void
    {
        $db = db_connect('tests');
        foreach (DemoProductNutritionCatalog::SEEDERS as $name => $slug) {
            $status = DemoProductNutritionCatalog::status($slug);
            if (! $status['ready']) {
                // No fake data: an unapproved product cannot consume its
                // one-time registry marker or silently gain a recipe.
                self::assertSame(0, $db->table('seeder_runs')->where('seeder_name', $name)->countAllResults());
                self::assertFalse(DatabaseSeeder::isSatisfied($name, $db));
                continue;
            }
            $runner = \Config\Database::seeder();
            $runner->call($name);
            self::assertTrue(DatabaseSeeder::isSatisfied($name, $db), $slug);
            $product = $db->table('products')->where('slug', $slug)->get()->getRowArray();
            self::assertSame('estimated', $product['nutrition_status']);
            $rows = $db->table('product_recipes')->where('product_id', $product['id'])->countAllResults();
            $auditRows = $db->table('audit_logs')->where([
                'entity_type' => 'product', 'entity_id' => $product['id'], 'action' => 'sourced_demo_recipe_seeded',
            ])->countAllResults();
            $runner->call($name);
            self::assertSame($rows, $db->table('product_recipes')->where('product_id', $product['id'])->countAllResults());
            self::assertSame($auditRows, $db->table('audit_logs')->where([
                'entity_type' => 'product', 'entity_id' => $product['id'], 'action' => 'sourced_demo_recipe_seeded',
            ])->countAllResults());
        }
    }
}
