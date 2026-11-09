<?php
require_role(['ADMIN', 'EDITOR']);
$pdo = db();
$carersCount = (int) $pdo->query('SELECT COUNT(*) FROM carers')->fetchColumn();
$patientsCount = (int) $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
$selectedDate = is_string($_GET['scale_date'] ?? null) ? $_GET['scale_date'] : date('Y-m-d');
$day = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
if (! $day || $day->format('Y-m-d') !== $selectedDate) {
    $day = new DateTimeImmutable('today');
    $selectedDate = $day->format('Y-m-d');
}
$dayStart = $day->format('Y-m-d H:i:s');
$dayEnd = $day->modify('+1 day')->format('Y-m-d H:i:s');
$municipalities = ['Portimão', 'Lagos', 'Lagoa', 'Albufeira', 'Silves'];
foreach ($pdo->query("SELECT DISTINCT TRIM(municipality) FROM services WHERE TRIM(COALESCE(municipality, '')) != '' ORDER BY TRIM(municipality)")->fetchAll(PDO::FETCH_COLUMN) as $municipality) {
    if (! in_array($municipality, $municipalities, true)) $municipalities[] = $municipality;
}
$selectedMunicipality = is_string($_GET['municipality'] ?? null) ? $_GET['municipality'] : '';
if (! in_array($selectedMunicipality, $municipalities, true) && $selectedMunicipality !== '__unassigned') $selectedMunicipality = '';
$stmt = $pdo->prepare('SELECT services.*, carers.name AS carer_name, patients.name AS patient_name FROM services LEFT JOIN carers ON carers.id = services.carer_id LEFT JOIN patients ON patients.id = services.patient_id WHERE services.starts_at < ? AND services.ends_at > ? ORDER BY services.starts_at, services.id');
$stmt->execute([$dayEnd, $dayStart]);
$dayServices = $stmt->fetchAll();
$summary = [];
foreach ($municipalities as $municipality) $summary[$municipality] = ['total' => 0, 'hours' => 0.0, 'carers' => [], 'SCHEDULED' => 0, 'DONE' => 0, 'CANCELLED' => 0];
$summary[''] = ['total' => 0, 'hours' => 0.0, 'carers' => [], 'SCHEDULED' => 0, 'DONE' => 0, 'CANCELLED' => 0];
$totals = ['total' => 0, 'hours' => 0.0, 'carers' => [], 'SCHEDULED' => 0, 'DONE' => 0, 'CANCELLED' => 0];
$pending = [];
foreach ($dayServices as $service) {
    $municipality = trim((string) ($service['municipality'] ?? ''));
    $row = &$summary[$municipality];
    $row['total']++;
    $row[$service['status']]++;
    $hours = max(0, (min(strtotime($service['ends_at']), strtotime($dayEnd)) - max(strtotime($service['starts_at']), strtotime($dayStart))) / 3600);
    if ($service['status'] !== 'CANCELLED') {
        $row['hours'] += $hours;
        if ($service['carer_id']) $row['carers'][$service['carer_id']] = true;
    }
    $matches = $selectedMunicipality === '' || $municipality === ($selectedMunicipality === '__unassigned' ? '' : $selectedMunicipality);
    if ($matches) {
        $totals['total']++;
        $totals[$service['status']]++;
        if ($service['status'] !== 'CANCELLED') {
            $totals['hours'] += $hours;
            if ($service['carer_id']) $totals['carers'][$service['carer_id']] = true;
            if ($municipality === '' || ! $service['carer_name'] || ! $service['patient_name']) $pending[] = $service;
        }
    }
    unset($row);
}
if ($summary['']['total'] === 0) unset($summary['']);
// Compare all municipalities first: a filter must not hide the other half of a conflict.
$conflicts = [];
foreach ($dayServices as $index => $first) {
    if ($first['status'] === 'CANCELLED') continue;
    for ($next = $index + 1, $count = count($dayServices); $next < $count; $next++) {
        $second = $dayServices[$next];
        if ($second['starts_at'] >= $first['ends_at']) break;
        if ($second['status'] === 'CANCELLED' || $first['carer_id'] !== $second['carer_id']) continue;
        $filter = $selectedMunicipality === '__unassigned' ? '' : $selectedMunicipality;
        if ($selectedMunicipality !== '' && trim((string) $first['municipality']) !== $filter && trim((string) $second['municipality']) !== $filter) continue;
        $conflicts[] = [$first, $second];
    }
}
$scaleUrl = static function (string $municipality = '', string $status = '', int $carer = 0) use ($selectedDate): string {
    return 'index.php?' . http_build_query(['page' => 'scales', 'period' => 'day', 'scale_date' => $selectedDate, 'municipality' => $municipality === '' ? 'all' : $municipality, 'status' => $status, 'carer_id' => $carer]);
};
$newReports = $pdo->query("SELECT reports.id, reports.title, reports.created_at, carers.name AS carer_name FROM reports LEFT JOIN carers ON carers.id = reports.carer_id WHERE reports.deleted_at IS NULL AND reports.sender_role = 'CARER' AND reports.read_by_admin_at IS NULL ORDER BY reports.created_at DESC")->fetchAll();

