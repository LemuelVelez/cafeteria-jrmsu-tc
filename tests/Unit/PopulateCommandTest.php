<?php

namespace Tests\Unit;

use App\Commands\Populate;
use PHPUnit\Framework\TestCase;

/** These tests exercise the default CLI path without MySQL or user input. */
final class PopulateCommandTest extends TestCase
{
    public function testPlainPopulateSeedsWithoutRequestingKitchenMeasurements(): void
    {
        $command = self::fake();
        self::assertSame(EXIT_SUCCESS, $command->run([]));
        self::assertSame(1, $command->seedCalls);
    }

    public function testFailedSeedCommandStopsPopulation(): void
    {
        $command = self::fake();
        $command->seedResult = EXIT_ERROR;
        self::assertSame(EXIT_ERROR, $command->run([]));
        self::assertSame(1, $command->seedCalls);
    }

    public function testIncompleteCoreDataCannotBeReportedAsPopulated(): void
    {
        $command = self::fake();
        $command->coreReady = false;
        self::assertSame(EXIT_ERROR, $command->run([]));
    }

    public function testUnknownProductIsRejectedWithoutSeeding(): void
    {
        $command = self::fake();
        self::assertSame(EXIT_ERROR, $command->run(['not-a-product']));
        self::assertSame(0, $command->seedCalls);
    }

    private static function fake(): Populate
    {
        return new class extends Populate {
            public int $seedCalls = 0;
            public int $seedResult = EXIT_SUCCESS;
            public bool $coreReady = true;

            protected function runSeedCommand(): mixed
            {
                $this->seedCalls++;
                return $this->seedResult;
            }

            protected function coreSeedIsReady(): bool
            {
                return $this->coreReady;
            }

            protected function readyNutritionCount(): int
            {
                return 0; // Source catalogs are intentionally unsigned in this test.
            }
        };
    }
}
