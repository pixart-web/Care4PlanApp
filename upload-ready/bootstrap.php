<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
session_start();
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/scheduling.php';
set_exception_handler('application_exception');
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    header('Cache-Control: no-store');
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (! is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    migrate($pdo);
    seed($pdo);

    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('ADMIN', 'EDITOR', 'CARER')),
            carer_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS carers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            name TEXT NOT NULL,
            age INTEGER NULL,
            phone TEXT NOT NULL,
            email TEXT NULL,
            color_hex TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            age INTEGER NULL,
            address TEXT NOT NULL,
            observations TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );


        CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            sender_user_id INTEGER NOT NULL,
            sender_role TEXT NOT NULL CHECK(sender_role IN ('ADMIN', 'EDITOR', 'CARER')),
            carer_id INTEGER NULL,
            patient_id INTEGER NULL,
            read_by_admin_at TEXT NULL,
            read_by_carer_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(carer_id) REFERENCES carers(id) ON DELETE SET NULL,
            FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE SET NULL
        );
        CREATE TABLE IF NOT EXISTS report_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_id INTEGER NOT NULL,
            sender_user_id INTEGER NOT NULL,
            sender_role TEXT NOT NULL CHECK(sender_role IN ('ADMIN', 'EDITOR', 'CARER')),
            body TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(report_id) REFERENCES reports(id) ON DELETE CASCADE,
            FOREIGN KEY(sender_user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS entity_movements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entity_type TEXT NOT NULL CHECK(entity_type IN ('PATIENT', 'CARER')),
            entity_name TEXT NOT NULL,
            direction TEXT NOT NULL CHECK(direction IN ('IN', 'OUT')),
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            carer_id INTEGER NOT NULL,
            starts_at TEXT NOT NULL,
            ends_at TEXT NOT NULL,
            hourly_rate REAL NOT NULL,
            status TEXT NOT NULL DEFAULT 'SCHEDULED' CHECK(status IN ('SCHEDULED', 'DONE', 'CANCELLED')),
            done_at TEXT NULL,
            done_by_user_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(carer_id, starts_at),
            FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY(carer_id) REFERENCES carers(id) ON DELETE CASCADE,
            FOREIGN KEY(done_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        );
    ");

    ensure_column($pdo, 'patients', 'observations', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'birth_date', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'tax_number', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'sns_number', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'postal_code', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'city', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'phone', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'email', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'emergency_contact_name', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'emergency_contact_relationship', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'emergency_contact_phone', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'emergency_contact_email', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'doctor_name', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'health_unit', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'diagnoses', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'allergies', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'medication', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'medication_schedule', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'mobility', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'cognition', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'fall_risk', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'incontinence', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'food_restrictions', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'care_needs', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'daily_routine', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'preferences', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'avoid', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'special_instructions', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'home_access', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'pets', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'equipment', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'status', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'support_start_date', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'contracted_hours', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'client_hourly_rate', 'REAL NULL');
    ensure_column($pdo, 'patients', 'service_type', 'TEXT NULL');
    ensure_column($pdo, 'patients', 'admin_notes', 'TEXT NULL');
    ensure_column($pdo, 'carers', 'pocket_adjustment', 'REAL NOT NULL DEFAULT 0');
    ensure_column($pdo, 'services', 'observations', 'TEXT NULL');
    ensure_column($pdo, 'services', 'municipality', 'TEXT NULL');
    ensure_column($pdo, 'report_replies', 'read_by_carer_at', 'TEXT NULL');
    migrate_v2($pdo);
    scheduling_migrate($pdo);
}


function sync_carer_usernames(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT users.id AS user_id, carers.name AS carer_name FROM users JOIN carers ON carers.id = users.carer_id');
    $update = $pdo->prepare('UPDATE users SET name = ?, username = ?, updated_at = ? WHERE id = ?');
    $conflict = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');

    foreach ($stmt->fetchAll() as $row) {
        $name = trim((string) $row['carer_name']);
        if ($name === '') {
            continue;
        }
        $conflict->execute([$name, (int) $row['user_id']]);
        if ((int) $conflict->fetchColumn() === 0) {
            $update->execute([$name, $name, now(), (int) $row['user_id']]);
        }
    }
}
function record_entity_movement(PDO $pdo, string $entityType, string $entityName, string $direction): void
{
    $stmt = $pdo->prepare('INSERT INTO entity_movements (entity_type, entity_name, direction, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$entityType, $entityName, $direction, now()]);
}
function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
    foreach ($columns as $existing) {
        if ($existing['name'] === $column) {
            return;
        }
    }

    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}