$monthStart = date('Y-m-01 00:00:00');
$monthEnd = date('Y-m-t 23:59:59');
$movementStmt = $pdo->prepare('SELECT COUNT(*) FROM entity_movements WHERE entity_type = ? AND direction = ? AND created_at BETWEEN ? AND ?');
$countMovement = function (string $entityType, string $direction) use ($movementStmt, $monthStart, $monthEnd): int {
    $movementStmt->execute([$entityType, $direction, $monthStart, $monthEnd]);
    return (int) $movementStmt->fetchColumn();
};
$newCarers = $countMovement('CARER', 'IN');
$leftCarers = $countMovement('CARER', 'OUT');
$newPatients = $countMovement('PATIENT', 'IN');
$leftPatients = $countMovement('PATIENT', 'OUT');

layout('Dashboard - Care4All', function () use ($carersCount, $patientsCount, $newReports, $newCarers, $leftCarers, $newPatients, $leftPatients, $day, $selectedDate, $selectedMunicipality, $municipalities, $summary, $totals, $conflicts, $pending, $scaleUrl) { ?>
    <?php $birthdays=profile_birthdays(db()); if ($birthdays): ?><section class="alert" role="status"><strong>Aniversários de hoje</strong><?php foreach ($birthdays as $birthday): ?><div><?= e($birthday['name']) ?> · <?= e($birthday['kind']) ?></div><?php endforeach; ?></section><?php endif; ?>
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p class="muted">Operação diária · <?= $day->format('d/m/Y') ?></p>
        </div>
        <a class="btn btn-primary" href="<?= e($scaleUrl($selectedMunicipality)) ?>">Abrir escalas</a>
    </div>
    <form class="dashboard-filters" method="get" aria-label="Filtrar resumo diário">
        <input type="hidden" name="page" value="dashboard">
        <label class="field"><span class="label">Dia</span><input class="input" type="date" name="scale_date" value="<?= e($selectedDate) ?>" required></label>
        <label class="field"><span class="label">Concelho</span><select class="input" name="municipality"><option value="">Todos os concelhos</option><?php foreach ($municipalities as $municipality): ?><option <?= $selectedMunicipality === $municipality ? 'selected' : '' ?>><?= e($municipality) ?></option><?php endforeach; ?><option value="__unassigned" <?= $selectedMunicipality === '__unassigned' ? 'selected' : '' ?>>Sem concelho</option></select></label>
        <button class="btn btn-secondary" type="submit">Atualizar</button>
    </form>
    <section class="daily-metrics" aria-label="Resumo do dia">
        <a class="daily-metric" href="<?= e($scaleUrl($selectedMunicipality)) ?>"><span>Serviços do dia</span><strong><?= $totals['total'] ?></strong></a>
        <a class="daily-metric" href="<?= e($scaleUrl($selectedMunicipality, 'SCHEDULED')) ?>"><span>Agendados</span><strong><?= $totals['SCHEDULED'] ?></strong></a>
        <a class="daily-metric" href="<?= e($scaleUrl($selectedMunicipality, 'DONE')) ?>"><span>Concluídos</span><strong><?= $totals['DONE'] ?></strong></a>
        <a class="daily-metric" href="<?= e($scaleUrl($selectedMunicipality, 'CANCELLED')) ?>"><span>Cancelados</span><strong><?= $totals['CANCELLED'] ?></strong></a>
        <a class="daily-metric" href="<?= e($scaleUrl($selectedMunicipality)) ?>"><span>Horas não canceladas</span><strong><?= number_format($totals['hours'], 1, ',', ' ') ?><small> h</small></strong></a>
        <a class="daily-metric" href="<?= e($scaleUrl($selectedMunicipality)) ?>"><span>Cuidadoras escaladas</span><strong><?= count($totals['carers']) ?></strong></a>
    </section>
    <section class="dashboard-section" aria-labelledby="municipality-title">
        <div class="section-head"><h2 id="municipality-title">Resumo por concelho</h2><span class="muted">Horas e cuidadoras excluem cancelados</span></div>
        <div class="table-wrap"><table class="municipality-table"><caption class="sr-only">Distribuição dos serviços em <?= $day->format('d/m/Y') ?></caption><thead><tr><th scope="col">Concelho</th><th scope="col">Serviços</th><th scope="col">Horas</th><th scope="col">Cuidadoras</th><th scope="col">Concluídos</th><th scope="col">Pendentes</th><th scope="col">Cancelados</th></tr></thead><tbody>
        <?php foreach ($summary as $municipality => $row): $key = $municipality === '' ? '__unassigned' : $municipality; if ($selectedMunicipality !== '' && $selectedMunicipality !== $key) continue; ?>
            <tr><th scope="row"><a href="<?= e($scaleUrl($key)) ?>"><?= e($municipality ?: 'Sem concelho') ?></a></th><td><a href="<?= e($scaleUrl($key)) ?>"><?= $row['total'] ?></a></td><td><a href="<?= e($scaleUrl($key)) ?>"><?= number_format($row['hours'], 1, ',', ' ') ?> h</a></td><td><a href="<?= e($scaleUrl($key)) ?>"><?= count($row['carers']) ?></a></td><?php foreach (['DONE', 'SCHEDULED', 'CANCELLED'] as $state): ?><td><a href="<?= e($scaleUrl($key, $state)) ?>"><?= $row[$state] ?></a></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php if ($totals['total'] === 0): ?><p class="empty-state muted">Sem serviços neste dia para a seleção atual.</p><?php endif; ?>
    </section>
    <section class="dashboard-section" aria-labelledby="conflicts-title">
        <div class="section-head"><h2 id="conflicts-title">Conflitos e pendências</h2><span class="badge <?= $conflicts ? 'badge-alert' : 'badge-ok' ?>"><?= count($conflicts) ?> conflitos</span></div>
        <?php if (! $conflicts && ! $pending): ?><div class="empty-state"><strong>Sem conflitos ou pendências detetados.</strong><p class="muted">Serviços não cancelados no dia selecionado.</p></div><?php endif; ?>
        <?php foreach ($conflicts as [$first, $second]): ?>
            <article class="conflict-row"><div><strong><?= e($first['carer_name'] ?? 'Cuidadora sem atribuição') ?></strong><p>Sobreposição de horários</p><?php foreach ([$first, $second] as $service): ?><p class="muted"><?= date('d/m H:i', strtotime($service['starts_at'])) ?> - <?= date('d/m H:i', strtotime($service['ends_at'])) ?> · <?= e($service['patient_name'] ?? 'Sem cliente') ?> · <?= e($service['municipality'] ?: 'Sem concelho') ?></p><?php endforeach; ?></div><a class="btn btn-secondary" href="<?= e($scaleUrl('', '', (int) $first['carer_id'])) ?>">Rever escalas</a></article>
        <?php endforeach; ?>
        <?php foreach ($pending as $service): ?><article class="conflict-row"><div><strong><?= e($service['patient_name'] ?? 'Sem cliente') ?></strong><p class="muted"><?= e($service['carer_name'] ?? 'Sem cuidadora') ?> · <?= empty(trim((string) $service['municipality'])) ? 'Concelho por definir' : 'Atribuição incompleta' ?></p></div><a class="btn btn-secondary" href="<?= e($scaleUrl(empty(trim((string) $service['municipality'])) ? '__unassigned' : $service['municipality'], '', (int) $service['carer_id'])) ?>">Rever escala</a></article><?php endforeach; ?>
    </section>
    <?php if (! empty($newReports)): ?>
        <section class="notification-panel">
            <?php foreach ($newReports as $report): ?>
                <div class="alert alert-report"><strong>Novo relatório</strong><span>Recebido de <?= e($report['carer_name'] ?? 'cuidadora') ?>: <?= e($report['title']) ?></span><a class="btn btn-secondary" href="index.php?page=reports">Ver</a></div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <section class="grid stats dashboard-registers" aria-label="Registos e movimentos do mês">
        <div class="card">
            <p class="muted">Cuidadoras</p>
            <div class="stat-num"><?= $carersCount ?></div>
            <p class="metric-delta"><span class="delta-in">+<?= $newCarers ?> novas</span> · <span class="delta-out">-<?= $leftCarers ?> saíram</span> este mês</p>
        </div>
        <div class="card">
            <p class="muted">Clientes</p>
            <div class="stat-num"><?= $patientsCount ?></div>
            <p class="metric-delta"><span class="delta-in">+<?= $newPatients ?> novos</span> · <span class="delta-out">-<?= $leftPatients ?> saíram</span> este mês</p>
        </div>
    </section>
<?php });
