<?php
$user = require_auth();
$isCarer = $user['role'] === 'CARER';
$pdo = db();
$carers = $pdo->query('SELECT * FROM carers ORDER BY name')->fetchAll();
$patients = $pdo->query('SELECT * FROM patients ORDER BY name')->fetchAll();
$municipalities = ['Portimão', 'Lagos', 'Lagoa', 'Albufeira', 'Silves'];
$selectedMunicipality = $_GET['municipality'] ?? $municipalities[0];
if (! in_array($selectedMunicipality, $municipalities, true)) {
    $selectedMunicipality = $municipalities[0];
}
$periodOptions = ['day' => 'Dia', 'week' => 'Semana', 'month' => 'Mês'];
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
    $periodStartDate = new DateTime();
    $periodStartDate->setISODate((int) $weekParts[1], (int) $weekParts[2], 1);
    $periodEndDate = (clone $periodStartDate)->modify('+6 days');
    $periodLabel = $periodStartDate->format('d/m/Y') . ' a ' . $periodEndDate->format('d/m/Y');
    $selectedDate = $periodStartDate->format('Y-m-d');
} elseif ($selectedPeriod === 'month') {
    if (! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
        $selectedMonth = date('Y-m');
    }
    $periodStartDate = new DateTime($selectedMonth . '-01');
    $periodEndDate = (clone $periodStartDate)->modify('last day of this month');
    $periodLabel = $monthNames[(int) $periodStartDate->format('n')] . ' de ' . $periodStartDate->format('Y');
    $selectedDate = $periodStartDate->format('Y-m-d');
} else {
    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
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
    $periodEnd = $periodEndDate->format('Y-m-d') . ' 23:59:59';
    $sql = "SELECT services.*, patients.name AS patient_name, patients.address, carers.name AS carer_name, carers.color_hex
        FROM services
        JOIN patients ON patients.id = services.patient_id
        JOIN carers ON carers.id = services.carer_id
        WHERE services.starts_at < ? AND services.ends_at > ?
            AND COALESCE(services.municipality, '') = ?";
    $params = [$periodEnd, $periodStart, $selectedMunicipality];
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
        $servicesByDate[$serviceDate][] = $service;
        if (! isset($mapCarers[$carerId])) {
            $mapCarers[$carerId] = ['id' => $carerId, 'name' => $service['carer_name'], 'color_hex' => $service['color_hex']];
        }
    }
    uasort($mapCarers, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    for ($day = clone $periodStartDate; $day <= $periodEndDate; $day->modify('+1 day')) {
        $calendarDays[] = $day->format('Y-m-d');
    }
}

layout('Escalas - Care4All', function () use ($isCarer, $carers, $patients, $municipalities, $periodOptions, $selectedMunicipality, $selectedPeriod, $selectedDate, $selectedWeek, $selectedMonth, $periodLabel, $mapServices, $mapCarers, $servicesByHour, $servicesByDate, $calendarDays, $calendarHours, $selectedCarer, $selectedPatient) { ?>
    <?php if ($isCarer): ?>
        <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/pt.global.min.js"></script>
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
                <label class="field"><span class="label">Concelho</span><select class="input" name="municipality" id="map_municipality" required><?php foreach ($municipalities as $municipality): ?><option value="<?= e($municipality) ?>" <?= $selectedMunicipality === $municipality ? 'selected' : '' ?>><?= e($municipality) ?></option><?php endforeach; ?></select></label>
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
                <div class="scale-map-filter-actions"><button class="btn btn-primary" type="submit">Ver mapa</button></div>
            </form>
            <div class="scale-map-summary">
                <strong><?= e($selectedMunicipality) ?></strong>
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
            <?php if (empty($mapServices)): ?>
                <div class="empty-state"><strong>Sem escalas neste periodo.</strong><p class="muted">Ajuste os filtros ou adicione uma nova escala para este concelho.</p></div>
            <?php else: ?>
                <div class="scale-map-scroll">
                    <table class="scale-map-table scale-map-table-calendar <?= $selectedPeriod === 'day' ? 'hour-view' : 'date-view' ?>">
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
            <?php endif; ?>        </section>
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
                document.querySelectorAll('.scale-map-filters select, .scale-map-filters input').forEach((input) => input.addEventListener('change', () => input.form.submit()));
                document.querySelectorAll('[data-service-id]').forEach((button) => button.addEventListener('click', () => { clearService(); document.getElementById('service-form-title').textContent = 'Detalhes da escala'; document.getElementById('service-submit').textContent = 'Guardar altera\u00e7\u00f5es'; document.getElementById('service_id').value = button.dataset.serviceId || ''; document.getElementById('patient_id').value = button.dataset.patientId || ''; document.getElementById('carer_id').value = button.dataset.carerId || ''; document.getElementById('starts_at').value = button.dataset.startsAt || ''; document.getElementById('ends_at').value = button.dataset.endsAt || ''; document.getElementById('hourly_rate').value = button.datasetHourlyRate || button.dataset.hourlyRate || 5; document.getElementById('service_observations').value = button.dataset.observations || ''; document.getElementById('service_municipality').value = button.dataset.municipality || document.getElementById('map_municipality').value; document.querySelectorAll('.selected-service-id').forEach(i => i.value = button.dataset.serviceId || ''); document.getElementById('service-actions').hidden = false; updateServicePocketPreview(); }));
                document.getElementById('open-new-service')?.addEventListener('click', clearService);
                document.getElementById('clear-service')?.addEventListener('click', clearService);
                ['starts_at', 'ends_at', 'hourly_rate'].forEach((id) => document.getElementById(id)?.addEventListener('input', updateServicePocketPreview));
            <?php endif; ?>
        });
    </script>
<?php });

function render_service_chip(array $service, string $selectedMunicipality, bool $unused = false): void { ?>
    <button class="service-chip status-<?= strtolower(e($service['status'])) ?>" style="--chip-color: <?= e($service['color_hex'] ?: '#299c93') ?>" type="button" data-open-modal="service-modal" data-service-id="<?= $service['id'] ?>" data-patient-id="<?= $service['patient_id'] ?>" data-carer-id="<?= $service['carer_id'] ?>" data-starts-at="<?= e(date_input($service['starts_at'])) ?>" data-ends-at="<?= e(date_input($service['ends_at'])) ?>" data-hourly-rate="<?= e((string) round((float) $service['hourly_rate'])) ?>" data-observations="<?= e($service['observations'] ?? '') ?>" data-municipality="<?= e($service['municipality'] ?? $selectedMunicipality) ?>">
        <strong><?= date('H:i', strtotime($service['starts_at'])) ?> - <?= date('H:i', strtotime($service['ends_at'])) ?></strong>
        <span><?= e($service['patient_name']) ?></span>
        <span><?= e($service['carer_name']) ?></span>
        <?php if (trim((string) ($service['observations'] ?? '')) !== ''): ?><em>Obs!!</em><?php endif; ?>
    </button>
<?php }