<?php

declare(strict_types=1);

function scheduling_migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS service_batches (idempotency_key TEXT PRIMARY KEY, payload_hash TEXT NOT NULL, result_json TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_services_carer_time ON services (carer_id, starts_at, ends_at)');
}

function scheduling_municipalities(): array
{
    $configured = defined('CARE4ALL_MUNICIPALITIES') ? constant('CARE4ALL_MUNICIPALITIES') : null;
    return is_array($configured) && $configured ? array_values(array_filter($configured, fn ($value) => is_string($value) && trim($value) !== '')) : ['Portimão', 'Lagos', 'Lagoa', 'Albufeira', 'Silves'];
}

/** Resolve wall-clock input without PHP's silent DST normalization. */
function scheduling_local_time(string $value): DateTimeImmutable
{
    $value = str_replace('T', ' ', trim($value));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D', $value)) {
        $value .= ':00';
    }
    $utc = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    if (! $utc || $utc->format('Y-m-d H:i:s') !== $value || substr($value, 0, 4) < '1900' || substr($value, 0, 4) > '2100') {
        throw new InvalidArgumentException('Data ou hora inválida.');
    }
    $zone = new DateTimeZone('Europe/Lisbon');
    $matches = [];
    foreach ($zone->getTransitions($utc->getTimestamp() - 172800, $utc->getTimestamp() + 172800) as $transition) {
        $candidate = $utc->setTimestamp($utc->getTimestamp() - $transition['offset'])->setTimezone($zone);
        if ($candidate->format('Y-m-d H:i:s') === $value) {
            $matches[$candidate->getTimestamp()] = $candidate;
        }
    }
    if (count($matches) !== 1) {
        throw new InvalidArgumentException('Hora local inexistente ou ambígua em Europe/Lisbon: ' . $value);
    }
    return array_values($matches)[0];
}

function scheduling_date(string $value): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    if (! $date || $date->format('Y-m-d') !== $value || $value < '1900-01-01' || $value > '2100-12-31') {
        throw new InvalidArgumentException('Data inválida.');
    }
    return $date;
}

