<?php
require_once __DIR__ . '/../lib/scheduling.php';
$user = require_auth();
$isCarer = $user['role'] === 'CARER';
if ($isCarer) {
    require __DIR__ . '/carer-services.php';
    return;
}
$pdo = db();
$carers = $pdo->query('SELECT * FROM carers ORDER BY name')->fetchAll();
$patients = $pdo->query('SELECT * FROM patients ORDER BY name')->fetchAll();
$municipalities = scheduling_municipalities();
$selectedMunicipality = $_GET['municipality'] ?? ($_SESSION['scales_municipality'] ?? 'all');
if ($selectedMunicipality === '') {
    $selectedMunicipality = 'all';
}
if (! in_array($selectedMunicipality, [...$municipalities, 'all', '__unassigned'], true)) {
    $selectedMunicipality = 'all';
}
$_SESSION['scales_municipality'] = $selectedMunicipality;
$selectedStatus = (string) ($_GET['status'] ?? '');
if (! in_array($selectedStatus, ['', 'SCHEDULED', 'DONE', 'CANCELLED'], true)) {
    $selectedStatus = '';
}
$search = substr(trim((string) ($_GET['search'] ?? '')), 0, 200);
$periodOptions = ['day' => 'Dia', 'week' => 'Semana', 'month' => 'Mês'];
$selectedPeriod = $_GET['period'] ?? 'week';
if (! isset($periodOptions[$selectedPeriod])) {
    $selectedPeriod = 'day';
}
$selectedDate = $_GET['scale_date'] ?? date('Y-m-d');
$validDate = is_string($selectedDate) ? DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate) : false;
if (!$validDate || $validDate->format('Y-m-d') !== $selectedDate) $selectedDate = date('Y-m-d');
$selectedWeek = $_GET['scale_week'] ?? (new DateTimeImmutable($selectedDate))->format('o-\WW');
$selectedMonth = $_GET['scale_month'] ?? substr($selectedDate, 0, 7);
$monthNames = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

if ($selectedPeriod === 'week') {
    if (! preg_match('/^(\d{4})-W(\d{2})$/', $selectedWeek, $weekParts)) {
        $selectedWeek = date('o-\WW');
        preg_match('/^(\d{4})-W(\d{2})$/', $selectedWeek, $weekParts);
    }
    if ((int) $weekParts[2] < 1 || (int) $weekParts[2] > 53) {
        $selectedWeek = date('o-\WW');
        preg_match('/^(\d{4})-W(\d{2})$/', $selectedWeek, $weekParts);
    }
    $periodStartDate = new DateTime();
    $periodStartDate->setISODate((int) $weekParts[1], (int) $weekParts[2], 1);
    if ($periodStartDate->format('o-\WW') !== $selectedWeek) {
        $periodStartDate = new DateTime('monday this week');
        $selectedWeek = $periodStartDate->format('o-\WW');
    }
    $periodEndDate = (clone $periodStartDate)->modify('+6 days');
    $periodLabel = $periodStartDate->format('d/m/Y') . ' a ' . $periodEndDate->format('d/m/Y');
    $selectedDate = $periodStartDate->format('Y-m-d');
} elseif ($selectedPeriod === 'month') {
    if (! preg_match('/^\d{4}-\d{2}$/', $selectedMonth) || (int) substr($selectedMonth, 5) < 1 || (int) substr($selectedMonth, 5) > 12) {
        $selectedMonth = date('Y-m');
    }
    $periodStartDate = new DateTime($selectedMonth . '-01');
    $periodEndDate = (clone $periodStartDate)->modify('last day of this month');
    $periodLabel = $monthNames[(int) $periodStartDate->format('n')] . ' de ' . $periodStartDate->format('Y');
    $selectedDate = $periodStartDate->format('Y-m-d');
} else {
    if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $selectedDate, $dateParts) || ! checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])) {
        $selectedDate = date('Y-m-d');
    }
    $periodStartDate = new DateTime($selectedDate);
    $periodEndDate = clone $periodStartDate;
    $periodLabel = $periodStartDate->format('d/m/Y');
}

