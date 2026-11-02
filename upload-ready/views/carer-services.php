<?php
$user = require_role(['CARER']);
$zone = new DateTimeZone(APP_TIMEZONE);
$today = new DateTimeImmutable('today', $zone);
$rawDate = is_string($_GET['scale_date'] ?? null) ? $_GET['scale_date'] : '';
$day = DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate, $zone);
if (! $day || $day->format('Y-m-d') !== $rawDate) {
    $day = $today;
}
$view = in_array($_GET['view'] ?? '', ['list', 'calendar'], true) ? $_GET['view'] : '';
$stmt = db()->prepare('SELECT services.id, services.starts_at, services.ends_at, services.status,
    services.municipality, services.observations, services.hourly_rate, patients.name AS patient_name, patients.address
    FROM services JOIN patients ON patients.id = services.patient_id
    WHERE services.carer_id = ? AND services.starts_at < ? AND services.ends_at > ?
    ORDER BY services.starts_at, services.ends_at, services.id');
$stmt->execute([(int) $user['carer_id'], $day->modify('+1 day')->format('Y-m-d 00:00:00'), $day->format('Y-m-d 00:00:00')]);
$services = $stmt->fetchAll();
$statusLabels = ['SCHEDULED' => 'Agendado', 'DONE' => 'Concluído', 'CANCELLED' => 'Cancelado'];
$dayUrl = static function (DateTimeImmutable $date) use ($view): string {
    return 'index.php?' . http_build_query(['page' => 'scales', 'scale_date' => $date->format('Y-m-d')] + ($view ? ['view' => $view] : []));
};
layout('Os meus serviços - Care4All', function () use ($day, $today, $view, $services, $statusLabels, $dayUrl) { ?>
    <link rel="stylesheet" href="assets/atelier-scales.css">
    <section class="care-agenda" data-carer-agenda data-date="<?= e($day->format('Y-m-d')) ?>" data-view="<?= e($view) ?>">
        <header class="page-head">
            <div><p class="eyebrow">A minha agenda</p><h1>Os meus servi&ccedil;os</h1></div>
            <div class="agenda-view-switch" role="group" aria-label="Vista da agenda">
                <button type="button" class="btn btn-secondary" data-agenda-view="list" aria-pressed="true" aria-controls="carer-service-list">Lista</button>
                <button type="button" class="btn btn-secondary" data-agenda-view="calendar" aria-pressed="false" aria-controls="carer-calendar-panel" hidden>Calend&aacute;rio</button>
            </div>
        </header>
        <nav class="agenda-date-nav" aria-label="Dia dos servi&ccedil;os">
            <a class="btn btn-secondary agenda-day-link" href="<?= e($dayUrl($day->modify('-1 day'))) ?>" aria-label="Dia anterior" title="Dia anterior"><i data-lucide="chevron-left" aria-hidden="true"></i></a>
            <form method="get" class="agenda-date-form">
                <input type="hidden" name="page" value="scales">
                <input type="hidden" name="view" value="<?= e($view ?: 'list') ?>" data-agenda-view-input>
                <label class="field"><span class="label">Data</span><input class="input" type="date" name="scale_date" value="<?= e($day->format('Y-m-d')) ?>" required></label>
                <button class="btn btn-secondary" type="submit">Ver dia</button>
            </form>
            <a class="btn btn-secondary agenda-day-link" href="<?= e($dayUrl($day->modify('+1 day'))) ?>" aria-label="Dia seguinte" title="Dia seguinte"><i data-lucide="chevron-right" aria-hidden="true"></i></a>
            <a class="btn btn-secondary agenda-day-link" href="<?= e($dayUrl($today)) ?>">Hoje</a>
        </nav>
        <section id="carer-service-list" aria-labelledby="carer-day-title">
            <header class="section-head"><h2 id="carer-day-title"><?= $day == $today ? 'Hoje' : e($day->format('d/m/Y')) ?></h2><span class="muted"><?= count($services) ?> servi&ccedil;o<?= count($services) === 1 ? '' : 's' ?></span></header>
            <div class="service-list">
                <?php foreach ($services as $service):
                    $id = (int) $service['id'];
                    $status = $service['status'];
                    $start = new DateTimeImmutable($service['starts_at']);
                    $end = new DateTimeImmutable($service['ends_at']); ?>
                    <article class="service-row service-mobile-card status-<?= e(strtolower($status)) ?>" aria-labelledby="service-patient-<?= $id ?>">
                        <div class="service-row-time"><time datetime="<?= e($start->format('Y-m-d\TH:i:s')) ?>"><?= e($start->format('H:i')) ?></time><span aria-hidden="true"> &ndash; </span><time datetime="<?= e($end->format('Y-m-d\TH:i:s')) ?>"><?= e($end->format('H:i')) ?></time><?php if ($start->format('Y-m-d') !== $end->format('Y-m-d')): ?><small><?= e($start->format('d/m') . ' - ' . $end->format('d/m')) ?></small><?php endif; ?></div>
                        <div class="service-row-body"><h3 id="service-patient-<?= $id ?>"><?= e($service['patient_name']) ?></h3><p class="muted"><?= e(trim((string) $service['municipality']) ?: 'Sem concelho') ?></p>
                            <details class="service-details"><summary>Detalhes<?= trim((string) $service['observations']) !== '' ? ' e observações' : '' ?></summary><dl><dt>Morada</dt><dd><?= e($service['address'] ?: 'Não indicada') ?></dd><dt>Pocket</dt><dd><?= e(money(service_pocket_value($service))) ?></dd><dt>Observa&ccedil;&otilde;es</dt><dd class="service-observations"><?= nl2br(e($service['observations'] ?: 'Sem observações.')) ?></dd></dl></details>
                        </div>
                        <div class="service-row-actions"><span class="service-state status-<?= e(strtolower($status)) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
                            <?php if (in_array($status, ['SCHEDULED', 'DONE'], true)): ?>
                                <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $status === 'DONE' ? 'undo_service' : 'done_service' ?>"><input type="hidden" name="id" value="<?= $id ?>"><button type="submit" class="btn <?= $status === 'DONE' ? 'btn-secondary' : 'btn-primary' ?>" aria-label="<?= e(($status === 'DONE' ? 'Desfazer conclusão: ' : 'Concluir serviço: ') . $service['patient_name'] . ', ' . $start->format('H:i')) ?>"><?= $status === 'DONE' ? 'Desfazer' : 'Concluir' ?></button></form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (! $services): ?><div class="empty-state"><h3>Sem servi&ccedil;os neste dia</h3><p class="muted"><?= e($day->format('d/m/Y')) ?></p></div><?php endif; ?>
            </div>
        </section>
        <section id="carer-calendar-panel" aria-label="Calend&aacute;rio dos meus servi&ccedil;os" hidden><p class="alert alert-error" role="alert" id="carer-calendar-error" hidden>N&atilde;o foi poss&iacute;vel carregar o calend&aacute;rio. <button class="btn btn-secondary" type="button" id="carer-calendar-retry">Tentar novamente</button></p><div id="carer-calendar"></div></section>
        <div class="modal-backdrop" id="carer-selected" hidden><section class="modal-card modal-card-sm" role="dialog" aria-modal="true" aria-labelledby="selected-title"><header class="modal-head"><h2 id="selected-title">Detalhes do servi&ccedil;o</h2><button class="modal-close" type="button" data-close-modal aria-label="Fechar">&times;</button></header><div class="modal-body"><p id="selected-address"></p><dl><dt>Hor&aacute;rio</dt><dd id="selected-time"></dd><dt>Concelho</dt><dd id="selected-municipality"></dd><dt>Estado</dt><dd id="selected-status"></dd><dt>Pocket</dt><dd id="selected-pocket"></dd><dt>Observa&ccedil;&otilde;es</dt><dd id="selected-observations" class="service-observations"></dd></dl><div class="btn-row modal-actions"><form method="post" id="carer-done-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="done_service"><input type="hidden" name="id" id="done_id"><button type="submit" class="btn btn-primary">Concluir</button></form><form method="post" id="carer-undo-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="undo_service"><input type="hidden" name="id" id="undo_id"><button type="submit" class="btn btn-secondary">Desfazer</button></form></div></div></section></div>
    </section>
    <script src="assets/vendor/fullcalendar-6.1.11.global.min.js" defer></script>
    <script src="assets/vendor/fullcalendar-pt-6.1.11.global.min.js" defer></script>
    <script src="assets/carer-services.js" defer></script>
<?php });
