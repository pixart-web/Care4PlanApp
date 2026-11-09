<?php
$statusLabels = ['SCHEDULED' => 'Agendado', 'DONE' => 'Concluído', 'CANCELLED' => 'Cancelado'];
$statusCounts = array_count_values(array_column($mapServices, 'status'));
$conflictCount = count(array_filter($mapServices, fn ($service) => !empty($service['has_conflict'])));
$activeHours = 0;
foreach ($mapServices as $service) {
    if ($service['status'] !== 'CANCELLED') $activeHours += max(0, (min(strtotime($service['ends_at']), strtotime($periodEnd)) - max(strtotime($service['starts_at']), strtotime($periodStart))) / 3600);
}
$scopeLabel = $selectedMunicipality === 'all' ? 'Todos os concelhos' : ($selectedMunicipality === '__unassigned' ? 'Sem concelho' : $selectedMunicipality);
$filterCount = (int) ($selectedCarer > 0) + (int) ($selectedPatient > 0) + (int) ($selectedStatus !== '') + (int) ($search !== '');
$calendarUrl = static function (array $changes) use ($selectedDate, $selectedPeriod): string {
    $date = new DateTimeImmutable($changes['scale_date'] ?? $selectedDate);
    return 'index.php?' . http_build_query(array_merge($_GET, ['page' => 'scales', 'period' => $selectedPeriod, 'scale_date' => $date->format('Y-m-d'), 'scale_week' => $date->format('o-\WW'), 'scale_month' => $date->format('Y-m'), 'carer_page' => 1], $changes));
};
$step = $selectedPeriod === 'month' ? 'month' : ($selectedPeriod === 'week' ? 'week' : 'day');
$previousUrl = $calendarUrl(['scale_date' => (clone $periodStartDate)->modify('-1 ' . $step)->format('Y-m-d')]);
$nextUrl = $calendarUrl(['scale_date' => (clone $periodStartDate)->modify('+1 ' . $step)->format('Y-m-d')]);

