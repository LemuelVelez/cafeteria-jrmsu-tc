<?php

namespace Tests\Unit;

use App\Services\AttendanceInputPolicy;
use App\Services\AttendanceService;
use PHPUnit\Framework\TestCase;

final class AttendanceInputPolicyTest extends TestCase
{
    public function testRegularAndOvertimeWageCalculationWithWorkedNumbers(): void
    {
        $result = AttendanceService::estimate('2026-10-19 08:00:00', '2026-10-19 18:00:00',
            '08:00:00', '17:00:00', 100, 5, 0.25, 0.10);
        self::assertSame(9.0, $result['regular_hours']);
        self::assertSame(1.0, $result['overtime_hours']);
        self::assertSame(0.0, $result['night_hours']);
        self::assertSame(1025.0, $result['wage_estimate']);
    }

    public function testOvernightNightDifferentialMatchesEightHours(): void
    {
        $result = AttendanceService::estimate('2026-10-19 21:00:00', '2026-10-20 06:00:00',
            '21:00:00', '06:00:00', 100, 5, 0.25, 0.10);
        self::assertSame(8.0, $result['night_hours']);
        self::assertSame(980.0, $result['wage_estimate']);
    }

    public function testRejectsInvalidTimesGracePeriodsAndMatchingClockTimes(): void
    {
        foreach ([[1,'24:30','18:00',5],[1,'08:00','99:00',5],
            [1,'09:00','09:00',5],[0,'08:00','17:00',5],
            [8,'08:00','17:00',5],[1,'08:00','17:00',-1],
            [1,'08:00','17:00',121]] as [$day,$start,$end,$grace]) {
            try {
                AttendanceInputPolicy::validateShift($day,$start,$end,$grace);
                self::fail('Expected validation to reject ' . json_encode([$day,$start,$end,$grace]));
            } catch (\DomainException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
        AttendanceInputPolicy::validateShift(1, '22:00', '06:00', 0);
        self::assertTrue(true);
    }

    public function testRejectsNonNumericHourlyRatesAndNormalizesToCents(): void
    {
        self::assertSame(125.55, AttendanceInputPolicy::hourlyRate('125.554'));
        foreach (['bad', '-10', '999999', '', null] as $value) {
            try {
                AttendanceInputPolicy::hourlyRate($value);
                self::fail('Unexpected valid hourly rate');
            } catch (\DomainException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function testCorrectionDatesMustBeReal(): void
    {
        self::assertSame('2026-10-19 08:34:00', AttendanceInputPolicy::correctedAt('2026-10-19T08:34'));
        foreach (['2026-02-30T10:00', '2026-10-19T25:00', '2026-10-19T10:72',
            '2026-10-19 10:00'] as $raw) {
            try {
                AttendanceInputPolicy::correctedAt($raw);
                self::fail('Expected invalid correction date');
            } catch (\DomainException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
    }
}
