<?php
$report = report_period_input();
['from' => $from, 'to' => $to, 'title' => $periodTitle, 'label' => $periodLabel, 'description' => $description] = $report;

$clientQuery = db()->prepare('SELECT id,name,client_hourly_rate,client_special_rate FROM patients WHERE id=?');
$clientQuery->execute([(int)($report['input']['client'] ?? 0)]);
$client = $clientQuery->fetch();
if (!$client) { http_response_code(404); exit('Cliente não encontrado.'); }
$query = db()->prepare("SELECT s.*, c.name AS carer FROM services s JOIN carers c ON c.id=s.carer_id
    WHERE s.patient_id=? AND (s.status='DONE' OR (s.status='SCHEDULED' AND s.ends_at<=?)) AND s.starts_at>=? AND s.starts_at<? ORDER BY s.starts_at,c.name");
// Carers often skip "Concluir": past scheduled services count as provided; future ones are left out.
$query->execute([$client['id'], now(), $from->format('Y-m-d'), $to->format('Y-m-d')]);
$rows = $query->fetchAll();
$total = 0.0;
$hours = 0.0;
?><!doctype html><html lang="pt"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($periodTitle) ?> · <?= e($client['name']) ?></title>
<link rel="stylesheet" href="assets/print-sheet.css"><script defer src="assets/print-sheet.js"></script>
<main><h1>Care4All · Serviços prestados</h1><h2><?= e($client['name']) ?> · <?= e($periodTitle) ?> · <?= e($periodLabel) ?></h2>
<?php if ($description !== ''): ?><section class="statement-description"><h3>Descrição</h3><p><?= nl2br(e($description)) ?></p></section><?php endif; ?>
<?php if (empty($rows)): ?><p>Sem serviços prestados neste período.</p><?php else: ?>
<table><thead><tr><th>Data e horário</th><th>Cuidadora</th><th>Estado</th><th>Horas</th><th>Valor</th></tr></thead><tbody>
<?php foreach ($rows as $row): $amount=client_service_amount($row,$client); $total+=$amount; $rowHours=(strtotime($row['ends_at'])-strtotime($row['starts_at']))/3600; $hours+=$rowHours; ?>
<tr><td><?= date('d/m H:i',strtotime($row['starts_at'])) ?> – <?= date('d/m H:i',strtotime($row['ends_at'])) ?></td><td><?= e($row['carer']) ?></td><td><?= $row['status'] === 'DONE' ? 'Concluído' : 'Realizado (sem confirmação)' ?></td><td><?= number_format($rowHours,2,',',' ') ?></td><td><?= money($amount) ?></td></tr>
<?php endforeach; ?></tbody></table><?php endif; ?>
<p><strong>Total de horas: <?= number_format($hours,2,',',' ') ?> · Total dos serviços prestados: <?= money($total) ?></strong></p><button type="button" data-print>Imprimir / Guardar PDF</button></main></html>
