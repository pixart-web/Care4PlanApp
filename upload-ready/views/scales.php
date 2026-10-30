<?php
require_once __DIR__ . '/../lib/scheduling.php';
$user = require_auth();
$isCarer = $user['role'] === 'CARER';
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
$periodOptions = ['day' => 'Dia', 'week' => 'Semana', 'month' => 'Mês', 'list' => 'Lista'];
$selectedPeriod = $_GET['period'] ?? 'day';
if (! isset($periodOptions[$selectedPeriod])) {
    $selectedPeriod = 'day';
}
$selectedDate = $_GET['scale_date'] ?? date('Y-m-d');
$selectedWeek = $_GET['scale_week'] ?? date('o-\WW');
$selectedMonth = $_GET['scale_month'] ?? date('Y-m');
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
    $sql = "SELECT services.*, patients.name AS patient_name, patients.address, carers.name AS carer_name, carers.color_hex
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

layout('Escalas - Care4All', function () use ($isCarer, $carers, $patients, $municipalities, $periodOptions, $selectedMunicipality, $selectedPeriod, $selectedDate, $selectedWeek, $selectedMonth, $periodLabel, $mapServices, $mapCarers, $servicesByHour, $servicesByDate, $calendarDays, $calendarHours, $selectedCarer, $selectedPatient, $selectedStatus, $search, $visibleCarers, $carerPage, $carerPages, $pageUrl) { ?>
    <?php if ($isCarer): ?>
        <script src="assets/vendor/fullcalendar-6.1.11.global.min.js"></script>
        <script src="assets/vendor/fullcalendar-pt-6.1.11.global.min.js"></script>
    <?php endif; ?>

    <div class="page-head">
        <div>
            <h1>Escalas</h1>
            <p class="muted"><?= $isCarer ? 'A tua agenda de servi&ccedil;os.' : 'Mapa em tabela por dia, semana ou m&ecirc;s, filtrado por concelho, utente e cuidadora.' ?></p>
        </div>
    </div>

    <?php if (! $isCarer): ?>
        <section class="card action-panel">
            <div class="action-panel-text">
                <h2>Gest&atilde;o de escalas</h2>
                <p class="muted">Escolha o concelho, associe o utente &agrave; cuidadora e acompanhe o mapa por per&iacute;odo.</p>
            </div>
            <div class="btn-row action-buttons">
                <button class="btn btn-primary" type="button" id="open-new-service" data-open-modal="service-modal">Adicionar escala</button>
            </div>
        </section>

        <section class="card scale-map-shell">
            <form class="scale-map-filters" method="get">
                <input type="hidden" name="page" value="scales">
                <label class="field"><span class="label">Concelho</span><select class="input" name="municipality" id="map_municipality"><option value="all" <?= $selectedMunicipality === 'all' ? 'selected' : '' ?>>Todos os concelhos</option><option value="__unassigned" <?= $selectedMunicipality === '__unassigned' ? 'selected' : '' ?>>Sem concelho</option><?php foreach ($municipalities as $municipality): ?><option value="<?= e($municipality) ?>" <?= $selectedMunicipality === $municipality ? 'selected' : '' ?>><?= e($municipality) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="label">Vista</span><select class="input" name="period" id="map_period"><?php foreach ($periodOptions as $periodKey => $periodLabelOption): ?><option value="<?= e($periodKey) ?>" <?= $selectedPeriod === $periodKey ? 'selected' : '' ?>><?= e($periodLabelOption) ?></option><?php endforeach; ?></select></label>
                <?php if ($selectedPeriod === 'week'): ?>
                    <label class="field"><span class="label">Semana</span><input class="input" type="week" name="scale_week" value="<?= e($selectedWeek) ?>"></label>
                <?php elseif ($selectedPeriod === 'month'): ?>
                    <label class="field"><span class="label">M&ecirc;s</span><input class="input" type="month" name="scale_month" value="<?= e($selectedMonth) ?>"></label>
                <?php else: ?>
                    <label class="field"><span class="label">Dia</span><input class="input" type="date" name="scale_date" value="<?= e($selectedDate) ?>"></label>
                <?php endif; ?>
                <label class="field"><span class="label">Cuidadora</span><select class="input" name="carer_id"><option value="0">Todas as cuidadoras</option><?php foreach ($carers as $carer): ?><option value="<?= $carer['id'] ?>" <?= $selectedCarer === (int) $carer['id'] ? 'selected' : '' ?>><?= e($carer['name']) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="label">Utente</span><select class="input" name="patient_id"><option value="0">Todos os utentes</option><?php foreach ($patients as $patient): ?><option value="<?= $patient['id'] ?>" <?= $selectedPatient === (int) $patient['id'] ? 'selected' : '' ?>><?= e($patient['name']) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="label">Estado</span><select class="input" name="status"><?php foreach (['' => 'Todos', 'SCHEDULED' => 'Agendado', 'DONE' => 'Realizado', 'CANCELLED' => 'Cancelado'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === $selectedStatus ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="label">Pesquisar</span><input class="input" type="search" name="search" value="<?= e($search) ?>" maxlength="200"></label>
                <div class="scale-map-filter-actions"><button class="btn btn-primary" type="submit">Ver mapa</button></div>
            </form>
            <div class="scale-map-summary">
                <strong><?= e($selectedMunicipality === 'all' ? 'Todos os concelhos' : ($selectedMunicipality === '__unassigned' ? 'Sem concelho' : $selectedMunicipality)) ?></strong>
                <span><?= e($periodOptions[$selectedPeriod]) ?>: <?= e($periodLabel) ?></span>
                <span><?= count($mapServices) ?> servi&ccedil;o<?= count($mapServices) === 1 ? '' : 's' ?></span>
                <span><?= count($mapCarers) ?> cuidadora<?= count($mapCarers) === 1 ? '' : 's' ?></span>
                <span><?= count(array_unique(array_column($mapServices, 'patient_id'))) ?> utente<?= count(array_unique(array_column($mapServices, 'patient_id'))) === 1 ? '' : 's' ?></span>
            </div>
            <?php if (! empty($mapCarers)): ?>
                <div class="scale-carer-legend" aria-label="Cuidadoras visiveis no mapa">
                    <?php foreach ($mapCarers as $carer): ?>
                        <span class="carer-legend-chip" style="--carer-color: <?= e($carer['color_hex'] ?: '#299c93') ?>"><?= e($carer['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div id="scales-mobile-list" <?= $selectedPeriod === 'list' ? '' : 'hidden' ?>>
                <?php foreach ($mapServices as $service): ?>
                    <div><strong><?= e(substr($service['starts_at'], 0, 10)) ?> / <?= e($service['municipality']) ?></strong><?php render_service_chip($service, $selectedMunicipality); ?></div>
                <?php endforeach; ?>
                <?php if (! $mapServices): ?><p>Sem escalas neste per&iacute;odo.</p><?php endif; ?>
            </div>
            <div id="scales-desktop-calendar" data-period="<?= e($selectedPeriod) ?>" <?= $selectedPeriod === 'list' ? 'hidden' : '' ?>>
            <?php if ($selectedPeriod === 'day'): ?>
                <nav class="btn-row scales-v2-pager" aria-label="P&aacute;ginas de cuidadoras">
                    <?php if ($carerPage > 1): ?><a class="btn btn-secondary" href="<?= e($pageUrl($carerPage - 1)) ?>" aria-label="Cuidadoras anteriores">&larr;</a><?php endif; ?>
                    <span><?= $carerPage ?> / <?= $carerPages ?></span>
                    <?php if ($carerPage < $carerPages): ?><a class="btn btn-secondary" href="<?= e($pageUrl($carerPage + 1)) ?>" aria-label="Cuidadoras seguintes">&rarr;</a><?php endif; ?>
                </nav>
                <div class="scale-map-scroll scales-v2-day-scroll" style="max-height:75vh;overflow:auto">
                    <div class="scales-v2-day-grid" style="display:grid;grid-template-columns:64px repeat(<?= max(1, count($visibleCarers)) ?>,minmax(140px,1fr));min-width:<?= 64 + max(1, count($visibleCarers)) * 140 ?>px">
                        <div style="position:sticky;top:0;z-index:3;background:white">Hora</div>
                        <?php foreach ($visibleCarers as $carer): ?><strong class="scales-v2-carer-heading" style="position:sticky;top:0;z-index:3;background:white;padding:8px;overflow-wrap:anywhere"><?= e($carer['name']) ?></strong><?php endforeach; ?>
                        <div class="scales-v2-hour-labels" style="position:relative;height:1728px">
                            <?php foreach ($calendarHours as $hour): ?><span style="position:absolute;top:<?= $hour * 72 ?>px;font-size:12px"><?= sprintf('%02d:00', $hour) ?></span><?php endforeach; ?>
                        </div>
                        <?php foreach ($visibleCarers as $carer): ?>
                            <div class="scales-v2-carer-column" style="position:relative;height:1728px;border-left:1px solid #ddd">
                                <?php foreach ($calendarHours as $hour): ?><div class="scales-v2-hour-line" style="position:absolute;left:0;right:0;top:<?= $hour * 72 ?>px;height:72px;border-top:1px solid #ddd;pointer-events:none"></div><?php endforeach; ?>
                                <?php foreach (scales_day_segments(array_values(array_filter($mapServices, fn ($service) => (int) $service['carer_id'] === (int) $carer['id'])), $selectedDate) as $service):
                                    $startMinute = $service['_start_minute'];
                                    $endMinute = $service['_end_minute'];
                                    ?>
                                    <div class="scales-v2-event" style="position:absolute;left:calc(<?= 100 * $service['_lane'] / $service['_lanes'] ?>% + 2px);width:calc(<?= 100 / $service['_lanes'] ?>% - 4px);top:<?= $startMinute * 1.2 ?>px;height:<?= ($endMinute - $startMinute) * 1.2 ?>px;overflow:hidden" title="<?= e($service['patient_name'] . ' ' . substr($service['starts_at'], 11, 5) . ' - ' . substr($service['ends_at'], 11, 5)) ?>"><?php render_service_chip($service, $selectedMunicipality, true, $endMinute - $startMinute); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif (empty($mapServices)): ?>
                <div class="empty-state"><strong>Sem escalas neste periodo.</strong><p class="muted">Ajuste os filtros ou adicione uma nova escala para este concelho.</p></div>
            <?php else: ?>
                <div class="scale-map-scroll">
                    <table class="scale-map-table scale-map-table-calendar <?= $selectedPeriod === 'week' ? 'calendar-week' : 'calendar-month' ?> date-view">
                        <thead>
                            <tr>
                                <?php if ($selectedPeriod === 'day'): ?>
                                    <?php foreach ($calendarHours as $hour): ?>
                                        <th><strong><?= sprintf('%02d:00', $hour) ?></strong></th>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($calendarDays as $calendarDay): ?>
                                        <th><strong><?= date('d/m', strtotime($calendarDay)) ?></strong><span><?= ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'][(int) date('w', strtotime($calendarDay))] ?></span></th>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php if ($selectedPeriod === 'day'): ?>
                                    <?php foreach ($calendarHours as $hour): $cellServices = $servicesByHour[$hour] ?? []; ?>
                                        <td class="scale-map-cell <?= empty($cellServices) ? 'empty' : '' ?>">
                                            <?php foreach ($cellServices as $service): ?><?php render_service_chip($service, $selectedMunicipality, false); ?><?php endforeach; ?>
                                        </td>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($calendarDays as $calendarDay): $cellServices = $servicesByDate[$calendarDay] ?? []; ?>
                                        <td class="scale-map-cell <?= empty($cellServices) ? 'empty' : '' ?>">
                                            <?php foreach ($cellServices as $service): ?><?php render_service_chip($service, $selectedMunicipality, false); ?><?php endforeach; ?>
                                        </td>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="card list-panel calendar-panel"><div class="list-panel-head calendar-panel-head"><div><h2>Calend&aacute;rio</h2><p class="muted">Consulta e atualiza os teus servi&ccedil;os.</p></div></div><div class="calendar-body"><div id="calendar"></div></div></section>
    <?php endif; ?>

    <?php if (! $isCarer): ?>
        <div class="modal-backdrop" id="service-modal" hidden><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="service-form-title"><div class="modal-head"><div><h2 id="service-form-title">Adicionar escala</h2><p class="muted">Escolha o concelho, utente, cuidadora, hor&aacute;rio, pocket e observa&ccedil;&otilde;es.</p></div><button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button></div><form method="post" id="service-form" class="modal-body"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="save_service"><input type="hidden" name="id" id="service_id"><label class="field"><span class="label">Concelho</span><select class="input" name="municipality" id="service_municipality" required><?php foreach ($municipalities as $municipality): ?><option value="<?= e($municipality) ?>" <?= $selectedMunicipality === $municipality ? 'selected' : '' ?>><?= e($municipality) ?></option><?php endforeach; ?></select></label><label class="field"><span class="label">Utente</span><select class="input" name="patient_id" id="patient_id" required><option value="">Selecionar</option><?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></label><label class="field"><span class="label">Cuidadora</span><select class="input" name="carer_id" id="carer_id" required><option value="">Selecionar</option><?php foreach ($carers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label><div class="form-grid"><label class="field"><span class="label">Hora de entrada</span><input class="input" name="starts_at" id="starts_at" type="datetime-local" required></label><label class="field"><span class="label">Hora de sa&iacute;da</span><input class="input" name="ends_at" id="ends_at" type="datetime-local" required></label></div><label class="field"><span class="label">Pocket</span><select class="input" name="hourly_rate" id="hourly_rate" required><?php for ($rate = 5; $rate <= 15; $rate++): ?><option value="<?= $rate ?>"><?= $rate ?> EUR / hora</option><?php endfor; ?></select></label><label class="field"><span class="label">Observa&ccedil;&otilde;es</span><textarea class="input textarea" name="observations" id="service_observations" rows="4"></textarea></label><div class="pocket-preview"><span>Valor do pocket</span><strong id="service-pocket-preview">0,00 EUR</strong></div><div class="btn-row modal-actions"><button class="btn btn-primary" type="submit" id="service-submit">Associar</button><button class="btn btn-secondary" type="button" id="clear-service">Limpar</button></div></form><div class="modal-body service-danger-actions" id="service-actions" hidden><div class="btn-row"><form method="post"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="cancel_service"><input type="hidden" name="id" class="selected-service-id"><button class="btn btn-secondary">Cancelar escala</button></form><form method="post" onsubmit="return confirm('Apagar escala?')"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="delete_service"><input type="hidden" name="id" class="selected-service-id"><button class="btn btn-danger">Apagar</button></form></div></div></section></div>
    <?php endif; ?>

    <?php if ($isCarer): ?>
        <div class="modal-backdrop" id="carer-selected" hidden><section class="modal-card modal-card-sm" role="dialog" aria-modal="true" aria-labelledby="selected-title"><div class="modal-head"><div><h2 id="selected-title"></h2><p class="muted" id="selected-address"></p></div><button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button></div><div class="modal-body"><p><strong>Estado:</strong> <span id="selected-status"></span></p><p style="margin-top:10px"><strong>Observa&ccedil;&otilde;es:</strong> <span id="selected-observations"></span></p><p style="margin-top:10px"><strong>Pocket:</strong> <span id="selected-pocket"></span></p><div class="btn-row modal-actions"><form method="post"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="done_service"><input type="hidden" name="id" id="done_id"><button class="btn btn-primary">Realizado</button></form><form method="post"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="undo_service"><input type="hidden" name="id" id="undo_id"><button class="btn btn-secondary">Desfazer</button></form></div></div></section></div>
    <?php endif; ?>

    <script>
        function localInput(date) { const copy = new Date(date); copy.setMinutes(copy.getMinutes() - copy.getTimezoneOffset()); return copy.toISOString().slice(0, 16); }
        function money(value) { return Number(value || 0).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' }); }
        function servicePocketValue() { const start = document.getElementById('starts_at'); const end = document.getElementById('ends_at'); const rate = document.getElementById('hourly_rate'); if (!start || !end || !rate || !start.value || !end.value) return 0; return Math.max(0, (new Date(end.value) - new Date(start.value)) / 3600000) * Number(rate.value || 0); }
        function updateServicePocketPreview() { const preview = document.getElementById('service-pocket-preview'); if (preview) preview.textContent = money(servicePocketValue()); }
        function clearService() { const form = document.getElementById('service-form'); if (!form) return; form.reset(); document.getElementById('service_id').value = ''; document.getElementById('service_municipality').value = document.getElementById('map_municipality')?.value || '<?= e($selectedMunicipality) ?>'; const day = '<?= e($selectedDate) ?>'; document.getElementById('starts_at').value = day + 'T09:00'; document.getElementById('ends_at').value = day + 'T10:00'; document.getElementById('hourly_rate').value = 5; document.getElementById('service_observations').value = ''; document.getElementById('service-form-title').textContent = 'Adicionar escala'; document.getElementById('service-submit').textContent = 'Associar'; document.getElementById('service-actions').hidden = true; updateServicePocketPreview(); }
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($isCarer): ?>
                const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), { locale: 'pt', buttonText: { today: 'Hoje', month: 'M\u00eas', week: 'Semana', day: 'Dia', list: 'Lista' }, allDayText: 'Dia inteiro', noEventsText: 'Sem escalas para mostrar', timeZone: 'Europe/Lisbon', initialView: window.innerWidth < 700 ? 'timeGridDay' : 'timeGridWeek', height: 'auto', headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' }, slotMinTime: '00:00:00', slotMaxTime: '24:00:00', events: function (info, success, failure) { const params = new URLSearchParams({ page: 'api.events', start: info.startStr, end: info.endStr }); fetch('index.php?' + params).then(r => r.json()).then(success).catch(failure); }, eventContent: function(arg) { const title = document.createElement('div'); title.className = 'fc-event-title-custom'; title.textContent = arg.event.title; if (arg.event.extendedProps.has_observations) { const obs = document.createElement('span'); obs.className = 'obs-flag'; obs.textContent = 'Obs!!'; title.appendChild(obs); } return { domNodes: [title] }; }, eventClick: function(info) { const event = info.event; const props = event.extendedProps; document.getElementById('carer-selected').hidden = false; document.getElementById('selected-title').textContent = event.title; document.getElementById('selected-address').textContent = props.address || ''; document.getElementById('selected-status').textContent = props.status; document.getElementById('selected-observations').textContent = props.observations || '-'; document.getElementById('selected-pocket').textContent = money(props.pocket_value); document.getElementById('done_id').value = event.id; document.getElementById('undo_id').value = event.id; } }); calendar.render();
            <?php else: ?>
                document.querySelectorAll('.scale-map-filters select').forEach((input) => input.addEventListener('change', () => input.form.submit()));
                document.querySelectorAll('[data-service-id]').forEach((button) => button.addEventListener('click', () => { clearService(); document.getElementById('service-form-title').textContent = 'Detalhes da escala'; document.getElementById('service-submit').textContent = 'Guardar altera\u00e7\u00f5es'; document.getElementById('service_id').value = button.dataset.serviceId || ''; document.getElementById('patient_id').value = button.dataset.patientId || ''; document.getElementById('carer_id').value = button.dataset.carerId || ''; document.getElementById('starts_at').value = button.dataset.startsAt || ''; document.getElementById('ends_at').value = button.dataset.endsAt || ''; document.getElementById('hourly_rate').value = button.datasetHourlyRate || button.dataset.hourlyRate || 5; document.getElementById('service_observations').value = button.dataset.observations || ''; document.getElementById('service_municipality').value = button.dataset.municipality || document.getElementById('map_municipality').value; document.querySelectorAll('.selected-service-id').forEach(i => i.value = button.dataset.serviceId || ''); document.getElementById('service-actions').hidden = false; updateServicePocketPreview(); }));
                document.getElementById('open-new-service')?.addEventListener('click', clearService);
                document.getElementById('clear-service')?.addEventListener('click', clearService);
                ['starts_at', 'ends_at', 'hourly_rate'].forEach((id) => document.getElementById(id)?.addEventListener('input', updateServicePocketPreview));
            <?php endif; ?>
        });
    </script>
    <?php if (! $isCarer): ?><script src="assets/scales-v2.js" defer></script><?php endif; ?>
<?php });

function render_service_chip(array $service, string $selectedMunicipality, bool $unused = false, int $visibleMinutes = 1440): void {
    $description = $service['patient_name'] . ', ' . $service['carer_name'] . ', ' . $service['starts_at'] . ' - ' . $service['ends_at'];
    $geometry = $unused ? 'height:100%;min-height:0;overflow:hidden;width:100%;margin:0;padding:0 4px;border-top:0;border-bottom:0;font-size:12px' : '';
    ?>
    <button class="service-chip status-<?= strtolower(e($service['status'])) ?>" style="--chip-color: <?= e(preg_match('/^#[0-9a-f]{6}$/i', $service['color_hex'] ?? '') ? $service['color_hex'] : '#299c93') ?>;<?= $geometry ?>" type="button" title="<?= e($description) ?>" aria-label="<?= e($description) ?>" data-open-modal="service-modal" data-service-id="<?= $service['id'] ?>" data-patient-id="<?= $service['patient_id'] ?>" data-carer-id="<?= $service['carer_id'] ?>" data-starts-at="<?= e(date_input($service['starts_at'])) ?>" data-ends-at="<?= e(date_input($service['ends_at'])) ?>" data-hourly-rate="<?= e((string) (float) $service['hourly_rate']) ?>" data-observations="<?= e($service['observations'] ?? '') ?>" data-municipality="<?= e($service['municipality'] ?? $selectedMunicipality) ?>">
        <?php if ($unused && $visibleMinutes < 30): ?>
            <span style="display:<?= $visibleMinutes < 10 ? 'none' : 'block' ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:12px;font-size:10px"><?= e(substr($service['starts_at'], 11, 5) . ' ' . $service['patient_name']) ?></span>
            <span style="position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)"><?= e($service['patient_name'] . ', ' . $service['carer_name'] . ', ' . substr($service['starts_at'], 11, 5) . ' - ' . substr($service['ends_at'], 11, 5)) ?></span>
        <?php else: ?>
        <strong><?= date('H:i', strtotime($service['starts_at'])) ?> - <?= date('H:i', strtotime($service['ends_at'])) ?></strong>
        <span><?= e($service['patient_name']) ?></span>
        <span><?= e($service['carer_name']) ?></span>
        <?php if (!$unused): ?><span class="service-state"><?= e(['SCHEDULED' => 'Agendado', 'DONE' => 'Concluído', 'CANCELLED' => 'Cancelado'][$service['status']] ?? $service['status']) ?></span><?php endif; ?>
        <?php if (trim((string) ($service['observations'] ?? '')) !== ''): ?><em>Obs!!</em><?php endif; ?>
        <?php endif; ?>
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