function scheduling_base_plan(PDO $pdo, array $data): array
{
    foreach (['patient_id', 'carer_id'] as $field) {
        if (! filter_var($data[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new InvalidArgumentException('Selecione cliente e cuidadora.');
        }
    }
    $rawId = $data['id'] ?? 0;
    $id = filter_var($rawId === '' ? 0 : $rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($id === false) {
        throw new InvalidArgumentException('Identificador inválido.');
    }
    $municipality = trim((string) ($data['municipality'] ?? ''));
    if (! in_array($municipality, scheduling_municipalities(), true)) {
        throw new InvalidArgumentException('Selecione o concelho.');
    }
    $rate = filter_var($data['hourly_rate'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($rate === false || ! is_finite($rate) || $rate < 0 || $rate > 10000) {
        throw new InvalidArgumentException('Introduza um valor Pocket válido entre 0 e 10000 EUR/hora.');
    }
    foreach (['patients' => 'patient_id', 'carers' => 'carer_id'] as $table => $field) {
        $stmt = $pdo->prepare("SELECT id FROM $table WHERE id = ?");
        $stmt->execute([(int) $data[$field]]);
        if (! $stmt->fetchColumn()) {
            throw new InvalidArgumentException('Cliente ou cuidadora inexistente.');
        }
    }
    if ($id) {
        $stmt = $pdo->prepare('SELECT id FROM services WHERE id = ?');
        $stmt->execute([$id]);
        if (! $stmt->fetchColumn()) {
            throw new InvalidArgumentException('Escala inexistente.');
        }
    }
    $start = scheduling_local_time((string) ($data['starts_at'] ?? ''));
    $end = scheduling_local_time((string) ($data['ends_at'] ?? ''));
    if ($end <= $start) {
        throw new InvalidArgumentException('A saída deve ser posterior à entrada.');
    }
    $mode = (string) ($data['recurrence_mode'] ?? 'none');
    if (! in_array($mode, ['none', 'dates', 'weekdays', 'week_only'], true) || ($id && $mode !== 'none')) {
        throw new InvalidArgumentException('Recorrência inválida; edite apenas uma ocorrência de cada vez.');
    }
    $baseDate = $start->format('Y-m-d');
    $dates = [$baseDate];
    if ($mode === 'dates') {
        $extra = $data['recurrence_dates'] ?? [];
        if (is_string($extra)) {
            $extra = preg_split('/[\s,;]+/', trim($extra), -1, PREG_SPLIT_NO_EMPTY);
        }
        if (! is_array($extra) || count($extra) > 120) {
            throw new InvalidArgumentException('Máximo de 120 ocorrências.');
        }
        foreach ($extra as $date) {
            $dates[] = scheduling_date((string) $date)->format('Y-m-d');
        }
    } elseif (in_array($mode, ['weekdays', 'week_only'], true)) {
        $first = scheduling_date($baseDate);
        $untilValue = $mode === 'week_only' ? '' : trim((string) ($data['recurrence_until'] ?? ''));
        $until = $untilValue !== '' ? scheduling_date($untilValue) : $first->modify('+' . (7 - (int) $first->format('N')) . ' days');
        if ($until < $first || $first->diff($until)->days > 3660) {
            throw new InvalidArgumentException('Intervalo de recorrência inválido (máximo de 10 anos).');
        }
        $weekdays = $data['recurrence_weekdays'] ?? [];
        if (! is_array($weekdays) || ! $weekdays || count($weekdays) > 7) {
            throw new InvalidArgumentException('Selecione dias da semana.');
        }
        foreach ($weekdays as $weekday) {
            if (! in_array((string) $weekday, ['1', '2', '3', '4', '5', '6', '7'], true)) {
                throw new InvalidArgumentException('Dia da semana inválido.');
            }
        }
        $dates = [];
        for ($day = $first; $day <= $until; $day = $day->modify('+1 day')) {
            if (in_array($day->format('N'), array_map('strval', $weekdays), true)) {
                $dates[] = $day->format('Y-m-d');
                if (count($dates) > 120) {
                    throw new InvalidArgumentException('Máximo de 120 ocorrências.');
                }
            }
        }
    }
    $dates = array_values(array_unique($dates));
    sort($dates);
    if (! $dates || count($dates) > 120) {
        throw new InvalidArgumentException('Selecione entre 1 e 120 ocorrências.');
    }
    $dayOffset = (int) scheduling_date($baseDate)->diff(scheduling_date($end->format('Y-m-d')))->format('%r%a');
    $occurrences = [];
    foreach ($dates as $date) {
        $occurrenceStart = scheduling_local_time($date . ' ' . $start->format('H:i:s'));
        $endDate = scheduling_date($date)->modify('+' . $dayOffset . ' days')->format('Y-m-d');
        $occurrenceEnd = scheduling_local_time($endDate . ' ' . $end->format('H:i:s'));
        if ($occurrenceEnd <= $occurrenceStart) {
            throw new InvalidArgumentException('Duração de ocorrência inválida.');
        }
        $occurrences[] = ['starts_at' => $occurrenceStart->format('Y-m-d H:i:s'), 'ends_at' => $occurrenceEnd->format('Y-m-d H:i:s')];
    }
    $observations = trim((string) ($data['observations'] ?? ''));
    if (strlen($observations) > 10000) {
        throw new InvalidArgumentException('As observações excedem o máximo de 10000 bytes.');
    }
    return ['id' => (int) $id, 'patient_id' => (int) $data['patient_id'], 'carer_id' => (int) $data['carer_id'],
        'municipality' => $municipality, 'hourly_rate' => (float) $rate,
        'observations' => $observations, 'skip_conflicts' => ($data['skip_conflicts'] ?? '') === '1' || ($data['skip_conflicts'] ?? false) === true, 'occurrences' => $occurrences];
}

function scheduling_check(PDO $pdo, array $plan): array
{
    $conflicts = [];
    $accepted = [];
    $ignoreIds = array_values(array_unique(array_filter(array_map(fn($entry)=>(int)($entry['id']??$plan['id']),$plan['occurrences']))));
    $ignoreSql = $ignoreIds ? ' AND id NOT IN ('.implode(',',array_fill(0,count($ignoreIds),'?')).')' : '';
    $query = $pdo->prepare("SELECT id, starts_at, ends_at, status FROM services WHERE carer_id = ?".$ignoreSql." AND status != 'CANCELLED' AND starts_at < ? AND ends_at > ? ORDER BY starts_at");
    foreach ($plan['occurrences'] as $index => $occurrence) {
        $item=array_merge($plan,$occurrence);
        $before=count($conflicts);
        $query->execute([$item['carer_id'],...$ignoreIds,$item['ends_at'],$item['starts_at']]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $existing) {
            $conflicts[]=['occurrence'=>$index,'starts_at'=>$item['starts_at'],'service_id'=>(int)$existing['id'],'existing_starts_at'=>$existing['starts_at'],'existing_ends_at'=>$existing['ends_at'],'existing_status'=>$existing['status'],'type'=>$existing['status']==='CANCELLED'?'cancelled_duplicate':'overlap'];
        }
        foreach ($accepted as $previous) {
            if ((int)$previous['carer_id']===(int)$item['carer_id'] && $previous['starts_at']<$item['ends_at'] && $previous['ends_at']>$item['starts_at']) $conflicts[]=['occurrence'=>$index,'starts_at'=>$item['starts_at'],'service_id'=>null,'type'=>'batch_overlap'];
        }
        if (count($conflicts)===$before || !$plan['skip_conflicts']) $accepted[]=$item;
    }
    $skipped = count(array_unique(array_column($conflicts, 'occurrence')));
    $eligible = count($plan['occurrences']) - $skipped;
    return ['valid' => (! $conflicts || $plan['skip_conflicts']) && $eligible > 0 && (empty($plan['unmatched']) || !empty($plan['allow_unmatched'])), 'count' => count($plan['occurrences']),
        'created_count' => 0, 'eligible_count' => $eligible, 'skipped_count' => $plan['skip_conflicts'] ? $skipped : 0,
        'occurrences' => array_map(function ($entry) use ($plan) { $item=array_merge($plan,$entry); $entry['pocket_value']=round(service_rate_amount($item),2); return $entry; },$plan['occurrences']), 'conflicts' => $conflicts, 'errors' => [], 'unmatched' => $plan['unmatched'] ?? []];
}

/** Caller owns authentication/authorization and CSRF. Preview never writes. */
function scheduling_preview_hash(array $plan, array $conflicts): string
{
    return hash('sha256', json_encode([$plan, $conflicts], JSON_THROW_ON_ERROR));
}

function scheduling_preview(PDO $pdo, array $data, ?string &$proposalHash = null): array
{
    try {
        $plan = scheduling_plan($pdo, $data);
        $result = scheduling_check($pdo, $plan);
        $proposalHash = scheduling_preview_hash($plan, $result['conflicts']);
        return $result;
    } catch (InvalidArgumentException $error) {
        return ['valid' => false, 'count' => 0, 'created_count' => 0, 'eligible_count' => 0, 'skipped_count' => 0, 'occurrences' => [], 'conflicts' => [], 'errors' => [$error->getMessage()]];
    }
}

/** Requires service_batches(idempotency_key PK, payload_hash, result_json, created_at). */
function scheduling_commit(PDO $pdo, array $data, ?string $expectedPreview = null): array
{
    $key = (string) ($data['idempotency_key'] ?? '');
    if (! preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $key)) {
        throw new InvalidArgumentException('Chave de idempotência inválida.');
    }
    if ($pdo->inTransaction()) {
        throw new LogicException('scheduling_commit requires its own transaction.');
    }
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $plan = scheduling_plan($pdo, $data);
        $hash = hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $stmt = $pdo->prepare('SELECT payload_hash, result_json FROM service_batches WHERE idempotency_key = ?');
        $stmt->execute([$key]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($batch) {
            if (! hash_equals($batch['payload_hash'], $hash)) {
                throw new InvalidArgumentException('Chave de idempotência já usada com outros dados.');
            }
            $result = json_decode($batch['result_json'], true, 512, JSON_THROW_ON_ERROR);
            $result['replayed'] = true;
            $pdo->exec('COMMIT');
            return $result;
        }
        $result = scheduling_check($pdo, $plan);
        if ($expectedPreview !== null && !hash_equals($expectedPreview, scheduling_preview_hash($plan, $result['conflicts']))) {
            throw new InvalidArgumentException('Pré-visualize as escalas antes de guardar. A seleção foi alterada ou expirou.');
        }
        if ($result['valid']) {
            $overlaps = planning_shared_visits($pdo, $plan, $result['conflicts']);
            $_SESSION['shared_visit_secret'] ??= bin2hex(random_bytes(32));
            $confirmation = hash_hmac('sha256', json_encode([$key, $plan, $overlaps], JSON_THROW_ON_ERROR), $_SESSION['shared_visit_secret']);
            if ($overlaps && !hash_equals($confirmation, (string)($data['shared_confirmation'] ?? ''))) {
                $pdo->exec('ROLLBACK');
                return array_merge($result, ['valid'=>false, 'shared_visits'=>$overlaps, 'shared_confirmation'=>$confirmation]);
            }
        }
        if (! $result['valid']) {
            $pdo->exec('ROLLBACK');
            return $result;
        }
        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('Europe/Lisbon')))->format('Y-m-d H:i:s');
        $ids = [];
        $conflictedIndexes = array_column($result['conflicts'], 'occurrence');
        foreach ($plan['occurrences'] as $index => $occurrence) {
            if ($plan['skip_conflicts'] && in_array($index, $conflictedIndexes, true)) {
                continue;
            }
            $item=array_merge($plan,$occurrence);
            // Older installations reserved even cancelled slots through a UNIQUE constraint.
            $release = $pdo->prepare("DELETE FROM services WHERE carer_id=? AND starts_at=? AND status='CANCELLED' AND id!=?");
            $release->execute([$item['carer_id'], $item['starts_at'], $item['id']]);
            $values=[$item['patient_id'],$item['carer_id'],$item['starts_at'],$item['ends_at'],$item['hourly_rate'],$item['observations'],$item['municipality'],$timestamp];
            if ($item['id']) {
                $pdo->prepare('UPDATE services SET patient_id=?,carer_id=?,starts_at=?,ends_at=?,hourly_rate=?,observations=?,municipality=?,updated_at=?,hourly_rate_special=?,client_rate_snapshot=?,client_special_snapshot=? WHERE id=?')->execute([...$values,$item['hourly_rate_special']??null,$item['client_rate_snapshot']??null,$item['client_special_snapshot']??null,$item['id']]);
                $ids[]=(int)$item['id'];
            } else {
                $series=hash('sha256',$key.'|'.($item['family']??'slot-1'));
                $pdo->prepare("INSERT INTO services(patient_id,carer_id,starts_at,ends_at,hourly_rate,observations,municipality,updated_at,created_at,status,hourly_rate_special,client_rate_snapshot,client_special_snapshot,series_key) VALUES(?,?,?,?,?,?,?,?,?,'SCHEDULED',?,?,?,?)")->execute([...$values,$timestamp,$item['hourly_rate_special']??null,$item['client_rate_snapshot']??null,$item['client_special_snapshot']??null,$series]);
                $ids[]=(int)$pdo->lastInsertId();
            }
        }
        $result['service_ids'] = $ids;
        $result['created_count'] = $plan['id'] ? 0 : count($ids);
        $result['updated_count'] = $plan['id'] ? count($ids) : 0;
        $result['replayed'] = false;
        if (function_exists('audit_event')) {
            audit_event($pdo, 'save_service_batch', $ids[0] ?? null);
        }
        $pdo->prepare('INSERT INTO service_batches (idempotency_key, payload_hash, result_json, created_at) VALUES (?, ?, ?, ?)')->execute([$key, $hash, json_encode($result, JSON_THROW_ON_ERROR), $timestamp]);
        $pdo->exec('COMMIT');
        return $result;
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}
