<?php
namespace App\Services;
final class BarcodeService
{
    public static function valid(string $code): bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/',$code)) return false;
        if (!ctype_digit($code) || !in_array(strlen($code),[12,13],true))return true;
        $digits=array_map('intval',str_split($code));$check=array_pop($digits);
        $sum=0;$weight=3;
        foreach(array_reverse($digits) as $digit){$sum+=$digit*$weight;$weight=$weight===3?1:3;}
        return (10-$sum%10)%10===$check;
    }
}
