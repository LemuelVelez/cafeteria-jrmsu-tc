<?php

namespace Tests\Database;

use App\Services\InventoryService;
use App\Services\StockCardService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

/** Database-backed proof that CSV/print iterator is not limited to a single pager page. */
#[Group('database')]
final class StockCardExportDatabaseTest extends DatabaseTestCase
{
    public function testExportContainsEveryFilteredRowAndBalancesAcrossOtherTypes(): void
    {
        $db = db_connect('tests');
        $productId = (int) $db->table('products')->select('id')
            ->where('slug', 'crispy-fries')->get()->getRow('id');
        $inventory = new InventoryService($db);
        $db->transBegin();
        try {
            for ($n = 0; $n < 60; $n++) {
                $inventory->stockIn($productId, 1, null, 'EXPORT-FIXTURE-' . $n);
            }
            $inventory->adjust($productId, -3, null, 'Export stock adjustment');
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to create stock-card fixtures.');
            }
            $db->transCommit();
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }

        $today = date('Y-m-d');
        $service = new StockCardService($db);
        $card = $service->get('product', $productId, $today, $today, 'stock_in', 1, 25);
        self::assertCount(25, $card['rows']);
        self::assertSame(60, $card['total']);
        $exported = iterator_to_array($service->exportRows('product', $productId, $today, $today, 'stock_in'));
        self::assertCount(60, $exported);
        self::assertSame(60, count(array_unique(array_column($exported, 'id'))));
        self::assertSame('EXPORT-FIXTURE-0', $exported[0]['reference']);
        // Although adjustment rows are filtered from the output, they must
        // remain part of the ledger window's running-balance calculation.
        $complete = iterator_to_array($service->exportRows('product', $productId, $today, $today));
        $last = end($complete);
        self::assertEqualsWithDelta((float) $card['closing'], (float) $last['running_balance'], 0.001);
        self::assertEqualsWithDelta((float) $card['closing'], (float) $db->table('products')
            ->select('stock')->where('id', $productId)->get()->getRow('stock'), 0.001);
    }

    public function testExportValidatesLedgerDateRangeAndMovementType(): void
    {
        $service = new StockCardService(db_connect('tests'));
        foreach ([
            ['inventory_movements', 1, '2026-10-01', '2026-10-19', ''],
            ['product', 1, '2026-99-01', '2026-10-19', ''],
            ['product', 1, '2026-10-19', '2026-10-01', ''],
            ['product', 1, '2026-10-01', '2026-10-19', 'stock_in;DELETE'],
        ] as [$ledger, $id, $from, $to, $type]) {
            try {
                iterator_to_array($service->exportRows($ledger, $id, $from, $to, $type));
                self::fail('Expected stock-card export input validation failure.');
            } catch (\DomainException) {
                self::assertTrue(true);
            }
        }
    }
}