function seed(PDO $pdo): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
            if (!valid_new_password(INITIAL_ADMIN_PASSWORD)) {
                throw new RuntimeException('Initial setup requires a configured password.');
            }
            $insert = $pdo->prepare('INSERT INTO users (name, username, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
            foreach (INITIAL_ADMINS as $admin) {
                $insert->execute([$admin, $admin, password_hash(INITIAL_ADMIN_PASSWORD, PASSWORD_DEFAULT), 'ADMIN', now(), now()]);
            }
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (empty($_SESSION['_csrf']) || !is_string($_POST['_csrf'] ?? null) || !hash_equals($_SESSION['_csrf'], $_POST['_csrf']))) {
        http_response_code(419);
        exit('Sessao expirada. Volte atras e tente novamente.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_auth(): array
{
    $user = current_user();
    if (! $user) {
        redirect('login');
    }

    return $user;
}

function require_role(array $roles): array
{
    $user = require_auth();
    if (! in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    return $user;
}

function redirect(string $page): never
{
    header('Location: index.php?page=' . urlencode($page));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(float|int|string $value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' EUR';
}

function date_input(string $value): string
{
    return str_replace(' ', 'T', substr($value, 0, 16));
}

function generate_unique_color(): string
{
    $count = (int) db()->query('SELECT COUNT(*) FROM carers')->fetchColumn();
    $goldenAngle = 137.508;

    for ($attempt = 0; $attempt < 720; $attempt++) {
        $hue = fmod(($count + $attempt) * $goldenAngle, 360);
        $color = hsl_to_hex($hue, 68, 46);
        $stmt = db()->prepare('SELECT COUNT(*) FROM carers WHERE color_hex = ?');
        $stmt->execute([$color]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $color;
        }
    }

    throw new RuntimeException('Nao foi possivel gerar uma cor unica.');
}

function hsl_to_hex(float $h, float $s, float $l): string
{
    $s /= 100;
    $l /= 100;
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;

    if ($h < 60) {
        [$r, $g, $b] = [$c, $x, 0];
    } elseif ($h < 120) {
        [$r, $g, $b] = [$x, $c, 0];
    } elseif ($h < 180) {
        [$r, $g, $b] = [0, $c, $x];
    } elseif ($h < 240) {
        [$r, $g, $b] = [0, $x, $c];
    } elseif ($h < 300) {
        [$r, $g, $b] = [$x, 0, $c];
    } else {
        [$r, $g, $b] = [$c, 0, $x];
    }

    return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
}

function carer_status(int $carerId): string
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM services WHERE carer_id = ? AND status != 'CANCELLED' AND starts_at <= ? AND ends_at > ?");
    $stmt->execute([$carerId, now(), now()]);
    return (int) $stmt->fetchColumn() > 0 ? 'Em serviço' : 'Livre';
}

function pocket_for_carer(int $carerId): float
{
    $start = date('Y-m-01 00:00:00');
    $end = date('Y-m-t 23:59:59');
    $availableAt = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $stmt = db()->prepare("SELECT COALESCE(SUM(hourly_rate * ((julianday(ends_at) - julianday(starts_at)) * 24)), 0) FROM services WHERE carer_id = ? AND status != 'CANCELLED' AND starts_at BETWEEN ? AND ? AND ends_at <= ?");
    $stmt->execute([$carerId, $start, $end, $availableAt]);
    $servicesTotal = (float) $stmt->fetchColumn();

    $adjustment = db()->prepare('SELECT COALESCE(pocket_adjustment, 0) FROM carers WHERE id = ?');
    $adjustment->execute([$carerId]);

    return $servicesTotal + (float) $adjustment->fetchColumn();
}

function service_pocket_value(array $service): float
{
    $hours = max(0, (strtotime($service['ends_at']) - strtotime($service['starts_at'])) / 3600);
    return round(((float) $service['hourly_rate']) * $hours, 2);
}

function has_overlap(int $carerId, string $start, string $end, ?int $ignoreId = null): bool
{
    $sql = "SELECT COUNT(*) FROM services WHERE carer_id = ? AND status != 'CANCELLED' AND starts_at < ? AND ends_at > ?";
    $params = [$carerId, $end, $start];
    if ($ignoreId) {
        $sql .= ' AND id != ?';
        $params[] = $ignoreId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() > 0;
}

function layout(string $title, callable $content): void
{
    $user = current_user();
    require __DIR__ . '/views/layout.php';
}
