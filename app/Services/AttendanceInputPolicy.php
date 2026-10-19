<?php

namespace App\Services;

/** Shared validation for attendance schedules and wage-rate edits. */
final class AttendanceInputPolicy
{
    public static function validateShift(int $weekday, string $start, string $end, int $graceMinutes): void
    {
        if ($weekday < 1 || $weekday > 7) {
            throw new \DomainException('Weekday must be from Monday (1) through Sunday (7).');
        }
        if (! self::isClockTime($start) || ! self::isClockTime($end) || $start === $end) {
            throw new \DomainException('Use valid different start/end times in 24-hour HH:MM format.');
        }
        if ($graceMinutes < 0 || $graceMinutes > 120) {
            throw new \DomainException('The grace period must be between 0 and 120 minutes.');
        }
    }

    public static function hourlyRate(mixed $raw): float
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            throw new \DomainException('Enter a valid hourly rate.');
        }
        $value = filter_var($raw, FILTER_VALIDATE_FLOAT);
        if ($value === false || ! is_finite((float) $value) || $value < 0 || $value > 100000) {
            throw new \DomainException('Hourly rate must be between 0 and 100000.');
        }
        return round((float) $value, 2);
    }

    public static function correctedAt(string $raw): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw, new \DateTimeZone('Asia/Manila'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (! $date || ($errors !== false && ($errors['error_count'] || $errors['warning_count']))
            || $date->format('Y-m-d\TH:i') !== $raw) {
            throw new \DomainException('Enter a valid correction date and time.');
        }
        return $date->format('Y-m-d H:i:s');
    }

    private static function isClockTime(string $value): bool
    {
        return preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value) === 1;
    }
}
