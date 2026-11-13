<?php
$month = $_GET['month'] ?? '';
if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[012])$/D', $month)) { http_response_code(400); exit('Mês inválido.'); }
$clientQuery = db()->prepare('SELECT id,name,client_hourly_rate,client_special_rate FROM patients WHERE id=?');
$clientQuery->execute([(int)($_GET['client'] ?? 0)]);
$client = $clientQuery->fetch();
if (!$client) { http_response_code(404); exit('Cliente não encontrado.'); }
$query = db()->prepare("SELECT s.*, c.name AS carer FROM services s JOIN carers c ON c.id=s.carer_id
    WHERE s.patient_id=? AND s.status='DONE' AND s.starts_at>=? AND s.starts_at<? ORDER BY s.starts_at,c.name");
$first = new DateTimeImmutable($month.'-01');
$query->execute([$client['id'], $first->format('Y-m-d'), $first->modify('+1 month')->format('Y-m-d')]);
$total = 0.0;
?><!doctype html><html lang="pt"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Relatório de serviços prestados</title>
<link rel="stylesheet" href="assets/print-sheet.css"><script defer src="assets/print-sheet.js"></script>
<main><h1>Care4All · Serviços prestados</h1><h2><?= e($client['name']) ?> · <?= $first->format('m/Y') ?></h2>
<table><thead><tr><th>Data e horário</th><th>Cuidadora</th><th>Horas</th><th>Valor</th></tr></thead><tbody>
<?php foreach ($query as $row): $amount=client_service_amount($row,$client); $total+=$amount; ?>
<tr><td><?= date('d/m H:i',strtotime($row['starts_at'])) ?> – <?= date('d/m H:i',strtotime($row['ends_at'])) ?></td><td><?= e($row['carer']) ?></td><td><?= number_format((strtotime($row['ends_at'])-strtotime($row['starts_at']))/3600,2,',',' ') ?></td><td><?= money($amount) ?></td></tr>
<?php endforeach; ?></tbody></table><p><strong>Total dos serviços concluídos: <?= money($total) ?></strong></p><button type="button" data-print>Imprimir / Guardar PDF</button></main></html>
