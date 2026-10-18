<?php
namespace App\Services;
final class AttendanceService
{
    /** Pure calculation; all datetimes are interpreted in Asia/Manila. */
    public static function estimate(string $in,string $out,string $shiftStart,string $shiftEnd,float $hourlyRate,int $graceMinutes=5,float $overtimePremium=0.25,float $nightDifferential=0.10): array
    {
        $zone=new \DateTimeZone('Asia/Manila');$start=new \DateTimeImmutable($in,$zone);$finish=new \DateTimeImmutable($out,$zone);
        if ($finish<=$start)throw new \DomainException('Clock-out must occur after clock-in.');
        $scheduledStart=new \DateTimeImmutable($start->format('Y-m-d').' '.$shiftStart,$zone);
        $scheduledEnd=new \DateTimeImmutable($start->format('Y-m-d').' '.$shiftEnd,$zone);
        if ($scheduledEnd<=$scheduledStart)$scheduledEnd=$scheduledEnd->modify('+1 day');
        $scheduledHours=($scheduledEnd->getTimestamp()-$scheduledStart->getTimestamp())/3600;
        $actualHours=($finish->getTimestamp()-$start->getTimestamp())/3600;
        $regularHours=min($scheduledHours,$actualHours);$overtime=max(0,$actualHours-$scheduledHours);
        $late=max(0,(int)floor(($start->getTimestamp()-$scheduledStart->getTimestamp())/60)-$graceMinutes);
        $undertime=max(0,(int)floor(($scheduledEnd->getTimestamp()-$finish->getTimestamp())/60));
        $night=0.0;
        for($day=$start->modify('-1 day')->setTime(0,0);$day<=$finish;$day=$day->modify('+1 day')) {
            $nightStart=$day->setTime(22,0);$nightEnd=$day->modify('+1 day')->setTime(6,0);
            $overlap=max(0,min($finish->getTimestamp(),$nightEnd->getTimestamp())-max($start->getTimestamp(),$nightStart->getTimestamp()));
            $night+=$overlap/3600;
        }
        $wage=$regularHours*$hourlyRate+$overtime*$hourlyRate*(1+$overtimePremium)+$night*$hourlyRate*$nightDifferential;
        return ['regular_hours'=>round($regularHours,2),'overtime_hours'=>round($overtime,2),'night_hours'=>round($night,2),'late_minutes'=>$late,'undertime_minutes'=>$undertime,'wage_estimate'=>round($wage,2)];
    }
}
