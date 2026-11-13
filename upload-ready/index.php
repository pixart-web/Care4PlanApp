<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/events.php';

verify_csrf();
validate_request_shape();

$page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'home';
$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : null;

if ($action) {
    handle_action($action);
}

if ($page === 'client-statement') {
    require_role(['ADMIN']);
    require __DIR__.'/views/client-statement.php';
    exit;
}

if ($page === 'photo') profile_photo_endpoint();

if ($page === 'api.events') {
    events_api();
}

route($page);

function handle_action(string $action): void
{
    $pdo = db();
    try {
        dispatch_action($pdo, $action);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function dispatch_action(PDO $pdo, string $action): void
{
    if ($action === 'login') {
        $login = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($login === '' || strlen($login) > 254 || $password === '' || strlen($password) > 1024) {
            login_failed($pdo, substr($login, 0, 254));
            reject_action('Credenciais inválidas.', 'login');
        }
        $ip = $pdo->prepare('SELECT attempts FROM login_attempts WHERE bucket = ? AND window_started > ?');
        $ip->execute([login_ip_bucket(), time() - 900]);
        if (login_limited($pdo, $login) || (int) $ip->fetchColumn() >= 30) {
            reject_action('Demasiadas tentativas. Tente novamente dentro de 15 minutos.', 'login');
        }
        $stmt = $pdo->prepare("SELECT users.* FROM users LEFT JOIN carers ON carers.id = users.carer_id WHERE users.username = ? OR carers.email = ? ORDER BY users.username = ? DESC LIMIT 1");
        $stmt->execute([$login, $login, $login]);
        $user = $stmt->fetch();

        $passwordMatches = $user
            ? password_verify($password, $user['password'])
            : password_verify($password, '$2y$12$Gn69FfU77v5t4TnHSZR5k.5R0107Jwap/QJSGg09C42DcyS.iobzW');
        if ($user && $passwordMatches) {
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE users SET password = ?, updated_at = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), now(), $user['id']]);
            }
            login_succeeded($pdo, $login);
            session_regenerate_id(true);
            $authenticatedAt = time();
            $_SESSION = [
                '_csrf' => bin2hex(random_bytes(32)),
                '_auth_started_at' => $authenticatedAt,
                '_auth_last_activity' => $authenticatedAt,
                '_auth_rotated_at' => $authenticatedAt,
            ];
            $_SESSION['user_id'] = $user['id'];
            audit_event($pdo, 'login');
            redirect('dashboard');
        }
        login_failed($pdo, $login);
        $_SESSION['flash_error'] = 'Credenciais inválidas.';
        redirect('login');
    }

    if ($action === 'logout') {
        if (! empty($_SESSION['user_id'])) {
            audit_event($pdo, 'logout');
        }
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        session_destroy();
        redirect('login');
    }

    $user = require_auth();
    $operational = ['ADMIN', 'EDITOR'];
    if (in_array($action, ['save_report', 'reply_report'], true) && (strlen((string) ($_POST['body'] ?? '')) > 20000 || strlen((string) ($_POST['title'] ?? '')) > 250)) {
        reject_action('O relatório excede o tamanho permitido.', 'reports');
    }
    // Keep administrator validation and its write in the same SQLite snapshot.
    if (in_array($action, ['save_user', 'delete_user'], true)) $pdo->beginTransaction();
    validate_action($pdo, $action, $user);
    if ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $query = $pdo->prepare('SELECT password FROM users WHERE id=?');
        $query->execute([$user['id']]);
        $hash = $query->fetchColumn();
        if (!password_verify($current, $hash)) reject_action('A palavra-passe atual está incorreta.', 'profile');
        if (!valid_new_password($new) || $new !== ($_POST['confirm_password'] ?? '')) {
            reject_action('Confirme a nova palavra-passe com pelo menos 12 caracteres.', 'profile');
        }
        $change = $pdo->prepare('UPDATE users SET password=?, updated_at=? WHERE id=? AND password=?');
        $change->execute([password_hash($new, PASSWORD_DEFAULT), now(), $user['id'], $hash]);
        if (!$change->rowCount()) reject_action('A palavra-passe foi alterada. Tente novamente.', 'profile');
        session_regenerate_id(true);
        $_SESSION['flash'] = 'Palavra-passe alterada.';
        redirect('profile');
    }
    $profileExtra = []; $profilePhoto = null;
    if (in_array($action, ['save_patient','save_carer','save_profile'], true)) {
        try {
            $profileExtra = profile_extra_values($action, $_POST);
            $profilePhoto = profile_photo_upload($_FILES['photo'] ?? []);
        } catch (InvalidArgumentException $error) {
            reject_action($error->getMessage(), ['save_patient'=>'patients','save_carer'=>'carers','save_profile'=>'profile'][$action]);
        }
    }

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
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND (carer_id IS NULL OR carer_id != ?)');
            $stmt->execute([$name, $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = 'Já existe outro utilizador com esse email.';
                redirect('carers');
            }

            $pdo->beginTransaction();
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

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO carers (name, age, phone, email, color_hex, pocket_adjustment, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, null, $phone, $email, $color, $pocketAdjustment, $now, $now]);
            $carerId = (int) $pdo->lastInsertId();
            record_entity_movement($pdo, 'CARER', $name, 'IN');
            $stmt = $pdo->prepare('INSERT INTO users (name, username, password, role, carer_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $name, password_hash($password, PASSWORD_DEFAULT), 'CARER', $carerId, $now, $now]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE carers SET user_id = ? WHERE id = ?')->execute([$userId, $carerId]);
        }

        profile_extra_save($pdo, 'carers', $id ?: $carerId, $profileExtra);
        profile_photo_save($pdo, 'carer', $id ?: $carerId, $profilePhoto);
        audit_event($pdo, $action, $id ?: $carerId);
        $pdo->commit();
        $_SESSION['flash'] = 'Cuidadora guardada.';
        redirect('carers');
    }

    if ($action === 'delete_carer') {
        require_role($operational);
        $id = (int) $_POST['id'];
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT name FROM carers WHERE id = ?');
        $stmt->execute([$id]);
        $carerName = (string) ($stmt->fetchColumn() ?: 'Cuidadora');
        $pdo->prepare('DELETE FROM users WHERE carer_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM carers WHERE id = ?')->execute([$id]);
        record_entity_movement($pdo, 'CARER', $carerName, 'OUT');
        audit_event($pdo, $action, $id);
        $pdo->commit();
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
        $pdo->beginTransaction();
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
            $id = (int) $pdo->lastInsertId();
            record_entity_movement($pdo, 'PATIENT', $data['name'], 'IN');
        }
        profile_extra_save($pdo, 'patients', $id, $profileExtra);
        profile_photo_save($pdo, 'patient', $id, $profilePhoto);
        audit_event($pdo, $action, $id);
        $pdo->commit();
        $_SESSION['flash'] = 'Cliente guardado.';
        redirect('patients');
    }
    if ($action === 'delete_patient') {
        require_role($operational);
        $id = (int) $_POST['id'];
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT name FROM patients WHERE id = ?');
        $stmt->execute([$id]);
        $patientName = (string) ($stmt->fetchColumn() ?: 'Cliente');
        $pdo->prepare('DELETE FROM patients WHERE id = ?')->execute([$id]);
        record_entity_movement($pdo, 'PATIENT', $patientName, 'OUT');
        audit_event($pdo, $action, $id);
        $pdo->commit();
        $_SESSION['flash'] = 'Cliente apagado.';
        redirect('patients');
    }
    if ($action === 'preview_service') {
        require_role($operational);
        $result = scheduling_preview($pdo, $_POST);
        if (!$result['errors']) {
            $token = bin2hex(random_bytes(24));
            $plan = scheduling_plan($pdo, $_POST);
            $_SESSION['service_previews'] = array_slice($_SESSION['service_previews'] ?? [], -9, null, true);
            $_SESSION['service_previews'][$token] = ['hash' => hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR)), 'expires' => time() + 1800];
            $result['preview_token'] = $token;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'save_service') {
        require_role($operational);
        try {
            $plan = scheduling_plan($pdo, $_POST);
            if (($_POST['recurrence_mode'] ?? 'none') !== 'none' || in_array($_POST['edit_series'] ?? '', ['1','future'], true) || ($_POST['extra_times'] ?? '') === '1' || ($_POST['planning_mode'] ?? '') === 'copy_month') {
                $preview = $_SESSION['service_previews'][$_POST['preview_token'] ?? ''] ?? null;
                if (!$preview || $preview['expires'] < time() || !hash_equals($preview['hash'], hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR)))) {
                    reject_action('Pré-visualize as escalas antes de guardar. A seleção foi alterada ou expirou.', 'scales');
                }
            }
            $result = scheduling_commit($pdo, $_POST);
            if (!empty($result['shared_visits'])) {
                require __DIR__.'/views/confirm-shared.php';
                exit;
            }
            if (!$result['valid']) reject_action('Existem escalas em conflito. Pré-visualize e corrija a seleção.', 'scales');
            $_SESSION['flash'] = count($result['service_ids']) . ' escala(s) guardada(s). ' . count($result['conflicts']) . ' conflito(s) ignorado(s).';
        } catch (InvalidArgumentException $error) {
            reject_action($error->getMessage(), 'scales');
        }
        $_SESSION['scales_municipality'] = $plan['municipality'];
        header('Location: index.php?page=scales&municipality=' . urlencode($plan['municipality']) . '&scale_date=' . urlencode(substr($plan['occurrences'][0]['starts_at'], 0, 10)));
        exit;
    }

    if (in_array($action, ['cancel_service', 'delete_service'], true)) {
        require_role($operational);
        $pdo->beginTransaction();
        $lookup = $pdo->prepare('SELECT * FROM services WHERE id = ?');
        $lookup->execute([(int) $_POST['id']]);
        $service = $lookup->fetch();
        if ($service) {
            $targets = [$service];
            if (($_POST['future_series'] ?? '') === '1' && $service['status'] === 'SCHEDULED') {
                $targets = array_filter(planning_series($pdo, $service), fn($row) => $row['starts_at'] >= $service['starts_at']);
            }
            $remove = $pdo->prepare('DELETE FROM services WHERE id = ?');
            foreach ($targets as $target) {
                $remove->execute([$target['id']]);
                audit_event($pdo, $action, (int)$target['id']);
            }
        }
        $pdo->commit();
        redirect('scales');
    }

    if ($action === 'done_service' || $action === 'undo_service') {
        $user = require_role(['CARER']);
        $serviceId = (int) $_POST['id'];
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND carer_id = ? AND status != 'CANCELLED'");
        $stmt->execute([$serviceId, $user['carer_id']]);
        if (! $stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(403);
            exit('Acesso negado.');
        }

        if ($action === 'done_service') {
            $stmt = $pdo->prepare("UPDATE services SET status = 'DONE', done_at = ?, done_by_user_id = ?, updated_at = ? WHERE id = ? AND carer_id = ? AND status = 'SCHEDULED'");
            $stmt->execute([now(), $user['id'], now(), $serviceId, $user['carer_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE services SET status = 'SCHEDULED', done_at = NULL, done_by_user_id = NULL, updated_at = ? WHERE id = ? AND carer_id = ? AND status = 'DONE'");
            $stmt->execute([now(), $serviceId, $user['carer_id']]);
        }
        if ($stmt->rowCount() > 0) audit_event($pdo, $action, $serviceId);
        $pdo->commit();
        redirect('scales');
    }

    if ($action === 'save_profile') {
        $user = require_role(['CARER']);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE carers SET age = ?, phone = ?, email = ?, updated_at = ? WHERE id = ?')
            ->execute([$_POST['age'] !== '' ? (int) $_POST['age'] : null, trim($_POST['phone']), trim($_POST['email']), now(), $user['carer_id']]);
        profile_extra_save($pdo, 'carers', (int) $user['carer_id'], $profileExtra);
        profile_photo_save($pdo, 'carer', (int) $user['carer_id'], $profilePhoto);
        audit_event($pdo, $action, (int) $user['carer_id']);
        $pdo->commit();
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
            if ($patientId <= 0 || !may_report_patient($pdo, $user, $patientId)) {
                $_SESSION['flash_error'] = 'Selecione o cliente associado ao relatório.';
                redirect('reports');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO reports (title, body, sender_user_id, sender_role, carer_id, patient_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $body, (int) $user['id'], 'CARER', $carerId, $patientId, $now, $now]);
        } else {
            require_role(['ADMIN', 'EDITOR']);
            $carerId = (int) ($_POST['carer_id'] ?? 0);
            $exists = $pdo->prepare('SELECT 1 FROM carers WHERE id = ?');
            $exists->execute([$carerId]);
            if ($carerId <= 0 || !$exists->fetchColumn()) {
                $_SESSION['flash_error'] = 'Selecione a cuidadora associada ao relatório.';
                redirect('reports');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO reports (title, body, sender_user_id, sender_role, carer_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $body, (int) $user['id'], $user['role'], $carerId, $now, $now]);
        }

        audit_event($pdo, $action, (int) $pdo->lastInsertId());
        $pdo->commit();
        $_SESSION['flash'] = 'Relatório criado.';
        redirect('reports');
    }

    if ($action === 'mark_report_read') {
        $user = require_auth();
        $pdo->beginTransaction();
        $reportId = (int) ($_POST['id'] ?? 0);
        if ($user['role'] === 'CARER') {
            $pdo->prepare("UPDATE report_replies SET read_by_carer_at = COALESCE(read_by_carer_at, ?) WHERE report_id = ? AND sender_role != 'CARER' AND report_id IN (SELECT id FROM reports WHERE carer_id = ? AND deleted_at IS NULL)")->execute([now(), $reportId, $user['carer_id']]);
            $stmt = $pdo->prepare('UPDATE reports SET read_by_carer_at = COALESCE(read_by_carer_at, ?), updated_at = ? WHERE id = ? AND carer_id = ? AND sender_role != ?');
            $stmt->execute([now(), now(), $reportId, (int) $user['carer_id'], 'CARER']);
        } else {
            require_role(['ADMIN', 'EDITOR']);
            $stmt = $pdo->prepare('UPDATE reports SET read_by_admin_at = COALESCE(read_by_admin_at, ?), updated_at = ? WHERE id = ? AND sender_role = ?');
            $stmt->execute([now(), now(), $reportId, 'CARER']);
        }
        $pdo->commit();
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

        $stmt = $pdo->prepare('SELECT * FROM reports WHERE id = ? AND deleted_at IS NULL');
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
        $pdo->beginTransaction();
        $stmt->execute([$reportId, (int) $user['id'], $senderRole, $body, $now]);
        $pdo->prepare('UPDATE reports SET updated_at = ? WHERE id = ?')->execute([$now, $reportId]);
        audit_event($pdo, $action, $reportId);
        $pdo->commit();
        $_SESSION['flash'] = 'Resposta adicionada ao histórico do relatório.';
        redirect('reports');
    }
    if ($action === 'delete_report') {
        $user = require_auth();
        $pdo->beginTransaction();
        $reportId = (int) ($_POST['id'] ?? 0);
        if ($user['role'] === 'CARER') {
            $stmt = $pdo->prepare('UPDATE reports SET deleted_at = ? WHERE id = ? AND sender_user_id = ?');
            $stmt->execute([now(), $reportId, (int) $user['id']]);
        } else {
            require_role(['ADMIN', 'EDITOR']);
            $stmt = $pdo->prepare('UPDATE reports SET deleted_at = ? WHERE id = ?');
            $stmt->execute([now(), $reportId]);
        }
        if ($stmt->rowCount() > 0) audit_event($pdo, $action, $reportId);
        $pdo->commit();
        $_SESSION['flash'] = 'Relatório arquivado.';
        redirect('reports');
    }
    if ($action === 'save_user') {
        require_role(['ADMIN']);
        $id = (int) ($_POST['id'] ?? 0);
        $role = $_POST['role'];
        $carerId = ($_POST['carer_id'] ?? '') !== '' ? (int) $_POST['carer_id'] : null;
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
        audit_event($pdo, $action, $id ?: (int) $pdo->lastInsertId());
        $pdo->commit();
        redirect('users');
    }

    if ($action === 'delete_user') {
        $user = require_role(['ADMIN']);
        $id = (int) $_POST['id'];
        if ($id !== (int) $user['id']) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            audit_event($pdo, $action, $id);
        }
        $pdo->commit();
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

