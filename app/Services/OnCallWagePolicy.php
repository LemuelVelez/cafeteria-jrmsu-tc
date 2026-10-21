<?php
namespace App\Services;

final class OnCallWagePolicy
{
    public static function calculate(int $hours, int $minutes, string|float|null $rate): float
    {
        if ($rate === null || trim((string)$rate)==='' || !is_numeric($rate) || (float)$rate<=0) throw new \DomainException('Set the approved on-call hourly rate in Admin Settings before recording wages.');
        if ($hours<0 || $hours>24 || $minutes<0 || $minutes>59 || ($hours===0 && $minutes===0) || ($hours===24 && $minutes!==0)) throw new \DomainException('Enter valid hours (0–24) and additional minutes (0–59).');
        return round((float)$rate * ($hours*60+$minutes)/60,2,PHP_ROUND_HALF_UP);
    }
}
