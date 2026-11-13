<?php
$user = require_role(['ADMIN']);
$pdo = db();
$monthStart = date('Y-m-01 00:00:00');
$monthEnd = date('Y-m-t 23:59:59');

$rows=$pdo->query('SELECT id,name,COALESCE(client_hourly_rate,0) AS client_hourly_rate,client_special_rate FROM patients ORDER BY name')->fetchAll();
$stmt=$pdo->prepare("SELECT * FROM services WHERE status!='CANCELLED' AND starts_at BETWEEN ? AND ?");
$stmt->execute([$monthStart,$monthEnd]); $services=$stmt->fetchAll(); $byPatient=[];
foreach ($services as $service) $byPatient[$service['patient_id']][]=$service;
foreach ($rows as &$row) {
    $row['hours']=0.0; $row['income']=0.0; $row['cost']=0.0;
    foreach ($byPatient[$row['id']]??[] as $service) {
        $row['hours']+=max(0,(strtotime($service['ends_at'])-strtotime($service['starts_at']))/3600);
        $row['income']+=client_service_amount($service,$row);
        $row['cost']+=service_rate_amount($service);
    }
}
unset($row);

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
            <p class="muted">Receitas, custos e margem por cliente no mês atual.</p>
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
                <h2>Margem por cliente</h2>
                <p class="muted">Compara o valor cobrado ao cliente com o valor pago à cuidadora.</p>
            </div>
        </div>
        <div class="table-wrap desktop-table">
            <table>
                <thead>
                <tr>
                    <th>Cliente</th>
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