<?php

namespace Tests\Unit;

use App\Services\StockCardService;
use PHPUnit\Framework\TestCase;

/** Invalid stock-card requests must fail before database access. */
final class StockCardRequestValidationTest extends TestCase
{
    public function testMalformedDatesAreRejectedForBothBrowsingAndExport(): void
    {
        $service = new StockCardService();
        foreach ([
            ['2026-02-30', '2026-03-01'],
            ['not-a-date', '2026-10-19'],
            ['2026-10-19', '2026-10-18'],
        ] as [$from, $to]) {
            try {
                $service->get('product', 0, $from, $to);
                self::fail('Invalid browsing dates were accepted: ' . $from);
            } catch (\DomainException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }

            try {
                iterator_to_array($service->exportRows('product', 0, $from, $to));
                self::fail('Invalid export dates were accepted: ' . $from);
            } catch (\DomainException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }
        }
    }

    public function testValidEmptyCardDoesNotRequireDatabaseAccess(): void
    {
        $service = new StockCardService();
        $card = $service->get('product', 0, '2026-10-01', '2026-10-19');
        self::assertSame([], $card['rows']);
        self::assertSame(0, $card['total']);
    }

    public function testInvalidPageAndMovementTypeAreRejectedEvenOnEmptyCard(): void
    {
        $service = new StockCardService();
        foreach ([
            fn () => $service->get('product', 0, '2026-10-01', '2026-10-19', '', 0),
            fn () => $service->get('product', 0, '2026-10-01', '2026-10-19', 'bad;type'),
        ] as $request) {
            try {
                $request();
                self::fail('Invalid stock-card request was accepted.');
            } catch (\DomainException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }
        }
    }
}
