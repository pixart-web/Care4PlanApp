<?php
require_role(['ADMIN', 'EDITOR']);
$pdo = db();
$carersCount = (int) $pdo->query('SELECT COUNT(*) FROM carers')->fetchColumn();
$patientsCount = (int) $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
$todayServices = (int) $pdo->query("SELECT COUNT(*) FROM services WHERE status != 'CANCELLED' AND starts_at BETWEEN date('now', 'localtime') || ' 00:00:00' AND date('now', 'localtime') || ' 23:59:59'")->fetchColumn();
$newReports = $pdo->query("SELECT reports.id, reports.title, reports.created_at, carers.name AS carer_name FROM reports LEFT JOIN carers ON carers.id = reports.carer_id WHERE reports.sender_role = 'CARER' AND reports.read_by_admin_at IS NULL ORDER BY reports.created_at DESC")->fetchAll();

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

layout('Dashboard - Care4All', function () use ($carersCount, $patientsCount, $todayServices, $newReports, $newCarers, $leftCarers, $newPatients, $leftPatients) { ?>
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p class="muted">Resumo operacional do apoio domiciliário.</p>
        </div>
    </div>
    <?php if (! empty($newReports)): ?>
        <section class="notification-panel">
            <?php foreach ($newReports as $report): ?>
                <div class="alert alert-report"><strong>Novo relatório</strong><span>Recebido de <?= e($report['carer_name'] ?? 'cuidadora') ?>: <?= e($report['title']) ?></span><a class="btn btn-secondary" href="index.php?page=reports">Ver</a></div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <section class="grid stats">
        <div class="card">
            <p class="muted">Cuidadoras</p>
            <div class="stat-num"><?= $carersCount ?></div>
            <p class="metric-delta"><span class="delta-in">+<?= $newCarers ?> novas</span> · <span class="delta-out">-<?= $leftCarers ?> saíram</span> este mês</p>
        </div>
        <div class="card">
            <p class="muted">Utentes</p>
            <div class="stat-num"><?= $patientsCount ?></div>
            <p class="metric-delta"><span class="delta-in">+<?= $newPatients ?> novos</span> · <span class="delta-out">-<?= $leftPatients ?> saíram</span> este mês</p>
        </div>
        <div class="card"><p class="muted">Serviços hoje</p><div class="stat-num"><?= $todayServices ?></div></div>
    </section>
<?php });