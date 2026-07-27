<?php
$user = require_role(['CARER']);
$pdo = db();
$carerId = (int) $user['carer_id'];
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE carer_id = ? AND status != 'CANCELLED' AND starts_at BETWEEN ? AND ?");
$stmt->execute([$carerId, $todayStart, $todayEnd]);
$todayServices = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM((julianday(ends_at) - julianday(starts_at)) * 24), 0) FROM services WHERE carer_id = ? AND status != 'CANCELLED' AND starts_at BETWEEN ? AND ?");
$stmt->execute([$carerId, $todayStart, $todayEnd]);
$todayHours = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT id, title, created_at FROM reports WHERE carer_id = ? AND sender_role != 'CARER' AND read_by_carer_at IS NULL ORDER BY created_at DESC");
$stmt->execute([$carerId]);
$newReports = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT reports.id, reports.title, MAX(report_replies.created_at) AS replied_at
    FROM reports
    JOIN report_replies ON report_replies.report_id = reports.id
    WHERE reports.carer_id = ?
        AND reports.sender_role = 'CARER'
        AND report_replies.sender_role != 'CARER'
        AND report_replies.read_by_carer_at IS NULL
    GROUP BY reports.id, reports.title
    ORDER BY replied_at DESC");
$stmt->execute([$carerId]);
$answeredReports = $stmt->fetchAll();
$monthPocket = pocket_for_carer($carerId);

layout('Dashboard - Care4All', function () use ($todayServices, $todayHours, $monthPocket, $newReports, $answeredReports) { ?>
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p class="muted">Resumo do teu dia e do pocket mensal.</p>
        </div>
    </div>
    <?php if (! empty($newReports) || ! empty($answeredReports)): ?>
        <section class="notification-panel">
            <?php foreach ($newReports as $report): ?>
                <div class="alert alert-report"><strong>Novo relatório</strong><span><?= e($report['title']) ?></span><a class="btn btn-secondary" href="index.php?page=reports">Ver</a></div>
            <?php endforeach; ?>
            <?php foreach ($answeredReports as $report): ?>
                <div class="alert alert-report"><strong>Relatório respondido</strong><span>O relatório "<?= e($report['title']) ?>" foi respondido.</span><a class="btn btn-secondary" href="index.php?page=reports">Ver</a></div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <section class="grid stats">
        <div class="card"><p class="muted">Serviços hoje</p><div class="stat-num"><?= $todayServices ?></div></div>
        <div class="card"><p class="muted">Horas hoje</p><div class="stat-num"><?= number_format($todayHours, 1, ',', ' ') ?></div></div>
        <div class="card"><p class="muted">Pocket mensal</p><div class="stat-num"><?= money($monthPocket) ?></div></div>
    </section>
<?php });