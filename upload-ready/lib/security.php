<?php
declare(strict_types=1);

function valid_new_password(string $password): bool
{
    return strlen($password) >= 12 && strlen($password) <= 1024 && $password !== 'CHANGE_ME_ON_SERVER';
}

function application_exception(Throwable $error): void
{
    $reference = bin2hex(random_bytes(6));
    // Do not log SQL, form values, clinical information or credentials.
    error_log('Care4All error ' . $reference . ' ' . get_class($error));
    http_response_code(500);
    echo 'Não foi possível concluir a operação. Referência: ' . $reference;
}

function migrate_v2(PDO $pdo): void
{
    ensure_column($pdo, 'reports', 'deleted_at', 'TEXT NULL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS login_attempts (bucket TEXT PRIMARY KEY, attempts INTEGER NOT NULL, window_started INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS audit_events (id INTEGER PRIMARY KEY, user_id INTEGER, action TEXT NOT NULL, entity_id INTEGER, created_at TEXT NOT NULL);
        CREATE INDEX IF NOT EXISTS idx_services_calendar ON services(starts_at, ends_at);
        CREATE INDEX IF NOT EXISTS idx_services_carer_time ON services(carer_id, starts_at, ends_at);
        CREATE INDEX IF NOT EXISTS idx_services_municipality_time ON services(municipality, starts_at);
        CREATE INDEX IF NOT EXISTS idx_reports_carer ON reports(carer_id, updated_at);");
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS preserve_patient_history BEFORE DELETE ON patients
        WHEN EXISTS(SELECT 1 FROM services WHERE patient_id = OLD.id) OR EXISTS(SELECT 1 FROM reports WHERE patient_id = OLD.id)
        BEGIN SELECT RAISE(ABORT, 'Associated history must be preserved'); END;
        CREATE TRIGGER IF NOT EXISTS preserve_carer_history BEFORE DELETE ON carers
        WHEN EXISTS(SELECT 1 FROM services WHERE carer_id = OLD.id) OR EXISTS(SELECT 1 FROM reports WHERE carer_id = OLD.id)
        BEGIN SELECT RAISE(ABORT, 'Associated history must be preserved'); END;
        CREATE TRIGGER IF NOT EXISTS preserve_user_history BEFORE DELETE ON users
        WHEN EXISTS(SELECT 1 FROM reports WHERE sender_user_id = OLD.id) OR EXISTS(SELECT 1 FROM report_replies WHERE sender_user_id = OLD.id)
        BEGIN SELECT RAISE(ABORT, 'Associated history must be preserved'); END;");
    $pdo->prepare('INSERT OR IGNORE INTO schema_migrations VALUES (?, ?)')->execute(['v2-security', now()]);
}

function audit_event(PDO $pdo, string $action, ?int $entityId = null): void
{
    $pdo->prepare('INSERT INTO audit_events(user_id, action, entity_id, created_at) VALUES(?, ?, ?, ?)')
        ->execute([$_SESSION['user_id'] ?? null, $action, $entityId, now()]);
}

function login_bucket(string $login): string
{
    return hash('sha256', strtolower($login) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
}

function login_limited(PDO $pdo, string $login): bool
{
    $stmt = $pdo->prepare('SELECT attempts FROM login_attempts WHERE bucket = ? AND window_started > ?');
    $stmt->execute([login_bucket($login), time() - 900]);
    return (int) $stmt->fetchColumn() >= 5;
}

function login_failed(PDO $pdo, string $login): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE window_started < ?')->execute([time() - 900]);
    foreach ([login_bucket($login), hash('sha256', 'ip|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'))] as $bucket) {
        $pdo->prepare('INSERT INTO login_attempts VALUES(?, 1, ?) ON CONFLICT(bucket) DO UPDATE SET attempts = attempts + 1')->execute([$bucket, time()]);
    }
}

function may_report_patient(PDO $pdo, array $user, int $patientId): bool
{
    if ($user['role'] !== 'CARER') return true;
    $stmt = $pdo->prepare("SELECT 1 FROM services WHERE patient_id = ? AND carer_id = ? AND status != 'CANCELLED' LIMIT 1");
    $stmt->execute([$patientId, $user['carer_id']]);
    return (bool) $stmt->fetchColumn();
}

function reject_action(string $message, string $page): never
{
    $_SESSION['flash_error'] = $message;
    redirect($page);
}

