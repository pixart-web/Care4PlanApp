<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

verify_csrf();

$page = $_GET['page'] ?? 'home';
$action = $_POST['action'] ?? null;

if ($action) {
    handle_action($action);
}

if ($page === 'api.events') {
    events_api();
}

route($page);

function handle_action(string $action): void
{
    $pdo = db();

    if ($action === 'login') {
        $login = trim($_POST['username'] ?? '');
        $stmt = $pdo->prepare("SELECT users.* FROM users LEFT JOIN carers ON carers.id = users.carer_id WHERE users.username = ? OR carers.email = ? ORDER BY users.username = ? DESC LIMIT 1");
        $stmt->execute([$login, $login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            redirect('dashboard');
        }

        $_SESSION['flash_error'] = 'Credenciais inválidas.';
        redirect('login');
    }

    if ($action === 'logout') {
        session_destroy();
        redirect('login');
    }

    $user = require_auth();
    $operational = ['ADMIN', 'EDITOR'];

    if ($action === 'save_carer') {
        require_role($operational);
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $color = trim($_POST['color_hex'] ?? '') ?: generate_unique_color();
        $pocketAdjustment = (float) ($_POST['pocket_adjustment'] ?? 0);
        $now = now();

        if ($name === '' || $phone === '' || $email === '') {
            $_SESSION['flash_error'] = 'Preencha nome, telefone e email.';
            redirect('carers');
        }

        if ($id) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND carer_id != ?');
            $stmt->execute([$name, $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = 'Já existe outro utilizador com esse email.';
                redirect('carers');
            }

            $stmt = $pdo->prepare('UPDATE carers SET name = ?, phone = ?, email = ?, color_hex = ?, pocket_adjustment = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $email, $color, $pocketAdjustment, $now, $id]);

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE carer_id = ?');
            $stmt->execute([$id]);
            $hasUser = (int) $stmt->fetchColumn() > 0;

            if (! $hasUser) {
                $stmt = $pdo->prepare('INSERT INTO users (name, username, password, role, carer_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $name, password_hash($password !== '' ? $password : bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'CARER', $id, $now, $now]);
                $pdo->prepare('UPDATE carers SET user_id = ? WHERE id = ?')->execute([(int) $pdo->lastInsertId(), $id]);
            } elseif ($password !== '') {
                $pdo->prepare('UPDATE users SET name = ?, username = ?, password = ?, updated_at = ? WHERE carer_id = ?')
                    ->execute([$name, $name, password_hash($password, PASSWORD_DEFAULT), $now, $id]);
            } else {
                $pdo->prepare('UPDATE users SET name = ?, username = ?, updated_at = ? WHERE carer_id = ?')
                    ->execute([$name, $name, $now, $id]);
            }
        } else {
            if ($password === '') {
                $_SESSION['flash_error'] = 'Defina uma password para a cuidadora.';
                redirect('carers');
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $stmt->execute([$name]);
            if ((int) $stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = 'Já existe um utilizador com esse email.';
                redirect('carers');
            }

            $stmt = $pdo->prepare('INSERT INTO carers (name, age, phone, email, color_hex, pocket_adjustment, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, null, $phone, $email, $color, $pocketAdjustment, $now, $now]);
            $carerId = (int) $pdo->lastInsertId();
            record_entity_movement($pdo, 'CARER', $name, 'IN');
            $stmt = $pdo->prepare('INSERT INTO users (name, username, password, role, carer_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $name, password_hash($password, PASSWORD_DEFAULT), 'CARER', $carerId, $now, $now]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE carers SET user_id = ? WHERE id = ?')->execute([$userId, $carerId]);
        }

        $_SESSION['flash'] = 'Cuidadora guardada.';
        redirect('carers');
    }

    if ($action === 'delete_carer') {
        require_role($operational);
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('SELECT name FROM carers WHERE id = ?');
        $stmt->execute([$id]);
        $carerName = (string) ($stmt->fetchColumn() ?: 'Cuidadora');
        $pdo->prepare('DELETE FROM users WHERE carer_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM carers WHERE id = ?')->execute([$id]);
        record_entity_movement($pdo, 'CARER', $carerName, 'OUT');
        $_SESSION['flash'] = 'Cuidadora apagada.';
        redirect('carers');
    }
    if ($action === 'save_patient') {
        require_role($operational);
        $id = (int) ($_POST['id'] ?? 0);
        $fields = [
            'name', 'birth_date', 'tax_number', 'sns_number', 'address', 'postal_code', 'city', 'phone', 'email',
            'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'emergency_contact_email',
            'doctor_name', 'health_unit', 'diagnoses', 'allergies', 'medication', 'medication_schedule', 'mobility',
            'cognition', 'fall_risk', 'incontinence', 'food_restrictions', 'care_needs', 'daily_routine', 'preferences',
            'avoid', 'special_instructions', 'home_access', 'pets', 'equipment', 'status', 'support_start_date',
            'contracted_hours', 'service_type', 'admin_notes', 'observations'
        ];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = trim((string) ($_POST[$field] ?? ''));
        }
        $data['age'] = ($_POST['age'] ?? '') !== '' ? (int) $_POST['age'] : null;
        $data['client_hourly_rate'] = ($_POST['client_hourly_rate'] ?? '') !== '' ? (float) $_POST['client_hourly_rate'] : null;
        $now = now();

        if ($data['name'] === '' || $data['address'] === '') {
            $_SESSION['flash_error'] = 'Preencha nome e morada.';
            redirect('patients');
        }

        $columns = array_merge($fields, ['age', 'client_hourly_rate']);
        if ($id) {
            $set = implode(', ', array_map(fn($column) => $column . ' = ?', $columns));
            $values = array_map(fn($column) => $data[$column] ?? null, $columns);
            $values[] = $now;
            $values[] = $id;
            $pdo->prepare("UPDATE patients SET {$set}, updated_at = ? WHERE id = ?")->execute($values);
        } else {
            $insertColumns = array_merge($columns, ['created_at', 'updated_at']);
            $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
            $values = array_map(fn($column) => $data[$column] ?? null, $columns);
            $values[] = $now;
            $values[] = $now;
            $pdo->prepare('INSERT INTO patients (' . implode(', ', $insertColumns) . ") VALUES ({$placeholders})")->execute($values);
            record_entity_movement($pdo, 'PATIENT', $data['name'], 'IN');
        }
        $_SESSION['flash'] = 'Cliente guardado.';
        redirect('patients');
    }
    if ($action === 'delete_patient') {
        require_role($operational);
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('SELECT name FROM patients WHERE id = ?');
        $stmt->execute([$id]);
        $patientName = (string) ($stmt->fetchColumn() ?: 'Utente');
        $pdo->prepare('DELETE FROM patients WHERE id = ?')->execute([$id]);
        record_entity_movement($pdo, 'PATIENT', $patientName, 'OUT');
        $_SESSION['flash'] = 'Cliente apagado.';
        redirect('patients');
    }
    if ($action === 'save_service') {
        require_role($operational);
        $serviceId = (int) ($_POST['id'] ?? 0);
        $patientId = (int) $_POST['patient_id'];
        $carerId = (int) $_POST['carer_id'];
        $startTimestamp = strtotime($_POST['starts_at'] ?? '');
        $endTimestamp = strtotime($_POST['ends_at'] ?? '');
        $start = $startTimestamp ? date('Y-m-d H:i:00', $startTimestamp) : '';
        $end = $endTimestamp ? date('Y-m-d H:i:00', $endTimestamp) : '';
        $rate = max(5, min(15, (float) ($_POST['hourly_rate'] ?? 5)));
        $observations = trim($_POST['observations'] ?? '');
        $municipality = trim($_POST['municipality'] ?? '');
        $allowedMunicipalities = ['Portimão', 'Lagos', 'Lagoa', 'Albufeira', 'Silves'];
        $now = now();

        if (! in_array($municipality, $allowedMunicipalities, true)) {
            $_SESSION['flash_error'] = 'Selecione o concelho da escala.';
            redirect('scales');
        }

        if (! $startTimestamp || ! $endTimestamp || $endTimestamp <= $startTimestamp) {
            $_SESSION['flash_error'] = 'Confirme a hora de entrada e a hora de saída.';
            redirect('scales');
        }

        if (has_overlap($carerId, $start, $end, $serviceId ?: null)) {
            $_SESSION['flash_error'] = 'A cuidadora já tem escala nesse horário.';
            redirect('scales');
        }

        if ($serviceId) {
            $pdo->prepare('UPDATE services SET patient_id = ?, carer_id = ?, starts_at = ?, ends_at = ?, hourly_rate = ?, observations = ?, municipality = ?, updated_at = ? WHERE id = ?')
                ->execute([$patientId, $carerId, $start, $end, $rate, $observations, $municipality, $now, $serviceId]);
        } else {
            $pdo->prepare('INSERT INTO services (patient_id, carer_id, starts_at, ends_at, hourly_rate, observations, municipality, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$patientId, $carerId, $start, $end, $rate, $observations, $municipality, 'SCHEDULED', $now, $now]);
        }

        $_SESSION['flash'] = 'Escala guardada.';
        header('Location: index.php?page=scales&municipality=' . urlencode($municipality) . '&scale_date=' . urlencode(substr($start, 0, 10)));
        exit;
    }

    if ($action === 'cancel_service') {
        require_role($operational);
        $pdo->prepare("UPDATE services SET status = 'CANCELLED', updated_at = ? WHERE id = ?")->execute([now(), (int) $_POST['id']]);
        redirect('scales');
    }

    if ($action === 'delete_service') {
        require_role($operational);
        $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([(int) $_POST['id']]);
        redirect('scales');
    }

    if ($action === 'done_service' || $action === 'undo_service') {
        $user = require_role(['CARER']);
        $serviceId = (int) $_POST['id'];
        $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ? AND carer_id = ?');
        $stmt->execute([$serviceId, $user['carer_id']]);
        if (! $stmt->fetch()) {
            http_response_code(403);
            exit('Acesso negado.');
        }

        if ($action === 'done_service') {
            $pdo->prepare("UPDATE services SET status = 'DONE', done_at = ?, done_by_user_id = ?, updated_at = ? WHERE id = ?")
                ->execute([now(), $user['id'], now(), $serviceId]);
        } else {
            $pdo->prepare("UPDATE services SET status = 'SCHEDULED', done_at = NULL, done_by_user_id = NULL, updated_at = ? WHERE id = ?")
                ->execute([now(), $serviceId]);
        }
        redirect('scales');
    }

    if ($action === 'save_profile') {
        $user = require_role(['CARER']);
        $pdo->prepare('UPDATE carers SET age = ?, phone = ?, email = ?, updated_at = ? WHERE id = ?')
            ->execute([$_POST['age'] !== '' ? (int) $_POST['age'] : null, trim($_POST['phone']), trim($_POST['email']), now(), $user['carer_id']]);
        $_SESSION['flash'] = 'Perfil atualizado.';
        redirect('profile');
    }


    if ($action === 'save_report') {
        $user = require_auth();
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $now = now();

        if ($title === '' || $body === '') {
            $_SESSION['flash_error'] = 'Preencha o título e o relatório.';
            redirect('reports');
        }

        if ($user['role'] === 'CARER') {
            $carerId = (int) $user['carer_id'];
            $patientId = (int) ($_POST['patient_id'] ?? 0);
            if ($patientId <= 0) {
                $_SESSION['flash_error'] = 'Selecione o utente associado ao relatório.';
                redirect('reports');
            }
            $stmt = $pdo->prepare('INSERT INTO reports (title, body, sender_user_id, sender_role, carer_id, patient_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $body, (int) $user['id'], 'CARER', $carerId, $patientId, $now, $now]);
        } else {
            require_role(['ADMIN', 'EDITOR']);
            $carerId = (int) ($_POST['carer_id'] ?? 0);
            if ($carerId <= 0) {
                $_SESSION['flash_error'] = 'Selecione a cuidadora associada ao relatório.';
                redirect('reports');
            }
            $stmt = $pdo->prepare('INSERT INTO reports (title, body, sender_user_id, sender_role, carer_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $body, (int) $user['id'], $user['role'], $carerId, $now, $now]);
        }

        $_SESSION['flash'] = 'Relatório criado.';
        redirect('reports');
    }

    if ($action === 'mark_report_read') {
        $user = require_auth();
        $reportId = (int) ($_POST['id'] ?? 0);
        if ($user['role'] === 'CARER') {
            $stmt = $pdo->prepare('UPDATE reports SET read_by_carer_at = COALESCE(read_by_carer_at, ?), updated_at = ? WHERE id = ? AND carer_id = ? AND sender_role != ?');
            $stmt->execute([now(), now(), $reportId, (int) $user['carer_id'], 'CARER']);
        } else {
            require_role(['ADMIN', 'EDITOR']);
            $stmt = $pdo->prepare('UPDATE reports SET read_by_admin_at = COALESCE(read_by_admin_at, ?), updated_at = ? WHERE id = ? AND sender_role = ?');
            $stmt->execute([now(), now(), $reportId, 'CARER']);
        }
        redirect('reports');
    }
    if ($action === 'reply_report') {
        $user = require_auth();
        $reportId = (int) ($_POST['id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        if ($body === '') {
            $_SESSION['flash_error'] = 'Escreva a resposta antes de enviar.';
            redirect('reports');
        }

        $stmt = $pdo->prepare('SELECT * FROM reports WHERE id = ?');
        $stmt->execute([$reportId]);
        $original = $stmt->fetch();
        if (! $original) {
            http_response_code(404);
            exit('Relatório não encontrado.');
        }

        if ($user['role'] === 'CARER') {
            if ((int) $original['carer_id'] !== (int) $user['carer_id']) {
                http_response_code(403);
                exit('Acesso negado.');
            }
            $senderRole = 'CARER';
        } else {
            require_role(['ADMIN', 'EDITOR']);
            if (empty($original['carer_id'])) {
                $_SESSION['flash_error'] = 'Este relatório não tem cuidadora associada para responder.';
                redirect('reports');
            }
            $senderRole = $user['role'];
        }

        $now = now();
        $stmt = $pdo->prepare('INSERT INTO report_replies (report_id, sender_user_id, sender_role, body, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$reportId, (int) $user['id'], $senderRole, $body, $now]);
        $pdo->prepare('UPDATE reports SET updated_at = ? WHERE id = ?')->execute([$now, $reportId]);
        $_SESSION['flash'] = 'Resposta adicionada ao histórico do relatório.';
        redirect('reports');
    }
    if ($action === 'delete_report') {
        $user = require_auth();
        $reportId = (int) ($_POST['id'] ?? 0);
        if ($user['role'] === 'CARER') {
            $stmt = $pdo->prepare('DELETE FROM reports WHERE id = ? AND carer_id = ?');
            $stmt->execute([$reportId, (int) $user['carer_id']]);
        } else {
            require_role(['ADMIN', 'EDITOR']);
            $stmt = $pdo->prepare('DELETE FROM reports WHERE id = ?');
            $stmt->execute([$reportId]);
        }
        $_SESSION['flash'] = 'Relatório apagado.';
        redirect('reports');
    }
    if ($action === 'save_user') {
        require_role(['ADMIN']);
        $id = (int) ($_POST['id'] ?? 0);
        $role = $_POST['role'];
        $carerId = $_POST['carer_id'] !== '' ? (int) $_POST['carer_id'] : null;
        if ($id) {
            if ($_POST['password'] !== '') {
                $pdo->prepare('UPDATE users SET name = ?, username = ?, password = ?, role = ?, carer_id = ?, updated_at = ? WHERE id = ?')
                    ->execute([trim($_POST['name']), trim($_POST['username']), password_hash($_POST['password'], PASSWORD_DEFAULT), $role, $carerId, now(), $id]);
            } else {
                $pdo->prepare('UPDATE users SET name = ?, username = ?, role = ?, carer_id = ?, updated_at = ? WHERE id = ?')
                    ->execute([trim($_POST['name']), trim($_POST['username']), $role, $carerId, now(), $id]);
            }
        } else {
            $pdo->prepare('INSERT INTO users (name, username, password, role, carer_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([trim($_POST['name']), trim($_POST['username']), password_hash($_POST['password'], PASSWORD_DEFAULT), $role, $carerId, now(), now()]);
        }
        redirect('users');
    }

    if ($action === 'delete_user') {
        $user = require_role(['ADMIN']);
        $id = (int) $_POST['id'];
        if ($id !== (int) $user['id']) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        }
        redirect('users');
    }
}

function route(string $page): void
{
    if ($page === 'home') {
        current_user() ? redirect('dashboard') : redirect('login');
    }

    if ($page === 'login') {
        require __DIR__ . '/views/login.php';
        return;
    }

    $user = require_auth();
    if ($page === 'dashboard') {
        require __DIR__ . ($user['role'] === 'CARER' ? '/views/carer-dashboard.php' : '/views/dashboard.php');
        return;
    }

    $map = [
        'scales' => 'scales.php',
        'carers' => 'carers.php',
        'patients' => 'patients.php',
        'users' => 'users.php',
        'pocket' => 'pocket.php',
        'profile' => 'profile.php',
        'reports' => 'reports.php',
        'finances' => 'finances.php',
        'settings' => 'placeholder.php',
    ];

    if (! isset($map[$page])) {
        http_response_code(404);
        exit('Página não encontrada.');
    }

    require __DIR__ . '/views/' . $map[$page];
}

function events_api(): never
{
    $user = require_auth();
    $params = [];
    $sql = "SELECT services.*, patients.name AS patient_name, patients.address, carers.name AS carer_name, carers.color_hex
            FROM services
            JOIN patients ON patients.id = services.patient_id
            JOIN carers ON carers.id = services.carer_id
            WHERE services.starts_at < ? AND services.ends_at > ?";
    $params[] = date('Y-m-d H:i:s', strtotime($_GET['end'] ?? '+1 month'));
    $params[] = date('Y-m-d H:i:s', strtotime($_GET['start'] ?? '-1 month'));

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
            'extendedProps' => array_merge($service, [
                'has_observations' => trim((string) ($service['observations'] ?? '')) !== '',
                'pocket_value' => service_pocket_value($service),
            ]),
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($events);
    exit;
}