layout('Escalas - Care4All', function () use ($carers, $patients, $municipalities, $periodOptions, $selectedMunicipality, $selectedPeriod, $selectedDate, $selectedWeek, $selectedMonth, $periodLabel, $mapServices, $mapCarers, $servicesByDate, $calendarDays, $calendarHours, $selectedCarer, $selectedPatient, $selectedStatus, $search, $visibleCarers, $carerPage, $carerPages, $pageUrl, $calendarUrl, $previousUrl, $nextUrl, $scopeLabel, $statusLabels, $statusCounts, $activeHours, $conflictCount, $filterCount) { ?>
    <link rel="stylesheet" href="assets/atelier-scales.css">
    <div class="planning-heading"><div><h1>Escalas</h1><p class="muted"><?= e($scopeLabel) ?> <span aria-hidden="true">/</span> <?= e($periodLabel) ?></p></div><button class="btn btn-primary" id="open-new-service" type="button" data-open-modal="service-modal"><i data-lucide="plus" aria-hidden="true"></i>Nova escala</button></div>

    <section class="planning-workspace" aria-label="Planeamento de serviços">
        <div class="planning-toolbar">
            <div class="date-navigation"><a class="icon-button" href="<?= e($previousUrl) ?>" aria-label="Período anterior" title="Período anterior"><i data-lucide="chevron-left" aria-hidden="true"></i></a><a class="today-button" href="<?= e($calendarUrl(['scale_date' => date('Y-m-d')])) ?>">Hoje</a><a class="icon-button" href="<?= e($nextUrl) ?>" aria-label="Período seguinte" title="Período seguinte"><i data-lucide="chevron-right" aria-hidden="true"></i></a></div>
            <label class="planning-date"><span class="sr-only"><?= $selectedPeriod === 'week' ? 'Semana' : ($selectedPeriod === 'month' ? 'Mês' : 'Data') ?></span><input class="input" form="scale-filter-form" type="<?= $selectedPeriod === 'week' ? 'week' : ($selectedPeriod === 'month' ? 'month' : 'date') ?>" name="<?= $selectedPeriod === 'week' ? 'scale_week' : ($selectedPeriod === 'month' ? 'scale_month' : 'scale_date') ?>" value="<?= e($selectedPeriod === 'week' ? $selectedWeek : ($selectedPeriod === 'month' ? $selectedMonth : $selectedDate)) ?>"></label>
            <nav class="view-segments" aria-label="Vista do calendário"><?php foreach ($periodOptions as $key => $label): ?><a href="<?= e($calendarUrl(['period' => $key])) ?>" <?= $selectedPeriod === $key ? 'aria-current="true"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
            <label class="planning-municipality"><span class="sr-only">Concelho</span><i data-lucide="map-pin" aria-hidden="true"></i><select class="input" form="scale-filter-form" name="municipality" id="map_municipality"><option value="all" <?= $selectedMunicipality === 'all' ? 'selected' : '' ?>>Todos os concelhos</option><option value="__unassigned" <?= $selectedMunicipality === '__unassigned' ? 'selected' : '' ?>>Sem concelho</option><?php foreach ($municipalities as $municipality): ?><option <?= $selectedMunicipality === $municipality ? 'selected' : '' ?>><?= e($municipality) ?></option><?php endforeach; ?></select></label>
            <button class="btn btn-secondary" type="button" data-open-modal="copy-month-modal">Duplicar mês</button><button class="btn btn-secondary filter-toggle" id="toggle-planning-filters" type="button" aria-expanded="false" aria-controls="planning-rail"><i data-lucide="sliders-horizontal" aria-hidden="true"></i>Filtros<?= $filterCount ? ' (' . $filterCount . ')' : '' ?></button>
        </div>
        <div class="planning-context"><div class="scale-map-summary"><span><?= count($mapServices) ?> <?= count($mapServices) === 1 ? 'serviço' : 'serviços' ?></span><span><?= count($mapCarers) ?> <?= count($mapCarers) === 1 ? 'cuidadora' : 'cuidadoras' ?></span><span><?= number_format($activeHours, 1, ',', ' ') ?> h</span></div><div class="planning-state-key"><span><b class="state-dot scheduled"></b>Agendado</span><span><b class="state-dot done"></b>Concluído</span><span><b class="state-dot cancelled"></b>Cancelado</span><?php if ($conflictCount): ?><a class="conflict-indicator" href="#planning-conflicts"><i data-lucide="triangle-alert" aria-hidden="true"></i><?= $conflictCount ?> em conflito</a><?php endif; ?></div></div>
        <div class="planning-body">
            <div class="planning-calendar">
                <?php
                $rowByCarer=($_GET['rows']??'clients')==='carers';
                $matrixPatients = [];
                $matrixServices = [];
                foreach ($servicesByDate as $date => $services) {
                    foreach ($services as $service) {
                        $rowId=$rowByCarer?$service['carer_id']:$service['patient_id'];
                        $matrixPatients[$rowId]=$rowByCarer?$service['carer_name']:$service['patient_name'];
                        if($rowByCarer)$service['_display_person']=$service['patient_name'];
                        $matrixServices[$rowId][$date][]=$service;
                    }
                }
                if (!$rowByCarer && !$selectedStatus && !$search) {
                    foreach ($patients as $patient) {
                        if ((!$selectedPatient || $selectedPatient === (int)$patient['id']) && ($selectedMunicipality === 'all' || ($selectedMunicipality === '__unassigned' ? empty($patient['municipality']) : ($patient['municipality'] ?? '') === $selectedMunicipality))) $matrixPatients[$patient['id']] = $patient['name'];
                    }
                }
                if($rowByCarer && !$selectedStatus && !$search)foreach($carers as $carer)if(!$selectedCarer || $selectedCarer===(int)$carer['id'])$matrixPatients[$carer['id']]=$carer['name'];
                asort($matrixPatients, SORT_NATURAL | SORT_FLAG_CASE);
                ?>
                <p class="calendar-help" id="calendar-help">Passe o rato sobre um serviço para ver os detalhes. Duplo clique para editar.</p>
                <div id="scales-desktop-calendar" class="patient-calendar-scroll" data-period="<?= e($selectedPeriod) ?>" tabindex="0" role="region" aria-label="Calendário por <?= $rowByCarer?'cuidadora':'cliente' ?>" aria-describedby="calendar-help">
                    <table class="patient-calendar">
                        <caption class="sr-only">Clientes e serviços · <?= e($periodLabel) ?></caption>
                        <thead><tr><th scope="col" class="patient-corner"><?= $rowByCarer?'Cuidadora':'Cliente' ?></th><?php foreach ($calendarDays as $day): ?><th scope="col" class="<?= $day === date('Y-m-d') ? 'is-today' : '' ?>"><span><?= ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][(int) date('w', strtotime($day))] ?></span><strong><?= date('d/m', strtotime($day)) ?></strong></th><?php endforeach; ?></tr></thead>
                        <tbody><?php foreach ($matrixPatients as $patientId => $patientName): ?><tr><th scope="row"><span class="patient-avatar" aria-hidden="true"><i data-lucide="user-round"></i></span><span><?= e($patientName) ?></span></th><?php foreach ($calendarDays as $day): ?><td class="<?= $day === date('Y-m-d') ? 'is-today' : '' ?>"><div class="patient-day-services"><?php foreach ($matrixServices[$patientId][$day] ?? [] as $service):
                            $start = max($service['starts_at'], $day . ' 00:00:00');
                            $nextDay = (new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
                            $end = min($service['ends_at'], $nextDay);
                            $formatHour = static fn ($value) => date('i', strtotime($value)) === '00' ? date('H', strtotime($value)) . 'h' : date('H', strtotime($value)) . 'h' . date('i', strtotime($value));
                            $service['_display_time'] = $formatHour($start) . '–' . ($end === $nextDay ? '24h' : $formatHour($end));
                            render_service_chip($service, $selectedMunicipality);
                        endforeach; ?><?php if (empty($matrixServices[$patientId][$day])): ?><span class="empty-day" aria-label="Sem serviços">·</span><?php endif; ?></div></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                    </table>
                    <?php if (!$matrixPatients): ?><div class="empty-state">Sem clientes ou serviços para os filtros selecionados.</div><?php endif; ?>
                </div>
                <div class="service-hover" id="service-hover" role="tooltip" hidden></div>
            </div>
            <aside class="planning-rail" id="planning-rail" hidden>
                <div class="rail-heading"><h2>Filtros e resumo</h2><button class="icon-button" type="button" data-close-rail aria-label="Fechar filtros" title="Fechar filtros"><i data-lucide="x" aria-hidden="true"></i></button></div>
                <form class="scale-map-filters" id="scale-filter-form" method="get"><input type="hidden" name="page" value="scales"><input type="hidden" name="period" value="<?= e($selectedPeriod) ?>">
                    <label class="field"><span class="label">Linhas do calendário</span><select class="input" name="rows"><option value="clients" <?= !$rowByCarer?'selected':'' ?>>Clientes</option><option value="carers" <?= $rowByCarer?'selected':'' ?>>Cuidadoras</option></select></label><label class="field"><span class="label">Cuidadora</span><select class="input" name="carer_id"><option value="0">Todas as cuidadoras</option><?php foreach ($carers as $carer): ?><option value="<?= $carer['id'] ?>" <?= $selectedCarer === (int) $carer['id'] ? 'selected' : '' ?>><?= e($carer['name']) ?></option><?php endforeach; ?></select></label>
                    <label class="field"><span class="label">Cliente</span><select class="input" name="patient_id"><option value="0">Todos os clientes</option><?php foreach ($patients as $patient): ?><option value="<?= $patient['id'] ?>" <?= $selectedPatient === (int) $patient['id'] ? 'selected' : '' ?>><?= e($patient['name']) ?></option><?php endforeach; ?></select></label>
                    <label class="field"><span class="label">Estado</span><select class="input" name="status"><option value="">Todos os estados</option><?php foreach ($statusLabels as $key => $label): ?><option value="<?= $key ?>" <?= $selectedStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label class="field"><span class="label">Pesquisar</span><input class="input" type="search" name="search" value="<?= e($search) ?>" maxlength="200" placeholder="Cliente ou cuidadora"></label>
                    <div class="btn-row"><button class="btn btn-primary" type="submit">Aplicar</button><a class="btn btn-secondary" href="<?= e($calendarUrl(['carer_id' => 0, 'patient_id' => 0, 'status' => '', 'search' => ''])) ?>">Limpar</a></div>
                </form>
                <section class="rail-summary"><h3><?= e($scopeLabel) ?></h3><p class="muted"><?= e($periodLabel) ?></p><dl><dt>Serviços</dt><dd><?= count($mapServices) ?></dd><dt>Horas não canceladas</dt><dd><?= number_format($activeHours, 1, ',', ' ') ?> h</dd><?php foreach ($statusLabels as $key => $label): ?><dt><?= e($label) ?></dt><dd><?= $statusCounts[$key] ?? 0 ?></dd><?php endforeach; ?></dl></section>
                <?php if ($conflictCount): ?><section class="rail-conflicts" id="planning-conflicts"><h3>Conflitos</h3><?php foreach ($mapServices as $service): if (empty($service['has_conflict'])) continue; ?><button type="button" class="conflict-link" data-open-modal="service-details" data-select-service="<?= $service['id'] ?>"><?= e($service['carer_name']) ?><span><?= date('d/m H:i', strtotime($service['starts_at'])) ?></span></button><?php endforeach; ?></section><?php endif; ?>
            </aside>
        </div>
    </section>

    <div class="modal-backdrop service-detail-backdrop" id="service-details" hidden><section class="modal-card service-detail-panel" role="dialog" aria-modal="true" aria-labelledby="service-details-title"><div class="modal-head"><div><p class="muted">Serviço</p><h2 id="service-details-title">Detalhes da escala</h2></div><button class="icon-button" type="button" data-close-modal aria-label="Fechar detalhes" title="Fechar detalhes"><i data-lucide="x" aria-hidden="true"></i></button></div><div class="modal-body"><span class="detail-status" id="detail-status"></span><h3 id="detail-patient"></h3><p id="detail-carer"></p><dl class="service-details-list"><dt>Data</dt><dd id="detail-date"></dd><dt>Horário</dt><dd id="detail-time"></dd><dt>Concelho</dt><dd id="detail-municipality"></dd><dt>Valor horário</dt><dd id="detail-rate"></dd><dt>Pocket do serviço</dt><dd id="detail-pocket"></dd></dl><section class="detail-notes"><h3>Observações</h3><p id="detail-observations"></p></section><div class="alert alert-error" id="detail-conflict" hidden>Sobreposição de horário com outro serviço desta cuidadora.</div><button class="btn btn-primary" id="edit-selected-service" type="button"><i data-lucide="pencil" aria-hidden="true"></i>Editar escala</button></div></section></div>

    <div class="modal-backdrop service-editor-backdrop" id="service-modal" hidden><section class="modal-card service-editor" role="dialog" aria-modal="true" aria-labelledby="service-form-title"><div class="modal-head"><div><p class="muted">Planeamento</p><h2 id="service-form-title">Nova escala</h2></div><button class="icon-button" type="button" data-close-modal aria-label="Fechar" title="Fechar"><i data-lucide="x" aria-hidden="true"></i></button></div>
        <form method="post" id="service-form" class="service-editor-grid" data-date="<?= e($selectedDate) ?>"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="save_service"><input type="hidden" name="id" id="service_id">
            <div class="service-editor-fields"><fieldset><legend>Dados do serviço</legend><div class="form-grid"><label class="field"><span class="label">Cliente</span><select class="input" name="patient_id" id="patient_id" required><option value="">Selecionar</option><?php foreach ($patients as $patient): ?><option value="<?= $patient['id'] ?>" data-municipality="<?= e($patient['municipality']??'') ?>"><?= e($patient['name']) ?></option><?php endforeach; ?></select></label><label class="field"><span class="label">Cuidadora</span><select class="input" name="carer_id" id="carer_id" required><option value="">Selecionar</option><?php foreach ($carers as $carer): ?><option value="<?= $carer['id'] ?>" data-weekday="<?= e((string)($carer['hourly_rate_weekday']??'')) ?>" data-special="<?= e((string)($carer['hourly_rate_special']??'')) ?>"><?= e($carer['name']) ?></option><?php endforeach; ?></select></label></div><div class="form-grid"><label class="field"><span class="label">Concelho</span><select class="input" name="municipality" id="service_municipality" required><?php foreach ($municipalities as $municipality): ?><option <?= $selectedMunicipality === $municipality ? 'selected' : '' ?>><?= e($municipality) ?></option><?php endforeach; ?></select></label><label class="field"><span class="label">Pocket/hora — dias úteis</span><input class="input" name="hourly_rate" id="hourly_rate" type="number" step="0.01" min="0" max="10000" value="5" required></label></div></fieldset>
            <fieldset><legend>Horários e período</legend><div class="form-grid"><label class="field"><span class="label">Entrada / data inicial</span><input class="input" name="starts_at" id="starts_at" type="datetime-local" required></label><label class="field"><span class="label">Saída</span><input class="input" name="ends_at" id="ends_at" type="datetime-local" required></label></div></fieldset>
            <label class="field"><span class="label">Pocket/hora — fins de semana e feriados</span><input class="input" name="hourly_rate_special" type="number" min="0" max="10000" step="0.01"><span class="subtitle">Em branco: aplica-se o valor dos dias úteis.</span></label>
            <label class="field" data-extra-times-control><span class="label">Agendar outra hora para o mesmo dia?</span><select class="input" name="extra_times"><option value="0">Não</option><option value="1">Sim</option></select></label>
            <div data-extra-times hidden><?php for($slot=2;$slot<=3;$slot++): ?><div class="form-grid"><label class="field"><span class="label">Entrada <?= $slot ?></span><input class="input" type="time" name="extra_start_<?= $slot ?>" disabled></label><label class="field"><span class="label">Saída <?= $slot ?></span><input class="input" type="time" name="extra_end_<?= $slot ?>" disabled></label></div><?php endfor; ?></div>
            <label class="field" data-series-control hidden><span class="label">Alterar também as horas nos outros dias agendados desta série?</span><select class="input" name="edit_series"><option value="0">Não, apenas este serviço</option><option value="1">Sim, pré-visualizar os outros dias</option></select></label>
            <div data-recurrence-slot></div><label class="field"><span class="label">Observações</span><textarea class="input textarea" name="observations" id="service_observations" rows="3" maxlength="10000"></textarea></label></div>
            <aside class="service-editor-preview"><h3>Pré-visualização</h3><dl class="preview-context"><dt>Cliente</dt><dd data-preview-patient>Por selecionar</dd><dt>Cuidadora</dt><dd data-preview-carer>Por selecionar</dd><dt>Concelho</dt><dd data-preview-municipality></dd></dl><div class="pocket-preview"><span>Pocket por serviço</span><strong id="service-pocket-preview">0,00 €</strong></div><div data-preview-slot></div><div data-preview-actions></div></aside>
            <div class="modal-actions editor-footer"><button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button><button class="btn btn-secondary" type="button" id="clear-service">Limpar</button><button class="btn btn-primary" type="submit" id="service-submit">Associar</button></div>
        </form>
        <div class="service-danger-actions" id="service-actions" hidden><div class="btn-row"><form method="post"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="cancel_service"><input type="hidden" name="id" class="selected-service-id"><button class="btn btn-secondary">Cancelar escala</button></form><form method="post" onsubmit="return confirm('Preservar esta escala no histórico e cancelar o serviço?')"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="delete_service"><input type="hidden" name="id" class="selected-service-id"><button class="btn btn-danger">Apagar</button></form></div></div>
    </section></div>
    <?php require __DIR__.'/copy-month.php'; ?>
    <script src="assets/scales-workspace.js?v=20261104" defer></script><script src="assets/scales-v2.js?v=20261104" defer></script><script src="assets/planning-additions.js?v=20261104" defer></script>
<?php });
