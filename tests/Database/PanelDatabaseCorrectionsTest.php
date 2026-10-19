<?php

namespace Tests\Database;

use App\Models\ProductModel;
use App\Services\BarcodeService;
use App\Services\CostingService;
use App\Services\InventoryService;
use App\Services\StockCardService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

/** Real MySQL integration checks; these never silently run on SQLite. */
#[Group('database')]
final class PanelDatabaseCorrectionsTest extends DatabaseTestCase
{
    public function testFreshSeedHasEightValidBarcodesAndLedgeredOpeningStock(): void
    {
        $rows = db_connect('tests')->table('products')->select('id,slug,barcode,stock')->get()->getResultArray();
        self::assertCount(8, $rows);
        foreach ($rows as $row) {
            self::assertTrue(BarcodeService::valid($row['barcode']), $row['slug']);
            $balance = (float) (db_connect('tests')->table('inventory_movements')
                ->selectSum('quantity')->where('product_id', (int) $row['id'])->get()->getRow('quantity') ?? 0);
            self::assertEqualsWithDelta((float) $row['stock'], $balance, 0.001);
            $hasRecipe = db_connect('tests')->table('product_recipes')
                ->where('product_id', $row['id'])->countAllResults() > 0;
            self::assertSame($hasRecipe ? 'complete' : 'no_recipe',
                (new CostingService(db_connect('tests')))->calculate((int) $row['id'])['cost_status']);
        }
    }

    public function testSeededAllergensAreNormalizedAfterFreshMigrateBeforeSeed(): void
    {
        $db = db_connect('tests');
        $sisig = $db->table('products')->select('id')->where('slug', 'pork-sisig-rice')->get()->getRow('id');
        $codes = $db->table('product_allergens pa')->select('a.code')
            ->join('allergens a', 'a.id=pa.allergen_id')
            ->where('pa.product_id', $sisig)->get()->getResultArray();
        self::assertEqualsCanonicalizing(['soy', 'egg'], array_column($codes, 'code'));
        self::assertNotContains('pork-sisig-rice', array_column((new ProductModel())->menu(null, null, null, 'egg'), 'slug'));
    }

    public function testEggAllergenExclusionDoesNotAccidentallyFilterEggplant(): void
    {
        $db = db_connect('tests');
        $category = (int) $db->table('categories')->select('id')->get(1)->getRow('id');
        $db->table('products')->insert([
            'category_id' => $category, 'name' => 'Eggplant Fixture', 'slug' => 'eggplant-fixture',
            'description' => 'Fixture with no egg allergen', 'price' => 50, 'stock' => 0,
            'allergens' => 'eggplant', 'is_available' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        (new InventoryService($db))->opening((int) $db->insertID(), 3);
        $slugs = array_column((new ProductModel())->menu(null, null, null, 'egg'), 'slug');
        self::assertContains('eggplant-fixture', $slugs);
        self::assertNotContains('pork-sisig-rice', $slugs);
    }

    public function testStockCardPaginatesSixtyMovementsWithMatchingBalance(): void
    {
        $db = db_connect('tests');
        $product = $db->table('products')->select('id,stock')->where('slug', 'crispy-fries')->get()->getRowArray();
        $id = (int) $product['id'];
        $inventory = new InventoryService($db);
        for ($n = 0; $n < 60; $n++) $inventory->stockIn($id, 1, null, 'TEST-' . $n);
        $today = date('Y-m-d');
        $card = (new StockCardService($db))->get('product', $id, $today, $today, '', 1, 25);
        self::assertCount(25, $card['rows']);
        $pageTwo = (new StockCardService($db))->get('product', $id, $today, $today, '', 2, 25);
        $pageThree = (new StockCardService($db))->get('product', $id, $today, $today, '', 3, 25);
        self::assertCount(25, $pageTwo['rows']);
        self::assertNotEmpty($pageThree['rows']);
        self::assertSame([], array_intersect(
            array_column($card['rows'], 'id'), array_column($pageTwo['rows'], 'id'),
        ));
        self::assertSame($card['opening'], $pageTwo['opening']);
        self::assertSame($card['closing'], $pageTwo['closing']);
        self::assertGreaterThanOrEqual(60, $card['total']);
        self::assertEqualsWithDelta((int) $product['stock'] + 60, (float) $card['closing'], 0.001);
        self::assertEqualsWithDelta((float) $card['closing'], (float) $db->table('products')
            ->select('stock')->where('id', $id)->get()->getRow('stock'), 0.001);
    }
}
