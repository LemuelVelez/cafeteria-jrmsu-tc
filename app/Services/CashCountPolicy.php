<?php
namespace App\Services;

final class CashCountPolicy
{
    public const DENOMINATIONS = [1000,500,200,100,50,20,10,5,1];
    public static function count(array $pieces): array
    {
        $lines=[];$total=0;
        foreach (self::DENOMINATIONS as $denomination) {
            $value=$pieces[(string)$denomination]??$pieces[$denomination]??0;
            if (filter_var($value,FILTER_VALIDATE_INT)===false || (int)$value<0 || (int)$value>1000000) throw new \DomainException('Cash pieces must be nonnegative whole numbers.');
            $lines[(string)$denomination]=['pieces'=>(int)$value,'subtotal'=>$denomination*(int)$value];
            $total+=$denomination*(int)$value;
        }
        return ['lines'=>$lines,'total'=>round($total,2)];
    }
    public static function credits(array $entries): array
    {
        if(count($entries)>100)throw new \DomainException('Too many credit entries.');
        $rows=[];$total=0.0;
        foreach($entries as $entry) {
            if(!is_array($entry))throw new \DomainException('Invalid credit entry.');
            $name=trim((string)($entry['name']??''));$amount=$entry['amount']??null;
            if($name===''||mb_strlen($name)>150||!is_numeric($amount)||(float)$amount<=0||(float)$amount>9999999999)throw new \DomainException('Credit entries require a name and positive amount.');
            $remarks=trim((string)($entry['remarks']??''));
            if(mb_strlen($remarks)>1000)throw new \DomainException('Credit remarks are too long.');
            $rows[]=['name'=>$name,'amount'=>round((float)$amount,2),'remarks'=>$remarks];$total+=round((float)$amount,2);
        }
        return ['entries'=>$rows,'total'=>round($total,2)];
    }
    public static function variance(float $expected,float $counted): float { return round($counted-$expected,2); }
}
