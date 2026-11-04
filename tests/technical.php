<?php
declare(strict_types=1);

test('Rate limit expired boundary starts a new fixed window', function () use ($pdo): void {
    $at = time() + 3600; // Deterministic clock: no sleep or second-boundary race.
    $bucket = login_bucket('boundary-fixture');
    $pdo->prepare('INSERT OR REPLACE INTO login_attempts VALUES (?, 5, ?)')->execute([$bucket, $at - 900]);
    try {
        login_failed($pdo, 'boundary-fixture', $at);
        $row = $pdo->query("SELECT attempts, window_started FROM login_attempts WHERE bucket='$bucket'")->fetch();
        same(1, (int) $row['attempts']); same($at, (int) $row['window_started']);
        login_failed($pdo, 'boundary-fixture', $at + 1);
        $row = $pdo->query("SELECT attempts, window_started FROM login_attempts WHERE bucket='$bucket'")->fetch();
        same(2, (int) $row['attempts']); same($at, (int) $row['window_started']);
        check(!login_limited($pdo, 'boundary-fixture', $at + 899));
    } finally { $pdo->exec('DELETE FROM login_attempts'); }
});
test('SQLite integrity and indexed calendar lookup', function () use ($pdo,$carer): void {
    same('ok', $pdo->query('PRAGMA integrity_check')->fetchColumn());
    same([], $pdo->query('PRAGMA foreign_key_check')->fetchAll());
    $plan = $pdo->query('EXPLAIN QUERY PLAN SELECT id FROM services WHERE carer_id='.(int)$carer." AND starts_at < '2027-01-01' AND ends_at > '2026-01-01'")->fetchAll();
    check(str_contains(json_encode($plan), 'INDEX'), 'Calendar lookup must use an index');
});
test('Existing installation seed does not compete for a writer lock', function () use ($pdo): void {
    $other = new PDO('sqlite:' . DB_FILE);
    $other->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $other->exec('BEGIN IMMEDIATE');
    $pdo->exec('PRAGMA busy_timeout = 50');
    try { seed($pdo); }
    finally { $other->exec('ROLLBACK'); $pdo->exec('PRAGMA busy_timeout = 5000'); }
});
test('Current schema migration checks do not compete for a writer lock', function () use ($pdo): void {
    $other = new PDO('sqlite:' . DB_FILE);
    $other->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $other->exec('BEGIN IMMEDIATE');
    $pdo->exec('PRAGMA busy_timeout = 50');
    try { migrate($pdo); }
    finally { $other->exec('ROLLBACK'); $pdo->exec('PRAGMA busy_timeout = 5000'); }
});
