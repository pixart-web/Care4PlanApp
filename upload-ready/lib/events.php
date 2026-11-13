<?php
declare(strict_types=1);

function events_api(): never
{
    $user = require_auth();
    try {
        $parse = static function (string $value): DateTimeImmutable {
            $date = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors && ($errors['warning_count'] || $errors['error_count'])) throw new InvalidArgumentException();
            return $date;
        };
        $rangeStart = $parse((string) ($_GET['start'] ?? '-1 month'));
        $rangeEnd = $parse((string) ($_GET['end'] ?? '+1 month'));
        if ($rangeEnd <= $rangeStart || $rangeEnd->getTimestamp() - $rangeStart->getTimestamp() > 366 * 86400) throw new InvalidArgumentException();
    } catch (Throwable $error) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Selecione um intervalo válido, até um ano.']);
        exit;
    }
    $params = [];
    $sql = "SELECT services.id, services.patient_id, services.carer_id, services.starts_at, services.ends_at, services.hourly_rate, services.hourly_rate_special, services.status, services.observations, services.municipality, patients.name AS patient_name, patients.address, carers.name AS carer_name, carers.color_hex
            FROM services
            JOIN patients ON patients.id = services.patient_id
            JOIN carers ON carers.id = services.carer_id
            WHERE services.status != 'CANCELLED' AND services.starts_at < ? AND services.ends_at > ?";
    $zone = new DateTimeZone(APP_TIMEZONE);
    $params[] = $rangeEnd->setTimezone($zone)->format('Y-m-d H:i:s');
    $params[] = $rangeStart->setTimezone($zone)->format('Y-m-d H:i:s');

    if ($user['role'] === 'CARER') {
        $sql .= ' AND services.carer_id = ?';
        $params[] = $user['carer_id'];
    } else {
        if (! empty($_GET['carer_id'])) {
            $sql .= ' AND services.carer_id = ?';
            $params[] = (int) $_GET['carer_id'];
        }
        if (! empty($_GET['patient_id'])) {
            $sql .= ' AND services.patient_id = ?';
            $params[] = (int) $_GET['patient_id'];
        }
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $events = [];
    foreach ($stmt->fetchAll() as $service) {
        $events[] = [
            'id' => $service['id'],
            'title' => $user['role'] === 'CARER' ? $service['patient_name'] : $service['patient_name'] . ' - ' . $service['carer_name'],
            'start' => str_replace(' ', 'T', $service['starts_at']),
            'end' => str_replace(' ', 'T', $service['ends_at']),
            'backgroundColor' => $service['status'] === 'CANCELLED' ? '#78716c' : ($service['status'] === 'DONE' ? '#2f6f4f' : $service['color_hex']),
            'borderColor' => $service['color_hex'],
            'extendedProps' => array_merge(array_intersect_key($service, array_flip($user['role'] === 'CARER'
                ? ['address', 'municipality', 'status', 'starts_at', 'ends_at', 'observations']
                : ['id', 'patient_id', 'carer_id', 'patient_name', 'carer_name', 'color_hex', 'address', 'municipality', 'status', 'starts_at', 'ends_at', 'hourly_rate', 'observations'])), [
                'has_observations' => trim((string) ($service['observations'] ?? '')) !== '',
                'pocket_value' => service_pocket_value($service),
            ]),
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($events);
    exit;
}


