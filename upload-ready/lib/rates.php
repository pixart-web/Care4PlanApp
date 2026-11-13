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

function service_rate_parts(array $service, string $regular='hourly_rate', string $special='hourly_rate_special'): array
{
    $zone = new DateTimeZone('Europe/Lisbon');
    $cursor = new DateTimeImmutable($service['starts_at'], $zone);
    $end = new DateTimeImmutable($service['ends_at'], $zone);
    $parts = ['weekday'=>0.0, 'special'=>0.0];
    while ($cursor < $end) {
        $next = min($end, $cursor->modify('tomorrow midnight'));
        $isSpecial = special_rate_day($cursor->format('Y-m-d'));
        $rate = $isSpecial ? ($service[$special] ?? $service[$regular] ?? 0) : ($service[$regular] ?? 0);
        $hours = ($next->getTimestamp() - $cursor->getTimestamp()) / 3600;
        $parts[$isSpecial ? 'special' : 'weekday'] += $hours * (float)$rate;
        $cursor = $next;
    }
    return array_map(fn($amount)=>round($amount, 2), $parts);
}

function service_rate_amount(array $service, string $regular='hourly_rate', string $special='hourly_rate_special'): float
{
    return array_sum(service_rate_parts($service, $regular, $special));
}

function client_service_amount(array $service, array $client): float
{
    $service['income_regular'] = $service['client_rate_snapshot'] ?? $client['client_hourly_rate'] ?? 0;
    $service['income_special'] = $service['client_special_snapshot'] ?? $service['income_regular'];
    return service_rate_amount($service, 'income_regular', 'income_special');
}
