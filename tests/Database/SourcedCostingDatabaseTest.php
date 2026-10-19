<?php

namespace Tests\Database;

use App\Services\CostingService;
use App\Services\IngredientInventoryService;
use App\Services\StockCardService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

/** Uses synthetic fixtures in the disposable MySQL test schema, never demo seed data. */
#[Group('database')]
final class SourcedCostingDatabaseTest extends DatabaseTestCase
{
    public function testOpeningStockIsLedgeredOnceAndStockCardHandlesEmptyCatalog(): void
    {
        $db = db_connect('tests');
        $today = date('Y-m-d');
        $empty = (new StockCardService($db))->get('ingredient', 0, $today, $today);
        self::assertSame([], $empty['rows']);
        self::assertSame(0.0, $empty['opening']);
        $db->table('ingredients')->insert([
            'name' => 'Synthetic ledger ' . bin2hex(random_bytes(4)),
            'purchase_unit' => 'g', 'cost_per_unit' => 0.1,
            'nutrient_basis' => '100g', 'density_g_per_ml' => 1, 'stock' => 0,
        ]);
        $id = (int) $db->insertID();
        $ledger = new IngredientInventoryService($db);
        $db->transBegin();
        try {
            $ledger->opening($id, 18);
            $db->transCommit();
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
        self::assertEqualsWithDelta(18, (float) $db->table('ingredients')
            ->select('stock')->where('id', $id)->get()->getRow('stock'), 0.001);
        self::assertSame(1, $db->table('ingredient_movements')->where([
            'ingredient_id' => $id, 'movement_type' => 'opening',
        ])->countAllResults());
        $this->expectException(\DomainException::class);
        $ledger->opening($id, 18);
    }

    public function testCostingUsesMeasuredPieceWeightButNotCookingLossForNutrients(): void
    {
        $db = db_connect('tests');
        $product = $db->table('products')->where('slug', 'crispy-fries')->get()->getRowArray();
        $productId = (int) $product['id'];
        $db->table('product_recipes')->where('product_id', $productId)->delete(); // fixture isolation
        $db->table('ingredients')->insert([
            'name' => 'Synthetic piece ' . bin2hex(random_bytes(4)),
            'purchase_unit' => 'pcs', 'cost_per_unit' => 8,
            'nutrient_basis' => '100g', 'grams_per_piece' => 50,
            'density_g_per_ml' => 1, 'calories' => 200, 'protein_g' => 12,
            'carbohydrates_g' => 20, 'fat_g' => 4, 'sugar_g' => 2,
            'fiber_g' => 1, 'sodium_mg' => 100,
        ]);
        $ingredientId = (int) $db->insertID();
        $db->table('product_recipes')->insert([
            'product_id' => $productId, 'ingredient_id' => $ingredientId,
            'quantity' => 4, 'unit' => 'pcs', 'wastage_percent' => 0,
        ]);
        $db->table('products')->where('id', $productId)->update([
            'recipe_servings' => 2, 'cooking_yield' => 0.5, 'packaging_cost' => 2,
        ]);
        $costing = (new CostingService($db))->calculate($productId);
        self::assertSame('complete', $costing['cost_status']);
        // 4 pcs x 50g = 200g; 200 calories/100g / 2 servings = 200 calories.
        self::assertEqualsWithDelta(200, $costing['nutrients']['calories'], 0.001);
        self::assertEqualsWithDelta(12, $costing['nutrients']['protein_g'], 0.001);
        // Four units * PHP 8 / two portions, plus PHP 2 packaging each.
        self::assertEqualsWithDelta(18, $costing['unit_cost'], 0.001);
    }
    public function testOrderSnapshotsCostAndLaterIngredientPriceChangesDoNotRewriteIt(): void
    {
        $db = db_connect('tests');
        $productId = (int) $db->table('products')->where('slug', 'crispy-fries')
            ->select('id')->get()->getRow('id');
        $db->table('product_recipes')->where('product_id', $productId)->delete(); // fixture isolation
        $db->table('ingredients')->insert([
            'name' => 'Synthetic snapshot ' . bin2hex(random_bytes(5)),
            'purchase_unit' => 'g', 'cost_per_unit' => 0.05,
            'nutrient_basis' => '100g', 'density_g_per_ml' => 1,
        ]);
        $ingredientId = (int) $db->insertID();
        $db->table('product_recipes')->insert([
            'product_id' => $productId, 'ingredient_id' => $ingredientId,
            'quantity' => 100, 'unit' => 'g', 'wastage_percent' => 0,
        ]);
        $before = (new CostingService($db))->calculate($productId);
        self::assertSame('complete', $before['cost_status']);
        $customer = $this->makeCustomer();
        $created = $this->placeOrder([
            'items' => [['product_id' => $productId, 'quantity' => 1]],
            'order_type' => 'pickup', 'request_token' => bin2hex(random_bytes(16)),
        ], ['id' => $customer, 'role' => 'customer']);
        $line = $db->table('order_items')->where('order_id', $created['id'])->get()->getRowArray();
        self::assertSame('complete', $line['cost_status']);
        self::assertEqualsWithDelta($before['unit_cost'], (float) $line['unit_cost'], 0.001);
        $db->table('ingredients')->where('id', $ingredientId)->update(['cost_per_unit' => 2]);
        $after = (new CostingService($db))->calculate($productId);
        self::assertGreaterThan($before['unit_cost'], $after['unit_cost']);
        self::assertEqualsWithDelta($before['unit_cost'], (float) $db->table('order_items')
            ->select('unit_cost')->where('id', $line['id'])->get()->getRow('unit_cost'), 0.001);
    }

    public function testIngredientTrackingRejectsUncostedOrderWithoutAnyStockChange(): void
    {
        $db = db_connect('tests');
        $id = (int) $db->table('products')->where('slug', 'hot-americano')->select('id')->get()->getRow('id');
        $db->table('product_recipes')->where('product_id', $id)->delete(); // no-recipe fixture
        $originalStock = (int) $db->table('products')->where('id', $id)->get()->getRow('stock');
        $originalMovements = $db->table('inventory_movements')->where('product_id', $id)->countAllResults();
        $db->table('settings')->where('setting_key', 'inventory_ingredient_tracking_enabled')
            ->update(['setting_value' => '1']);
        $this->expectException(\DomainException::class);
        try {
            $this->placeOrder([
                'items' => [['product_id' => $id, 'quantity' => 1]],
                'order_type' => 'pickup', 'request_token' => bin2hex(random_bytes(16)),
            ], ['id' => $this->makeCustomer(), 'role' => 'customer']);
        } finally {
            self::assertSame($originalStock, (int) $db->table('products')
                ->where('id', $id)->get()->getRow('stock'));
            self::assertSame($originalMovements, $db->table('inventory_movements')
                ->where('product_id', $id)->countAllResults());
        }
    }
}
