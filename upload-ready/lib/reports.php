<?php
declare(strict_types=1);

/**
 * Printable report input: POST from the reports page (keeps descriptions out of URLs/logs) or GET.
 * Returns [from, to (exclusive), title, label, period, description] or exits with 400.
 */
function report_period_input(): array
{
    $input = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? $_POST : $_GET;
    $period = $input['period'] ?? 'monthly';
    $date = $input['date'] ?? '';
    $legacyMonth = $input['month'] ?? '';
    if (is_string($legacyMonth) && $legacyMonth !== '' && $date === '') {
        if (!preg_match('/^\d{4}-(0[1-9]|1[012])$/D', $legacyMonth)) { http_response_code(400); exit('Mês inválido.'); }
        $period = 'monthly';
        $date = $legacyMonth . '-01';
    }
    if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) { http_response_code(400); exit('Tipo de relatório inválido.'); }
    $reference = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
    if (!$reference || $reference->format('Y-m-d') !== $date) { http_response_code(400); exit('Data inválida.'); }
    $description = trim(is_string($input['description'] ?? null) ? $input['description'] : '');
    if (strlen($description) > 15000) { http_response_code(400); exit('A descrição excede o tamanho permitido.'); }

    if ($period === 'daily') {
        $from = $reference;
        $to = $from->modify('+1 day');
        $title = 'Relatório diário';
        $label = $from->format('d/m/Y');
    } elseif ($period === 'weekly') {
        $from = $reference->modify('-' . ((int) $reference->format('N') - 1) . ' days');
        $to = $from->modify('+7 days');
        $title = 'Relatório semanal';
        $label = $from->format('d/m/Y') . ' a ' . $to->modify('-1 day')->format('d/m/Y');
    } else {
        $from = $reference->modify('first day of this month');
        $to = $from->modify('+1 month');
        $title = 'Relatório mensal';
        $label = $from->format('m/Y');
    }
    return ['from' => $from, 'to' => $to, 'title' => $title, 'label' => $label, 'period' => $period, 'description' => $description, 'input' => $input];
}

/** Period fields shared by the printable report forms. */
function report_period_fields(): void
{ ?>
    <label class="field">Tipo de relatório<select class="input" name="period" required><option value="daily">Diário</option><option value="weekly">Semanal</option><option value="monthly" selected>Mensal</option></select></label>
    <label class="field">Data de referência<input class="input" type="date" name="date" value="<?= date('Y-m-d') ?>" required></label>
    <p class="muted">Diário: o dia escolhido. Semanal: de segunda a domingo dessa semana. Mensal: o mês dessa data.</p>
    <label class="field">Descrição<textarea class="input textarea" name="description" rows="4" maxlength="5000" placeholder="Observações que aparecem no relatório (opcional)"></textarea></label>
<?php }
