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
    $hasSnapshot = $service['client_rate_snapshot'] !== null && $service['client_rate_snapshot'] !== '';
    $service['income_regular'] = $hasSnapshot ? $service['client_rate_snapshot'] : ($client['client_hourly_rate'] ?? 0);
    // Blank weekend/holiday tariff means the weekday tariff applies.
    $service['income_special'] = $service['client_special_snapshot']
        ?? ($hasSnapshot ? null : ($client['client_special_rate'] ?? null))
        ?? $service['income_regular'];
    return service_rate_amount($service, 'income_regular', 'income_special');
}

/** Apply the client's current tariffs to all of that client's non-cancelled services. Caller owns the transaction. */
function client_sync_service_rates(PDO $pdo, int $patientId): int
{
    $stmt = $pdo->prepare('SELECT client_hourly_rate, client_special_rate FROM patients WHERE id = ?');
    $stmt->execute([$patientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (! $client) {
        return 0;
    }
    $regular = $client['client_hourly_rate'] === null ? null : (float) $client['client_hourly_rate'];
    $special = $client['client_special_rate'] === null ? null : (float) $client['client_special_rate'];
    $update = $pdo->prepare("UPDATE services SET client_rate_snapshot = ?, client_special_snapshot = ? WHERE patient_id = ? AND status != 'CANCELLED'");
    $update->execute([$regular, $special, $patientId]);
    return $update->rowCount();
}

/** Name of the Portuguese national holiday on a Y-m-d date, or null. */
function holiday_name(string $date): ?string
{
    static $names = [];
    $year = (int) substr($date, 0, 4);
    if (!isset($names[$year])) {
        $labels = ['Ano Novo', 'Dia da Liberdade', 'Dia do Trabalhador', 'Dia de Portugal', 'Assunção de Nossa Senhora', 'Implantação da República', 'Dia de Todos-os-Santos', 'Restauração da Independência', 'Imaculada Conceição', 'Natal', 'Sexta-feira Santa', 'Páscoa', 'Corpo de Deus'];
        $names[$year] = array_combine(national_holidays($year), $labels);
    }
    return $names[$year][$date] ?? null;
}

/** Holidays from the previous to the next year, as date => name (for client-side calendars). */
function holiday_map(?int $year = null): array
{
    $year ??= (int) date('Y');
    $map = [];
    foreach ([$year - 1, $year, $year + 1] as $each) foreach (national_holidays($each) as $date) $map[$date] = holiday_name($date);
    return $map;
}