function validate_action(PDO $pdo, string $action, array $user): void
{
    $pages = ['save_user' => 'users', 'save_carer' => 'carers', 'save_patient' => 'patients', 'save_profile' => 'profile'];
    $page = $pages[$action] ?? 'dashboard';
    if (isset($pages[$action])) {
        foreach ($_POST as $value) {
            if (is_string($value) && strlen($value) > 20000) reject_action('Um dos campos excede o tamanho permitido.', $page);
        }
        if (in_array($action, ['save_user', 'save_carer'], true)) {
            $password = (string) ($_POST['password'] ?? '');
            if (($password !== '' || empty($_POST['id'])) && !valid_new_password($password)) {
                reject_action('A palavra-passe deve ter entre 12 e 1024 caracteres.', $page);
            }
        }
        if ($action === 'save_carer' && !preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($_POST['color_hex'] ?? ''))) {
            reject_action('Selecione uma cor válida.', $page);
        }
        if ($action === 'save_carer') {
            $stmt = $pdo->prepare('SELECT 1 FROM carers WHERE id != ? AND (color_hex = ? OR (email != ? AND lower(email) = lower(?)))');
            $stmt->execute([(int) ($_POST['id'] ?? 0), $_POST['color_hex'], '', trim((string) ($_POST['email'] ?? ''))]);
            if ($stmt->fetchColumn()) reject_action('A cor ou o email já estão associados a outra cuidadora.', 'carers');
            if (isset($_POST['pocket_adjustment']) && !is_numeric($_POST['pocket_adjustment'])) reject_action('Introduza um ajuste Pocket válido.', 'carers');
        }
        if (isset($_POST['age']) && $_POST['age'] !== '' && (!ctype_digit((string) $_POST['age']) || (int) $_POST['age'] > 125)) {
            reject_action('Introduza uma idade válida.', $page);
        }
        if (isset($_POST['email']) && $_POST['email'] !== '' && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            reject_action('Introduza um endereço de email válido.', $page);
        }
        if (isset($_POST['client_hourly_rate']) && $_POST['client_hourly_rate'] !== '' && (!is_numeric($_POST['client_hourly_rate']) || (float) $_POST['client_hourly_rate'] < 0)) {
            reject_action('Introduza um valor horário válido.', $page);
        }
    }
    if ($action === 'save_user') {
        require_role(['ADMIN']);
        if (!in_array($_POST['role'] ?? '', ['ADMIN', 'EDITOR', 'CARER'], true) || trim((string) ($_POST['username'] ?? '')) === '' || trim((string) ($_POST['name'] ?? '')) === '') {
            reject_action('Confirme nome, utilizador e função.', 'users');
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id && $_POST['role'] !== 'ADMIN') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'ADMIN' AND id != ?");
            $stmt->execute([$id]);
            if (!$stmt->fetchColumn()) reject_action('É necessário manter pelo menos um administrador.', 'users');
        }
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? AND id != ?');
        $stmt->execute([trim($_POST['username']), $id]);
        if ($stmt->fetchColumn()) reject_action('Esse nome de utilizador já existe.', 'users');
        $carer = (int) ($_POST['carer_id'] ?? 0);
        if ($_POST['role'] === 'CARER') {
            $stmt = $pdo->prepare('SELECT 1 FROM carers WHERE id = ?');
            $stmt->execute([$carer]);
            if (!$stmt->fetchColumn()) reject_action('Associe uma cuidadora válida.', 'users');
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE carer_id = ? AND id != ?');
            $stmt->execute([$carer, $id]);
            if ($stmt->fetchColumn()) reject_action('A cuidadora já tem uma conta.', 'users');
        } else { $_POST['carer_id'] = ''; }
    }
    if (str_starts_with($action, 'delete_')) {
        $id = (int) ($_POST['id'] ?? 0);
        $checks = [
            'delete_patient' => ['patients', 'services WHERE patient_id = ?', 'reports WHERE patient_id = ?'],
            'delete_carer' => ['carers', 'services WHERE carer_id = ?', 'reports WHERE carer_id = ?'],
            'delete_user' => ['users', 'reports WHERE sender_user_id = ?', 'report_replies WHERE sender_user_id = ?'],
        ];
        if ($action !== 'delete_report') require_role($action === 'delete_user' ? ['ADMIN'] : ['ADMIN', 'EDITOR']);
        if (isset($checks[$action])) {
            [$table, $first, $second] = $checks[$action];
            $exists = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE id = ?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) reject_action('O registo já não existe.', $table);
            foreach ([$first, $second] as $query) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $query);
                $stmt->execute([$id]);
                if ($stmt->fetchColumn()) reject_action('Este registo tem histórico associado e não pode ser apagado.', $table);
            }
        }
        if ($action === 'delete_service') {
            reject_action('Para preservar o histórico, cancele a escala em vez de a apagar.', 'scales');
        }
    }
}
