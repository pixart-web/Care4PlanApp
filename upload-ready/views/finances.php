<?php
$user = require_role(['ADMIN']);
$pdo = db();
$monthStart = date('Y-m-01 00:00:00');
$monthEnd = date('Y-m-t 23:59:59');

$stmt = $pdo->prepare("SELECT patients.id, patients.name, COALESCE(patients.client_hourly_rate, 0) AS client_hourly_rate,
        COALESCE(SUM(CASE WHEN services.id IS NOT NULL THEN ((julianday(services.ends_at) - julianday(services.starts_at)) * 24) ELSE 0 END), 0) AS hours,
        COALESCE(SUM(CASE WHEN services.id IS NOT NULL THEN COALESCE(patients.client_hourly_rate, 0) * ((julianday(services.ends_at) - julianday(services.starts_at)) * 24) ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN services.id IS NOT NULL THEN services.hourly_rate * ((julianday(services.ends_at) - julianday(services.starts_at)) * 24) ELSE 0 END), 0) AS cost
    FROM patients
    LEFT JOIN services ON services.patient_id = patients.id
        AND services.status != 'CANCELLED'
        AND services.starts_at BETWEEN ? AND ?
    GROUP BY patients.id
    ORDER BY patients.name ASC");
$stmt->execute([$monthStart, $monthEnd]);
$rows = $stmt->fetchAll();

$totalIncome = 0.0;
$totalCost = 0.0;
foreach ($rows as &$row) {
    $row['income'] = round((float) $row['income'], 2);
    $row['cost'] = round((float) $row['cost'], 2);
    $row['hours'] = round((float) $row['hours'], 2);
    $row['margin'] = round($row['income'] - $row['cost'], 2);
    $row['margin_percent'] = $row['income'] > 0 ? round(($row['margin'] / $row['income']) * 100, 1) : ($row['cost'] > 0 ? -100.0 : 0.0);
    $row['profit_class'] = $row['margin'] > 0 ? 'profit-positive' : ($row['margin'] < 0 ? 'profit-negative' : 'profit-neutral');
    $totalIncome += $row['income'];
    $totalCost += $row['cost'];
}
unset($row);

$totalMargin = round($totalIncome - $totalCost, 2);
$totalMarginPercent = $totalIncome > 0 ? round(($totalMargin / $totalIncome) * 100, 1) : ($totalCost > 0 ? -100.0 : 0.0);
$totalClass = $totalMargin > 0 ? 'profit-positive' : ($totalMargin < 0 ? 'profit-negative' : 'profit-neutral');
$periodLabel = date('01/m/Y') . ' a ' . date('t/m/Y');

layout('Finanças - Care4All', function () use ($rows, $totalIncome, $totalCost, $totalMargin, $totalMarginPercent, $totalClass, $periodLabel) { ?>
    <div class="page-head">
        <div>
            <h1>Finanças</h1>
            <p class="muted">Receitas, custos e margem por utente no mês atual.</p>
        </div>
    </div>

    <section class="grid stats finance-stats">
        <div class="card"><p class="muted">Receita</p><div class="stat-num"><?= money($totalIncome) ?></div><p class="metric-delta"><?= e($periodLabel) ?></p></div>
        <div class="card"><p class="muted">Custo cuidadoras</p><div class="stat-num"><?= money($totalCost) ?></div><p class="metric-delta">Pocket planeado no mês</p></div>
        <div class="card <?= e($totalClass) ?>"><p class="muted">Margem total</p><div class="stat-num"><?= money($totalMargin) ?></div><p class="metric-delta"><?= number_format($totalMarginPercent, 1, ',', ' ') ?>%</p></div>
    </section>

    <section class="card list-panel finance-panel">
        <div class="list-panel-head">
            <div>
                <h2>Margem por utente</h2>
                <p class="muted">Compara o valor cobrado ao cliente com o valor pago à cuidadora.</p>
            </div>
        </div>
        <div class="table-wrap desktop-table">
            <table>
                <thead>
                <tr>
                    <th>Utente</th>
                    <th>Horas</th>
                    <th>Valor cliente</th>
                    <th>Valor cuidadora</th>
                    <th>Margem</th>
                    <th>Margem %</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="<?= e($row['profit_class']) ?>">
                        <td><strong><?= e($row['name']) ?></strong></td>
                        <td><?= number_format((float) $row['hours'], 1, ',', ' ') ?> h</td>
                        <td><?= money($row['income']) ?></td>
                        <td><?= money($row['cost']) ?></td>
                        <td><strong><?= money($row['margin']) ?></strong></td>
                        <td><strong><?= number_format((float) $row['margin_percent'], 1, ',', ' ') ?>%</strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mobile-cards">
            <?php foreach ($rows as $row): ?>
                <article class="mobile-item <?= e($row['profit_class']) ?>">
                    <strong><?= e($row['name']) ?></strong>
                    <div class="mobile-line"><span>Horas</span><strong><?= number_format((float) $row['hours'], 1, ',', ' ') ?> h</strong></div>
                    <div class="mobile-line"><span>Valor cliente</span><strong><?= money($row['income']) ?></strong></div>
                    <div class="mobile-line"><span>Valor cuidadora</span><strong><?= money($row['cost']) ?></strong></div>
                    <div class="mobile-line"><span>Margem</span><strong><?= money($row['margin']) ?></strong></div>
                    <div class="mobile-line"><span>Margem %</span><strong><?= number_format((float) $row['margin_percent'], 1, ',', ' ') ?>%</strong></div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php });