$selectedCarer = (int) ($_GET['carer_id'] ?? 0);
$selectedPatient = (int) ($_GET['patient_id'] ?? 0);
$mapServices = [];
$mapCarers = [];
$servicesByHour = [];
$servicesByDate = [];
$calendarDays = [];
$calendarHours = range(0, 23);

if (! $isCarer) {
    require_role(['ADMIN', 'EDITOR']);
    $periodStart = $periodStartDate->format('Y-m-d') . ' 00:00:00';
    $periodEnd = (clone $periodEndDate)->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $sql = "SELECT services.*, patients.name AS patient_name, patients.address, carers.name AS carer_name, carers.color_hex,
        EXISTS(SELECT 1 FROM services AS other WHERE other.id != services.id AND other.carer_id = services.carer_id AND other.status != 'CANCELLED' AND services.status != 'CANCELLED' AND other.starts_at < services.ends_at AND other.ends_at > services.starts_at) AS has_conflict
        FROM services
        JOIN patients ON patients.id = services.patient_id
        JOIN carers ON carers.id = services.carer_id
        WHERE services.starts_at < ? AND services.ends_at > ?";
    $params = [$periodEnd, $periodStart];
    if ($selectedMunicipality === '__unassigned') {
        $sql .= " AND TRIM(COALESCE(services.municipality, '')) = ''";
    } elseif ($selectedMunicipality !== 'all') {
        $sql .= ' AND services.municipality = ?';
        $params[] = $selectedMunicipality;
    }
    if ($selectedStatus !== '') {
        $sql .= ' AND services.status = ?';
        $params[] = $selectedStatus;
    }
    if ($search !== '') {
        $sql .= " AND (patients.name LIKE ? ESCAPE '\\' OR carers.name LIKE ? ESCAPE '\\' OR COALESCE(services.observations, '') LIKE ? ESCAPE '\\')";
        $term = '%' . strtr($search, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
        array_push($params, $term, $term, $term);
    }
    if ($selectedCarer > 0) {
        $sql .= ' AND services.carer_id = ?';
        $params[] = $selectedCarer;
    }
    if ($selectedPatient > 0) {
        $sql .= ' AND services.patient_id = ?';
        $params[] = $selectedPatient;
    }
    $sql .= ' ORDER BY services.starts_at ASC, carers.name ASC, patients.name ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $mapServices = $stmt->fetchAll();

    foreach ($mapServices as $service) {
        $carerId = (int) $service['carer_id'];
        $serviceDate = substr((string) $service['starts_at'], 0, 10);
        $serviceHour = (int) date('G', strtotime($service['starts_at']));
        $servicesByHour[$serviceHour][] = $service;
        for ($day = clone $periodStartDate; $day <= $periodEndDate; $day->modify('+1 day')) {
            $dayStart = $day->format('Y-m-d') . ' 00:00:00';
            $dayEnd = (clone $day)->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
            if ($service['starts_at'] < $dayEnd && $service['ends_at'] > $dayStart) {
                $servicesByDate[$day->format('Y-m-d')][] = $service;
            }
        }
        if (! isset($mapCarers[$carerId])) {
            $mapCarers[$carerId] = ['id' => $carerId, 'name' => $service['carer_name'], 'color_hex' => $service['color_hex']];
        }
    }
    uasort($mapCarers, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    for ($day = clone $periodStartDate; $day <= $periodEndDate; $day->modify('+1 day')) {
        $calendarDays[] = $day->format('Y-m-d');
    }
}
$columnCarers = $selectedCarer ? array_values(array_filter($carers, fn ($carer) => (int) $carer['id'] === $selectedCarer)) : $carers;
usort($columnCarers, fn ($a, $b) => [isset($mapCarers[(int) $a['id']]) ? 0 : 1, $a['name'], $a['id']] <=> [isset($mapCarers[(int) $b['id']]) ? 0 : 1, $b['name'], $b['id']]);
$carerPages = max(1, (int) ceil(count($columnCarers) / 8));
$carerPage = max(1, min($carerPages, (int) ($_GET['carer_page'] ?? 1)));
$visibleCarers = array_slice($columnCarers, ($carerPage - 1) * 8, 8);
$pageUrl = function (int $number): string {
    return 'index.php?' . http_build_query(array_merge($_GET, ['page' => 'scales', 'carer_page' => $number]));
};

require __DIR__ . '/scales-workspace.php';

function render_service_chip(array $service, string $selectedMunicipality, bool $unused = false, int $visibleMinutes = 1440): void {
    $description = $service['patient_name'] . ', ' . $service['carer_name'] . ', ' . $service['starts_at'] . ' - ' . $service['ends_at'];
    $geometry = $unused ? 'height:100%;min-height:0;overflow:hidden;width:100%;margin:0;padding:0 4px;border-top:0;border-bottom:0;font-size:12px' : '';
    ?>
    <button class="service-chip status-<?= strtolower(e($service['status'])) ?> <?= !empty($service['has_conflict']) ? 'has-conflict' : '' ?>" style="--chip-color: <?= e(preg_match('/^#[0-9a-f]{6}$/i', $service['color_hex'] ?? '') ? $service['color_hex'] : '#183A32') ?>;<?= $geometry ?>" type="button" title="<?= e($description) ?>" aria-label="<?= e($description) ?>" data-service-id="<?= $service['id'] ?>" data-patient-id="<?= $service['patient_id'] ?>" data-carer-id="<?= $service['carer_id'] ?>" data-starts-at="<?= e(date_input($service['starts_at'])) ?>" data-ends-at="<?= e(date_input($service['ends_at'])) ?>" data-hourly-rate="<?= e((string) (float) $service['hourly_rate']) ?>" data-observations="<?= e($service['observations'] ?? '') ?>" data-municipality="<?= e($service['municipality'] ?? '') ?>" data-patient-name="<?= e($service['patient_name']) ?>" data-carer-name="<?= e($service['carer_name']) ?>" data-status="<?= e($service['status']) ?>" data-has-conflict="<?= !empty($service['has_conflict']) ? '1' : '0' ?>">
        <span class="matrix-service-label"><?= e($service['_display_time'] ?? (date('H:i', strtotime($service['starts_at'])) . '–' . date('H:i', strtotime($service['ends_at'])))) ?> - <?= e($service['carer_name']) ?></span>
        <?php if ($service['status'] === 'CANCELLED'): ?><span class="sr-only">Cancelado</span><?php endif; ?>
    </button>
<?php }

function scales_day_segments(array $services, string $date): array
{
    $dayStart = $date . ' 00:00:00';
    $dayEnd = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $segments = [];
    foreach ($services as $service) {
        $start = max($service['starts_at'], $dayStart);
        $end = min($service['ends_at'], $dayEnd);
        if ($start >= $end) {
            continue;
        }
        $service['_start_minute'] = (int) substr($start, 11, 2) * 60 + (int) substr($start, 14, 2);
        $service['_end_minute'] = $end === $dayEnd ? 1440 : (int) substr($end, 11, 2) * 60 + (int) substr($end, 14, 2);
        $segments[] = $service;
    }
    usort($segments, fn ($a, $b) => [$a['_start_minute'], $a['_end_minute'], $a['id']] <=> [$b['_start_minute'], $b['_end_minute'], $b['id']]);
    $result = [];
    $group = [];
    $laneEnds = [];
    $groupEnd = -1;
    $flush = function () use (&$result, &$group, &$laneEnds): void {
        foreach ($group as $segment) {
            $segment['_lanes'] = count($laneEnds);
            $result[] = $segment;
        }
        $group = [];
        $laneEnds = [];
    };
    // Historical/cancelled overlaps share horizontal lanes, never cover each other.
    foreach ($segments as $segment) {
        if ($segment['_start_minute'] >= $groupEnd) {
            $flush();
            $groupEnd = -1;
        }
        $lane = 0;
        while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $segment['_start_minute']) {
            $lane++;
        }
        $laneEnds[$lane] = $segment['_end_minute'];
        $segment['_lane'] = $lane;
        $group[] = $segment;
        $groupEnd = max($groupEnd, $segment['_end_minute']);
    }
    $flush();
    return $result;
}
