<?php
declare(strict_types=1);

/** Portuguese national holidays, including Gregorian Easter-dependent dates. */
function national_holidays(int $year): array
{
    $a=$year%19; $b=intdiv($year,100); $c=$year%100; $d=intdiv($b,4); $e=$b%4;
    $f=intdiv($b+8,25); $g=intdiv($b-$f+1,3); $h=(19*$a+$b-$d-$g+15)%30;
    $i=intdiv($c,4); $k=$c%4; $l=(32+2*$e+2*$i-$h-$k)%7; $m=intdiv($a+11*$h+22*$l,451);
    $month=intdiv($h+$l-7*$m+114,31); $day=($h+$l-7*$m+114)%31+1;
    $easter=new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$day),new DateTimeZone('Europe/Lisbon'));
    $days=array_map(fn($date)=>$year.'-'.$date,['01-01','04-25','05-01','06-10','08-15','10-05','11-01','12-01','12-08','12-25']);
    foreach ([-2,0,60] as $offset) $days[]=$easter->modify(($offset>=0?'+':'').$offset.' days')->format('Y-m-d');
    return $days;
}

function special_rate_day(string $date): bool
{
    static $holidays=[]; $year=(int)substr($date,0,4);
    $holidays[$year]??=national_holidays($year);
    return (int)(new DateTimeImmutable($date))->format('N')>=6 || in_array($date,$holidays[$year],true);
}

function service_rate_label(array $service): string
{
    $regular=(float)$service['hourly_rate']; $special=$service['hourly_rate_special']??null;
    return money($regular).($special!==null && (float)$special!==$regular?' / '.money((float)$special):'');
}

/** Split at local midnight; retain the caller's legacy wall-time/elapsed-time convention. */
function service_rate_amount(array $service,string $regular='hourly_rate',string $special='hourly_rate_special',bool $elapsed=false): float
{
    $zone=new DateTimeZone('Europe/Lisbon');
    $start=new DateTimeImmutable($service['starts_at'],$zone); $end=new DateTimeImmutable($service['ends_at'],$zone); $total=0.0;
    while ($start<$end) {
        $next=$start->modify('tomorrow')->setTime(0,0); if ($next>$end) $next=$end;
        $rate=special_rate_day($start->format('Y-m-d'))?($service[$special]??$service[$regular]??0):($service[$regular]??0);
        $seconds=$elapsed?$next->getTimestamp()-$start->getTimestamp():strtotime($next->format('Y-m-d H:i:s').' UTC')-strtotime($start->format('Y-m-d H:i:s').' UTC');
        $total+=max(0,$seconds)/3600*(float)$rate; $start=$next;
    }
    return $total;
}
