<?php
// Carer earnings report: admin chooses the carer, a carer only ever gets her own.
$user = require_auth();
$report = report_period_input();
['from' => $from, 'to' => $to, 'title' => $periodTitle, 'label' => $periodLabel, 'description' => $description] = $report;
if ($user['role'] === 'CARER') {
    $carerId = (int) $user['carer_id'];
} else {
    require_role(['ADMIN']);
    $carerId = (int) ($report['input']['carer'] ?? 0);
}
$carerQuery = db()->prepare('SELECT id,name FROM carers WHERE id=?');
$carerQuery->execute([$carerId]);
$carer = $carerQuery->fetch();
if (!$carer) { http_response_code(404); exit('Cuidadora não encontrada.'); }

// Same rule as the client report and Pocket: concluded, or scheduled and already over.
$query = db()->prepare("SELECT s.*, p.name AS patient_name FROM services s JOIN patients p ON p.id=s.patient_id
    WHERE s.carer_id=? AND (s.status='DONE' OR (s.status='SCHEDULED' AND s.ends_at<=?)) AND s.starts_at>=? AND s.starts_at<?
    ORDER BY p.name, s.starts_at");
$query->execute([$carerId, now(), $from->format('Y-m-d'), $to->format('Y-m-d')]);
$byClient = [];
foreach ($query->fetchAll() as $row) {
    $hours = (strtotime($row['ends_at']) - strtotime($row['starts_at'])) / 3600;
    $amount = service_pocket_value($row);
    $group = &$byClient[(int) $row['patient_id']];
    $group['name'] = $row['patient_name'];
    $group['rows'][] = $row + ['_hours' => $hours, '_amount' => $amount];
    $group['hours'] = ($group['hours'] ?? 0) + $hours;
    $group['amount'] = ($group['amount'] ?? 0) + $amount;
    unset($group);
}
$totalHours = array_sum(array_column($byClient, 'hours'));
$totalAmount = array_sum(array_column($byClient, 'amount'));
?><!doctype html><html lang="pt"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($periodTitle) ?> · <?= e($carer['name']) ?></title>
<link rel="stylesheet" href="assets/print-sheet.css"><script defer src="assets/print-sheet.js"></script>
<main><h1>Care4All · Relatório da cuidadora</h1><h2><?= e($carer['name']) ?> · <?= e($periodTitle) ?> · <?= e($periodLabel) ?></h2>
<?php if ($description !== ''): ?><section class="statement-description"><h3>Descrição</h3><p><?= nl2br(e($description)) ?></p></section><?php endif; ?>
<?php if (!$byClient): ?><p>Sem serviços prestados neste período.</p><?php else: ?>
<h3>Resumo por cliente</h3>
<table><thead><tr><th>Cliente</th><th>Serviços</th><th>Horas</th><th>Valor a receber</th></tr></thead><tbody>
<?php foreach ($byClient as $group): ?><tr><td><?= e($group['name']) ?></td><td><?= count($group['rows']) ?></td><td><?= number_format($group['hours'],2,',',' ') ?></td><td><?= money($group['amount']) ?></td></tr><?php endforeach; ?>
</tbody><tfoot><tr><th>Total</th><th><?= array_sum(array_map(fn($group) => count($group['rows']), $byClient)) ?></th><th><?= number_format($totalHours,2,',',' ') ?></th><th><?= money($totalAmount) ?></th></tr></tfoot></table>
<h3>Detalhe dos serviços</h3>
<table><thead><tr><th>Cliente</th><th>Data e horário</th><th>Estado</th><th>Valor/hora (útil / especial)</th><th>Horas</th><th>Valor</th></tr></thead><tbody>
<?php foreach ($byClient as $group): foreach ($group['rows'] as $row): ?>
<tr><td><?= e($group['name']) ?></td><td><?= date('d/m H:i',strtotime($row['starts_at'])) ?> – <?= date('d/m H:i',strtotime($row['ends_at'])) ?></td><td><?= $row['status'] === 'DONE' ? 'Concluído' : 'Realizado (sem confirmação)' ?></td><td><?= service_rate_label($row) ?></td><td><?= number_format($row['_hours'],2,',',' ') ?></td><td><?= money($row['_amount']) ?></td></tr>
<?php endforeach; endforeach; ?></tbody></table><?php endif; ?>
<p><strong>Total de horas: <?= number_format($totalHours,2,',',' ') ?> · Total a receber: <?= money($totalAmount) ?></strong></p>
<p class="muted">Valores calculados pelas tarifas de cada serviço. Não inclui ajustes manuais do Pocket.</p>
<button type="button" data-print>Imprimir / Guardar PDF</button></main></html>
