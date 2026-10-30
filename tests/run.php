<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
if (is_file($root . '/upload-ready/config-local.php')) {
    fwrite(STDERR, "Refusing to run with config-local.php: test only in an isolated checkout.\n");
    exit(2);
}
$temp = sys_get_temp_dir() . '/care4all-test-' . bin2hex(random_bytes(10));
mkdir($temp, 0700, true);
$password = getenv('CARE4ALL_TEST_PASSWORD') ?: bin2hex(random_bytes(24));
if (strlen($password) < 12) { fwrite(STDERR, "CARE4ALL_TEST_PASSWORD must have >=12 characters.\n"); exit(2); }
putenv('CARE4ALL_TEST_PASSWORD=' . $password);
putenv('CARE4ALL_INITIAL_ADMIN_PASSWORD=' . $password);
putenv('CARE4ALL_DB_FILE=' . $temp . '/fixture.sqlite');
ini_set('session.save_path', $temp);
require $root . '/upload-ready/bootstrap.php';
require_once $root . '/upload-ready/lib/scheduling.php';
if (DB_FILE !== $temp . '/fixture.sqlite') { throw new RuntimeException('Unsafe DB configuration.'); }
set_exception_handler(function (Throwable $error): void { fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n"); exit(1); });
$passed = 0; $failed = 0;
function check(bool $condition, string $message = 'Assertion failed'): void { if (!$condition) throw new RuntimeException($message); }
function same(mixed $expected, mixed $actual): void { check($expected === $actual, 'Expected ' . json_encode($expected) . ', got ' . json_encode($actual)); }
function test(string $name, callable $body): void {
    global $passed, $failed;
    try { $body(); $passed++; echo "PASS $name\n"; }
    catch (Throwable $error) { $failed++; echo "FAIL $name: " . $error->getMessage() . "\n"; }
}
function rejects(callable $body): void {
    try { $body(); } catch (Throwable $error) { return; }
    throw new RuntimeException('Expected rejection.');
}
function snapshot(PDO $pdo): array {
    $result = [];
    foreach (['users','patients','carers','services','reports','report_replies','entity_movements'] as $table) {
        $result[$table] = $pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll();
    }
    return $result;
}
$pdo = db();
test('legacy fictional schema migrates without replacing rows or credentials', function (): void {
    $legacy=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $legacy->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,username TEXT UNIQUE,password TEXT,role TEXT,carer_id INTEGER,created_at TEXT,updated_at TEXT);
        CREATE TABLE carers(id INTEGER PRIMARY KEY,user_id INTEGER,name TEXT,age INTEGER,phone TEXT,email TEXT,color_hex TEXT,created_at TEXT,updated_at TEXT);
        CREATE TABLE patients(id INTEGER PRIMARY KEY,name TEXT,age INTEGER,address TEXT,created_at TEXT,updated_at TEXT);
        CREATE TABLE services(id INTEGER PRIMARY KEY,patient_id INTEGER,carer_id INTEGER,starts_at TEXT,ends_at TEXT,hourly_rate REAL,status TEXT,done_at TEXT,done_by_user_id INTEGER,created_at TEXT,updated_at TEXT);
        INSERT INTO patients VALUES(17,'Legacy fixture',80,'Fictional', '2020-01-01','2020-01-01');
        INSERT INTO carers VALUES(23,NULL,'Legacy carer',NULL,'000000000',NULL,'#112233','2020-01-01','2020-01-01');
        INSERT INTO services VALUES(41,17,23,'2020-01-02 09:00:00','2020-01-02 10:00:00',10,'DONE','2020-01-02 10:00:00',NULL,'2020-01-01','2020-01-01');");
    $hash=password_hash(getenv('CARE4ALL_TEST_PASSWORD'),PASSWORD_DEFAULT);
    $legacy->prepare('INSERT INTO users VALUES(7,?,?,?,?,?,?,?)')->execute(['Legacy editor','legacy-editor',$hash,'EDITOR',null,'2020-01-01','2020-01-01']);
    migrate($legacy); seed($legacy); $before=snapshot($legacy);
    migrate($legacy); seed($legacy); same($before,snapshot($legacy));
    same(1,(int)$legacy->query('SELECT COUNT(*) FROM users')->fetchColumn());
    same($hash,$legacy->query('SELECT password FROM users WHERE id=7')->fetchColumn());
    same(null,$legacy->query('SELECT municipality FROM services WHERE id=41')->fetchColumn());
    same('DONE',$legacy->query('SELECT status FROM services WHERE id=41')->fetchColumn());
    same('Legacy fixture',$legacy->query('SELECT name FROM patients WHERE id=17')->fetchColumn());
    same('ok',$legacy->query('PRAGMA integrity_check')->fetchColumn());
});
test('seed creates only initial admins', function () use ($pdo, $password): void {
    same(3, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    foreach ($pdo->query('SELECT password FROM users') as $row) check(password_verify($password, $row['password']));
});
$timestamp = now();
$pdo->prepare('INSERT INTO patients(name,address,client_hourly_rate,created_at,updated_at) VALUES(?,?,?,?,?)')->execute(['Fixture Patient A','Fictional address',20,$timestamp,$timestamp]);
$patient = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO patients(name,address,created_at,updated_at) VALUES(?,?,?,?)')->execute(['Fixture Patient B','Fictional address',$timestamp,$timestamp]);
$otherPatient = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO carers(name,phone,email,color_hex,pocket_adjustment,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute(['Fixture Carer','000000000','fixture@example.invalid','#123456',3.5,$timestamp,$timestamp]);
$carer = (int) $pdo->lastInsertId();
$insertUser = $pdo->prepare('INSERT INTO users(name,username,password,role,carer_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?)');
$insertUser->execute(['Fixture Carer','fixture-carer',password_hash($password,PASSWORD_DEFAULT),'CARER',$carer,$timestamp,$timestamp]);
$carerUser = (int) $pdo->lastInsertId();
$insertUser->execute(['Fixture Editor','fixture-editor',password_hash($password,PASSWORD_DEFAULT),'EDITOR',null,$timestamp,$timestamp]);
$editorUser = (int) $pdo->lastInsertId();
$insertService = $pdo->prepare('INSERT INTO services(patient_id,carer_id,starts_at,ends_at,hourly_rate,status,municipality,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
$insertService->execute([$patient,$carer,'2026-11-02 09:00:00','2026-11-02 10:00:00',10,'SCHEDULED','Lagos',$timestamp,$timestamp]);
$service = (int) $pdo->lastInsertId();
test('changed hashes and all fixture rows survive repeated migration/seed', function () use ($pdo): void {
    $pdo->prepare('UPDATE users SET password = ? WHERE id = 1')->execute([password_hash(getenv('CARE4ALL_TEST_PASSWORD') . '-changed', PASSWORD_DEFAULT)]);
    $before = snapshot($pdo);
    $schema = $pdo->query("SELECT type,name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name")->fetchAll();
    for ($i=0; $i<3; $i++) { migrate($pdo); seed($pdo); }
    same($before, snapshot($pdo));
    same($schema, $pdo->query("SELECT type,name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name")->fetchAll());
    same('ok', $pdo->query('PRAGMA integrity_check')->fetchColumn());
    same([], $pdo->query('PRAGMA foreign_key_check')->fetchAll());
});
test('password policy, escaping and CSRF token helpers', function (): void {
    check(!valid_new_password(str_repeat('a',11)));
    check(valid_new_password(getenv('CARE4ALL_TEST_PASSWORD')));
    check(!valid_new_password('CHANGE_ME_ON_SERVER'));
    check(!valid_new_password(str_repeat('a',1025)));
    same('&lt;script&gt;&quot;&#039;&amp;', e('<script>"\'&'));
    same(64, strlen(csrf_token())); same(csrf_token(), csrf_token());
});
test('patient report authorization helper', function () use ($pdo,$patient,$otherPatient,$carer): void {
    check(may_report_patient($pdo,['role'=>'CARER','carer_id'=>$carer],$patient));
    check(!may_report_patient($pdo,['role'=>'CARER','carer_id'=>$carer],$otherPatient));
    check(may_report_patient($pdo,['role'=>'ADMIN'],$otherPatient));
    check(may_report_patient($pdo,['role'=>'EDITOR'],$otherPatient));
});
test('overlap ignores municipality, allows adjacent times and own edit', function () use ($carer,$service): void {
    check(has_overlap($carer,'2026-11-02 09:30:00','2026-11-02 10:30:00'));
    check(!has_overlap($carer,'2026-11-02 10:00:00','2026-11-02 11:00:00'));
    check(!has_overlap($carer,'2026-11-02 09:00:00','2026-11-02 10:00:00',$service));
});
test('existing financial duration rules', function (): void {
    same(15.0, service_pocket_value(['starts_at'=>'2026-11-02 09:00:00','ends_at'=>'2026-11-02 10:30:00','hourly_rate'=>10]));
    same(0.0, service_pocket_value(['starts_at'=>'2026-11-02 10:00:00','ends_at'=>'2026-11-02 09:00:00','hourly_rate'=>10]));
    same(10.0, service_pocket_value(['starts_at'=>'2026-03-29 00:30:00','ends_at'=>'2026-03-29 02:30:00','hourly_rate'=>10]));
    same(30.0, service_pocket_value(['starts_at'=>'2026-10-25 00:30:00','ends_at'=>'2026-10-25 02:30:00','hourly_rate'=>10]));
});
test('pocket includes scheduled ended services, excludes cancelled/future/outside month', function () use ($pdo,$carer,$patient,$insertService,$timestamp): void {
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM services');
        // Use a past day in this month, or the previous hours on day one.
        $end = new DateTimeImmutable('today');
        if ((int)date('j') === 1) $end = new DateTimeImmutable('-30 minutes');
        if ($end->modify('-1 hour')->format('Y-m') !== date('Y-m')) throw new RuntimeException('Run after first hour of month for deterministic pocket boundary.');
        $start = $end->modify('-1 hour');
        $insertService->execute([$patient,$carer,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),10,'SCHEDULED','Lagos',$timestamp,$timestamp]);
        $insertService->execute([$patient,$carer,date('Y-m-d 00:00:00',strtotime('+1 month')),date('Y-m-d 01:00:00',strtotime('+1 month')),10,'DONE','Lagos',$timestamp,$timestamp]);
        $insertService->execute([$patient,$carer,date('Y-m-d H:i:s',strtotime('+2 hours')),date('Y-m-d H:i:s',strtotime('+3 hours')),10,'SCHEDULED','Lagos',$timestamp,$timestamp]);
        $insertService->execute([$patient,$carer,$start->modify('-2 hours')->format('Y-m-d H:i:s'),$start->modify('-1 hour')->format('Y-m-d H:i:s'),10,'CANCELLED','Lagos',$timestamp,$timestamp]);
        check(abs(pocket_for_carer($carer)-13.5)<0.001);
    } finally { $pdo->rollBack(); }
});
test('actual finances view preserves planned income cost and margin rules', function () use ($pdo,$carer,$patient,$insertService,$timestamp,$root): void {
    $pdo->beginTransaction(); $oldSession=$_SESSION;
    try {
        $pdo->exec('DELETE FROM services');
        $date=date('Y-m-05');
        $insertService->execute([$patient,$carer,$date.' 09:00:00',$date.' 11:00:00',10,'SCHEDULED','Lagos',$timestamp,$timestamp]);
        $insertService->execute([$patient,$carer,$date.' 12:00:00',$date.' 13:00:00',10,'DONE','Lagos',$timestamp,$timestamp]);
        $insertService->execute([$patient,$carer,$date.' 14:00:00',$date.' 15:00:00',10,'CANCELLED','Lagos',$timestamp,$timestamp]);
        $_SESSION['user_id']=1;
        ob_start();
        try { require $root.'/upload-ready/views/finances.php'; } finally { ob_end_clean(); }
        $row=array_values(array_filter($rows,fn($row)=>(int)$row['id']===$patient))[0];
        same(3.0,$row['hours']); same(60.0,$row['income']); same(30.0,$row['cost']); same(30.0,$row['margin']); same(50.0,$row['margin_percent']);
    } finally { $_SESSION=$oldSession; $pdo->rollBack(); }
});
$base = ['id'=>'','patient_id'=>$patient,'carer_id'=>$carer,'municipality'=>'Silves','starts_at'=>'2026-11-09T09:00','ends_at'=>'2026-11-09T10:00','hourly_rate'=>10,'observations'=>'Fixture only'];
test('recurrence weekdays and multiweek preview never writes', function () use ($pdo,$base): void {
    $before = snapshot($pdo);
    $data = $base + ['recurrence_mode'=>'weekdays','recurrence_until'=>'2026-11-13','recurrence_weekdays'=>[1,2,3,4,5]];
    $preview = scheduling_preview($pdo,$data); check($preview['valid']); same(5,$preview['count']);
    $data['recurrence_until']='2026-11-27'; same(15,scheduling_preview($pdo,$data)['count']);
    same($before,snapshot($pdo));
});
test('recurrence rejects DST gaps and ambiguity; wall time stable across DST', function () use ($pdo,$base): void {
    foreach (['2026-03-29T01:30','2026-10-25T01:30','2026-02-30T09:00'] as $time) rejects(fn()=>scheduling_local_time($time));
    $data = array_replace($base,['starts_at'=>'2026-03-27T09:00','ends_at'=>'2026-03-27T10:00','recurrence_mode'=>'dates','recurrence_dates'=>['2026-03-30']]);
    $plan = scheduling_plan($pdo,$data); same('2026-03-30 09:00:00',$plan['occurrences'][1]['starts_at']);
});
test('recurrence limits and cross-municipality conflicts', function () use ($pdo,$base): void {
    $conflict = array_replace($base,['starts_at'=>'2026-11-02T09:30','ends_at'=>'2026-11-02T10:30']);
    check(!scheduling_preview($pdo,$conflict)['valid']);
    $long = $base + ['recurrence_mode'=>'weekdays','recurrence_until'=>'2027-11-09','recurrence_weekdays'=>[1,2,3,4,5,6,7]];
    check(!scheduling_preview($pdo,$long)['valid']);
});
test('recurrence commit, replay and mismatched payload', function () use ($pdo,$base): void {
    $data = $base + ['idempotency_key'=>bin2hex(random_bytes(16)),'recurrence_mode'=>'weekdays','recurrence_until'=>'2026-11-13','recurrence_weekdays'=>[1,2,3,4,5]];
    $result = scheduling_commit($pdo,$data); check($result['valid']); same(5,count($result['service_ids']));
    $before = snapshot($pdo); check(scheduling_commit($pdo,$data)['replayed']); same($before,snapshot($pdo));
    rejects(fn()=>scheduling_commit($pdo,array_replace($data,['hourly_rate'=>11])));
    same($before,snapshot($pdo));
});
test('recurrence rollback after injected second insertion failure', function () use ($pdo,$base): void {
    $pdo->exec("CREATE TRIGGER fixture_fail BEFORE INSERT ON services WHEN NEW.starts_at = '2026-12-02 09:00:00' BEGIN SELECT RAISE(ABORT,'fixture failure'); END");
    try {
        $before = snapshot($pdo);
        $batches = (int)$pdo->query('SELECT COUNT(*) FROM service_batches')->fetchColumn();
        $data = array_replace($base,['starts_at'=>'2026-12-01T09:00','ends_at'=>'2026-12-01T10:00','recurrence_mode'=>'dates','recurrence_dates'=>['2026-12-02'],'idempotency_key'=>bin2hex(random_bytes(16))]);
        rejects(fn()=>scheduling_commit($pdo,$data)); same($before,snapshot($pdo));
        same($batches,(int)$pdo->query('SELECT COUNT(*) FROM service_batches')->fetchColumn());
        check(!$pdo->inTransaction());
    } finally { $pdo->exec('DROP TRIGGER fixture_fail'); }
});
test('history triggers reject direct cascade deletion without losing rows', function () use ($pdo,$patient,$carer,$carerUser,$timestamp): void {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO reports(title,body,sender_user_id,sender_role,carer_id,patient_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)')->execute(['History fixture','Synthetic',$carerUser,'CARER',$carer,$patient,$timestamp,$timestamp]);
        $report=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO report_replies(report_id,sender_user_id,sender_role,body,created_at) VALUES(?,?,?,?,?)')->execute([$report,$carerUser,'CARER','Synthetic',$timestamp]);
        $before=snapshot($pdo);
        foreach (['patients'=>$patient,'carers'=>$carer,'users'=>$carerUser] as $table=>$id) {
            rejects(fn()=>$pdo->exec('DELETE FROM '.$table.' WHERE id='.$id));
            same($before,snapshot($pdo));
        }
    } finally { $pdo->rollBack(); }
});
test('skip_conflicts creates only eligible occurrences and replays safely', function () use ($pdo,$base,$service): void {
    $before=$pdo->query('SELECT * FROM services WHERE id='.$service)->fetch();
    $data=array_replace($base,['starts_at'=>'2026-11-02T09:00','ends_at'=>'2026-11-02T10:00','recurrence_mode'=>'dates','recurrence_dates'=>['2026-11-03'],'skip_conflicts'=>'1','idempotency_key'=>bin2hex(random_bytes(16))]);
    $preview=scheduling_preview($pdo,$data); check($preview['valid']); same(2,$preview['count']); same(1,$preview['eligible_count']); same(1,$preview['skipped_count']);
    $result=scheduling_commit($pdo,$data); check($result['valid']); same(1,$result['created_count']); same(1,count($result['service_ids']));
    same($before,$pdo->query('SELECT * FROM services WHERE id='.$service)->fetch());
    $snapshot=snapshot($pdo); check(scheduling_commit($pdo,$data)['replayed']); same($snapshot,snapshot($pdo));
});
require __DIR__ . '/http.php';
require __DIR__ . '/backup.php';
echo "RESULT $passed passed, $failed failed\n";
session_write_close();
$pdo = null;
// Remove only files created in our unpredictable private fixture directory.
foreach (glob($temp . '/*') ?: [] as $file) { if (is_file($file)) @unlink($file); }
@rmdir($temp);
exit($failed ? 1 : 0);
