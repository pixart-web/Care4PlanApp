<?php
require __DIR__.'/calendar-matrix.php';
?><!doctype html><html lang="pt"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Care4All · Vista total</title>
<link rel="stylesheet" href="assets/print-sheet.css"><script defer src="assets/print-sheet.js"></script>
<main><h1>Escalas · <?= e($periodLabel) ?></h1><p><?= e($selectedMunicipality === 'all' ? 'Todos os concelhos' : $selectedMunicipality) ?></p>
<nav><?php foreach ($periodOptions as $key=>$label): ?><a class="btn" href="?<?= e(http_build_query(array_replace($_GET,['period'=>$key]))) ?>"><?= e($label) ?></a> <?php endforeach; ?></nav>
<button type="button" data-print>Imprimir / Guardar PDF</button>
<?php foreach (array_chunk($calendarDays,7) as $days): ?><section class="week-block"><table><thead><tr><th><?= $rowByCarer?'Cuidadora':'Cliente' ?></th>
<?php foreach ($days as $date): ?><th><?= date('d/m',strtotime($date)) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($matrixPatients as $id=>$name): ?><tr><th><?= e($name) ?></th><?php foreach ($days as $date): ?><td>
<?php foreach ($matrixServices[$id][$date] ?? [] as $service): ?>
<div><?= substr(max($service['starts_at'],$date.' 00:00:00'),11,5) ?>–<?= substr($service['ends_at'],0,10)>$date?'24:00':substr($service['ends_at'],11,5) ?> · <?= e($rowByCarer?$service['patient_name']:$service['carer_name']) ?></div>
<?php endforeach; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></section><?php endforeach; ?></main></html>
