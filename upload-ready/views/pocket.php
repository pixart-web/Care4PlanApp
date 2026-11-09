<?php
$user = require_role(['CARER']);
$pdo = db();
$total = pocket_for_carer((int) $user['carer_id']);
$stmt = $pdo->prepare("SELECT services.*, patients.name AS patient_name FROM services JOIN patients ON patients.id = services.patient_id WHERE services.carer_id = ? AND services.status != 'CANCELLED' AND services.starts_at BETWEEN ? AND ? AND services.ends_at <= ? ORDER BY services.starts_at DESC");
$stmt->execute([$user['carer_id'], date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59'), date('Y-m-d H:i:s', strtotime('-15 minutes'))]);
$services = $stmt->fetchAll();

layout('Pocket - Care4All', function () use ($total, $services) { ?>
    <div class="page-head"><div><h1>Pocket</h1><p class="muted">Total calculado automaticamente para o mês atual.</p></div></div>
    <div class="card" style="margin-bottom:16px"><p class="muted">Pocket do mês</p><div class="stat-num" style="color:var(--green)"><?= money($total) ?></div></div>
    <div class="card">
        <div class="table-wrap desktop-table"><table><thead><tr><th>Data</th><th>Cliente</th><th>Horário</th><th>Valor/hora (útil / especial)</th><th>Valor</th></tr></thead><tbody><?php foreach ($services as $s): ?><tr><td><?= date('d/m/Y', strtotime($s['starts_at'])) ?></td><td><?= e($s['patient_name']) ?></td><td><?= date('H:i', strtotime($s['starts_at'])) ?> - <?= date('H:i', strtotime($s['ends_at'])) ?></td><td><?= service_rate_label($s) ?></td><td><?= money(service_pocket_value($s)) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <div class="mobile-cards"><?php foreach ($services as $s): ?><article class="mobile-item"><strong><?= e($s['patient_name']) ?></strong><div class="mobile-line"><span>Data</span><span><?= date('d/m/Y', strtotime($s['starts_at'])) ?></span></div><div class="mobile-line"><span>Horário</span><span><?= date('H:i', strtotime($s['starts_at'])) ?> - <?= date('H:i', strtotime($s['ends_at'])) ?></span></div><div class="mobile-line"><span>Valor</span><span><?= money(service_pocket_value($s)) ?></span></div></article><?php endforeach; ?></div>
    </div>
<?php });