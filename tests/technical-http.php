<?php
declare(strict_types=1);

test('HTTP malformed arrays, identifiers and unknown actions rejected without writes', function () use ($pdo,&$admin): void {
    $before=snapshot($pdo);
    foreach ([['page'=>['scales']],['page'=>'scales','scale_date'=>['bad']],['page'=>'api.events','carer_id'=>'1oops']] as $query) {
        same(400,http_request('/index.php?'.http_build_query($query),$admin['cookies'])['status']);
    }
    foreach ([['action'=>['save_patient']],['action'=>'cancel_service','id'=>['1']],['action'=>'cancel_service','id'=>'1oops'],['action'=>'not_an_action'],['action'=>'preview_service','recurrence_weekdays'=>[['1']]]] as $post) {
        same(400,http_request('/index.php',$admin['cookies'],$post+['_csrf'=>$admin['csrf']])['status']);
    }
    same($before,snapshot($pdo));
});
test('HTTP event properties explicitly exclude internal columns', function () use ($pdo,$carer,&$admin,&$care): void {
    foreach (['admin','care'] as $role) {
        $session = &${$role};
        $response=http_request('/index.php?page=api.events&start=2026-11-01&end=2026-12-01',$session['cookies']);
        same(200,$response['status']); $events=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR); check(count($events)>0);
        foreach ($events as $event) {
            $props=$event['extendedProps'];
            foreach (['created_at','updated_at','done_by_user_id'] as $field) check(!array_key_exists($field,$props),'Internal field: '.$field);
            foreach (['address','municipality','status','pocket_value','starts_at','ends_at','observations','has_observations'] as $field) check(array_key_exists($field,$props),'Missing UI field: '.$field);
        }
        if ($role === 'care') {
            $actual=array_column($events,'id'); sort($actual);
            $expected=$pdo->query("SELECT id FROM services WHERE carer_id=".(int)$carer." AND starts_at < '2026-12-01' AND ends_at > '2026-11-01'")->fetchAll(PDO::FETCH_COLUMN); sort($expected);
            same($expected,$actual);
            $filtered=http_request('/index.php?page=api.events&start=2026-11-01&end=2026-12-01&carer_id=999999',$session['cookies']);
            same($events,json_decode($filtered['body'],true,512,JSON_THROW_ON_ERROR));
            $keys=array_keys($events[0]['extendedProps']); sort($keys);
            $allowed=['address','municipality','status','starts_at','ends_at','observations','has_observations','pocket_value']; sort($allowed);
            same($allowed,$keys);
        }
    }
});
test('HTTP patient audit refers to patient rather than movement ID', function () use ($pdo,&$admin): void {
    for($i=0;$i<4;$i++) record_entity_movement($pdo,'PATIENT','Fictional audit padding','IN');
    same(302,http_request('/index.php',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'save_patient','name'=>'Audit identity fixture','address'=>'Fictional'])['status']);
    $id=(int)$pdo->query("SELECT id FROM patients WHERE name='Audit identity fixture'")->fetchColumn();
    same($id,(int)$pdo->query("SELECT entity_id FROM audit_events WHERE action='save_patient' ORDER BY id DESC LIMIT 1")->fetchColumn());
});
test('HTTP report and profile changes rollback if audit fails', function () use ($pdo,$carer,&$admin,&$care): void {
    $pdo->exec("CREATE TRIGGER fixture_audit_failure BEFORE INSERT ON audit_events WHEN NEW.action IN ('save_report','save_profile') BEGIN SELECT RAISE(ABORT,'fixture'); END");
    try {
        foreach ([['session'=>&$admin,'post'=>['action'=>'save_report','title'=>'Atomic report','body'=>'Fictional','carer_id'=>$carer]],['session'=>&$care,'post'=>['action'=>'save_profile','birth_date'=>'1980-01-02','age'=>'42','phone'=>'000000001','email'=>'atomic@example.invalid']]] as &$case) {
            $before=snapshot($pdo);
            same(500,http_request('/index.php',$case['session']['cookies'],$case['post']+['_csrf'=>$case['session']['csrf']])['status']);
            same($before,snapshot($pdo));
        }
    } finally { $pdo->exec('DROP TRIGGER fixture_audit_failure'); }
});
test('HTTP failed cancellation leaves neither mutation nor audit', function () use ($pdo,$service,&$admin): void {
    $count=(int)$pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
    $pdo->exec("CREATE TRIGGER fixture_cancel_failure BEFORE DELETE ON services BEGIN SELECT RAISE(ABORT,'fixture'); END");
    try {
        $before=snapshot($pdo);
        same(500,http_request('/index.php',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'cancel_service','id'=>$service])['status']);
        same($before,snapshot($pdo));
        same($count,(int)$pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    } finally { $pdo->exec('DROP TRIGGER fixture_cancel_failure'); }
});
test('HTTP duplicate completion is idempotent and audit failure rolls back undo', function () use ($pdo,$service,&$care): void {
    $old=$pdo->query('SELECT * FROM services WHERE id='.(int)$service)->fetch();
    $pdo->prepare("UPDATE services SET status='DONE', done_at='2026-11-01 12:00:00' WHERE id=?")->execute([$service]);
    try {
        $before=snapshot($pdo);
        same(302,http_request('/index.php',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>'done_service','id'=>$service])['status']);
        check($before===snapshot($pdo),'Repeated completion rewrote history');
        $pdo->exec("CREATE TRIGGER fixture_undo_failure BEFORE INSERT ON audit_events WHEN NEW.action='undo_service' BEGIN SELECT RAISE(ABORT,'fixture'); END");
        try {
            same(500,http_request('/index.php',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>'undo_service','id'=>$service])['status']);
            check($before===snapshot($pdo),'Failed undo left partial state');
        } finally { $pdo->exec('DROP TRIGGER fixture_undo_failure'); }
    } finally { $pdo->prepare('UPDATE services SET status=?,done_at=? WHERE id=?')->execute([$old['status'],$old['done_at'],$service]); }
});
test('HTTP last administrator and self deletion remain protected', function () use ($pdo,&$admin): void {
    $admins=$pdo->query("SELECT * FROM users WHERE role='ADMIN'")->fetchAll();
    $target=$pdo->query("SELECT * FROM users WHERE username='Andreia'")->fetch();
    $pdo->exec("UPDATE users SET role='EDITOR' WHERE role='ADMIN' AND id!=".(int)$target['id']);
    try {
        $before=snapshot($pdo);
        same(302,http_request('/index.php',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'save_user','id'=>$target['id'],'name'=>$target['name'],'username'=>$target['username'],'role'=>'EDITOR','carer_id'=>'','password'=>''])['status']);
        same(302,http_request('/index.php',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'delete_user','id'=>$target['id']])['status']);
        check($before===snapshot($pdo),'Last administrator changed');
    } finally { foreach($admins as $row) $pdo->prepare("UPDATE users SET role='ADMIN' WHERE id=?")->execute([$row['id']]); }
});
test('HTTP forbidden action matrix and anonymous routes', function () use ($pdo,&$admin,&$editor,&$care): void {
    $before=snapshot($pdo);
    foreach(['save_user','delete_user'] as $action) same(403,http_request('/index.php',$editor['cookies'],['_csrf'=>$editor['csrf'],'action'=>$action,'id'=>'1'])['status']);
    foreach(['save_carer','delete_carer','save_patient','delete_patient','preview_service','save_service','cancel_service','delete_service','save_user','delete_user'] as $action) same(403,http_request('/index.php',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>$action,'id'=>'1'])['status']);
    foreach(['done_service','undo_service','save_profile'] as $action) same(403,http_request('/index.php',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>$action,'id'=>'1'])['status']);
    foreach(['dashboard','scales','patients','carers','users','reports','finances','settings','pocket','profile','api.events'] as $page) {
        $cookies=[]; same(302,http_request('/index.php?page='.$page,$cookies)['status']);
    }
    same(404,http_request('/index.php?page=not-a-route',$admin['cookies'])['status']);
    check($before===snapshot($pdo),'Forbidden action changed business data');
});
test('HTTP non-finite money and NUL dates cannot persist', function () use ($pdo,$carer,&$admin): void {
    $before=snapshot($pdo);
    same(302,http_request('/index.php',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'save_patient','name'=>'Bad rate','address'=>'Fictional','client_hourly_rate'=>'1e309'])['status']);
    same(302,http_request('/index.php',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'save_carer','birth_date'=>'1980-01-02','id'=>$carer,'name'=>'Fixture Carer','email'=>'fixture@example.invalid','phone'=>'000000000','color_hex'=>'#123456','pocket_adjustment'=>'1e309'])['status']);
    same(400,http_request('/index.php?page=scales&scale_date=%00',$admin['cookies'])['status']);
    check($before===snapshot($pdo),'Non-finite money persisted');
});
test('HTTP report read markers rollback together', function () use ($pdo,$carer,$timestamp,&$care): void {
    $pdo->prepare('INSERT INTO reports(title,body,sender_user_id,sender_role,carer_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute(['Atomic read fixture','Fictional',1,'ADMIN',$carer,$timestamp,$timestamp]);
    $id=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO report_replies(report_id,sender_user_id,sender_role,body,created_at) VALUES(?,?,?,?,?)')->execute([$id,1,'ADMIN','Fictional',$timestamp]);
    $pdo->exec("CREATE TRIGGER fixture_read_failure BEFORE UPDATE ON reports BEGIN SELECT RAISE(ABORT,'fixture'); END");
    try {
        $before=snapshot($pdo);
        same(500,http_request('/index.php',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>'mark_report_read','id'=>$id])['status']);
        check($before===snapshot($pdo),'Read markers partially persisted');
    } finally { $pdo->exec('DROP TRIGGER fixture_read_failure'); }
});
test('HTTP impossible event dates are rejected instead of normalized', function () use (&$care): void {
    same(400,http_request('/index.php?page=api.events&start=2026-02-30&end=2026-03-05',$care['cookies'])['status']);
});
test('HTTP impossible patient dates cannot persist', function () use ($pdo,&$admin): void {
    $before=snapshot($pdo);
    same(302,http_request('/index.php',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'save_patient','name'=>'Invalid date fixture','address'=>'Fictional','birth_date'=>'2026-02-30'])['status']);
    check($before===snapshot($pdo),'Invalid birth date persisted');
});
test('HTTP carer CRUD keeps linked account atomic and rejects username collision', function () use ($pdo,$password,&$admin): void {
    $data=['_csrf'=>$admin['csrf'],'action'=>'save_carer','birth_date'=>'1980-01-02','name'=>'CRUD Fixture Carer','phone'=>'000000000','email'=>'crud@example.invalid','color_hex'=>'#a1b2c3','password'=>$password];
    same(302,http_request('/index.php',$admin['cookies'],$data)['status']);
    $row=$pdo->query("SELECT * FROM carers WHERE email='crud@example.invalid'")->fetch(); check((bool)$row);
    $linked=$pdo->query('SELECT * FROM users WHERE carer_id='.(int)$row['id'])->fetch(); check((bool)$linked);
    same((int)$linked['id'],(int)$row['user_id']); check(password_verify($password,$linked['password']));
    $before=snapshot($pdo);
    same(302,http_request('/index.php',$admin['cookies'],array_replace($data,['id'=>$row['id'],'name'=>'Andreia','password'=>'']))['status']);
    check($before===snapshot($pdo),'Username collision partially changed carer');
    same(302,http_request('/index.php',$admin['cookies'],array_replace($data,['id'=>$row['id'],'name'=>'Renamed CRUD Fixture','password'=>'']))['status']);
    same('Renamed CRUD Fixture',$pdo->query('SELECT username FROM users WHERE id='.(int)$linked['id'])->fetchColumn());
    same(302,http_request('/index.php',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'delete_carer','id'=>$row['id']])['status']);
    same(0,(int)$pdo->query('SELECT COUNT(*) FROM users WHERE id='.(int)$linked['id'])->fetchColumn());
    same(0,(int)$pdo->query('SELECT COUNT(*) FROM carers WHERE id='.(int)$row['id'])->fetchColumn());
});
test('HTTP profile photos are private and roles cannot access other profiles', function () use ($pdo,$carer,$patient,&$care,&$admin): void {
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    profile_photo_save($pdo,'carer',$carer,['mime'=>'image/png','content'=>$png]);
    profile_photo_save($pdo,'patient',$patient,['mime'=>'image/png','content'=>$png]);
    try {
        $own=http_request('/index.php?page=photo&kind=carer&id='.$carer,$care['cookies']);same(200,$own['status']);same($png,$own['body']);
        same(403,http_request('/index.php?page=photo&kind=patient&id='.$patient,$care['cookies'])['status']);
        same(403,http_request('/index.php?page=photo&kind=carer&id=999999',$care['cookies'])['status']);
        same(200,http_request('/index.php?page=photo&kind=patient&id='.$patient,$admin['cookies'])['status']);
        $anonymous=[];same(302,http_request('/index.php?page=photo&kind=carer&id='.$carer,$anonymous)['status']);
    } finally { $pdo->exec('DELETE FROM profile_photos'); }
});
test('HTTP carer creation requires date of birth and photo writes rollback together',function()use($pdo,$password,&$admin):void{
    $before=snapshot($pdo);
    same(302,http_request('/index.php',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'save_carer','name'=>'Missing birthday','phone'=>'000000000','email'=>'missing@example.invalid','password'=>$password,'color_hex'=>'#981234'])['status']);
    same($before,snapshot($pdo));
    $pdo->beginTransaction();
    profile_photo_save($pdo,'carer',999999,['mime'=>'image/png','content'=>'fixture rollback only']);$pdo->rollBack();
    same(0,(int)$pdo->query('SELECT COUNT(*) FROM profile_photos')->fetchColumn());
});
test('Printed statements and calendars respect role and output format',function()use(&$admin,&$editor,&$care,$patient):void{
    $url='/index.php?page=client-statement&month=2026-11&client='.$patient;
    $response=http_request($url,$admin['cookies']);
    same(200,$response['status']);check(str_contains($response['body'],'Total dos serviços concluídos'));
    same(403,http_request($url,$care['cookies'])['status']);
    same(403,http_request($url,$editor['cookies'])['status']);
    foreach (['day','week','month'] as $period) {
        $sheet=http_request('/index.php?page=scales&full_sheet=1&municipality=all&period='.$period,$editor['cookies']);
        same(200,$sheet['status']);check(str_contains($sheet['body'],'data-print'));
    }
});
test('Login stays anonymous when its audit write fails',function()use($pdo,$password):void{
    $cookies=[];
    $page=http_request('/index.php?page=login',$cookies);
    $pdo->exec("CREATE TRIGGER fixture_login_audit BEFORE INSERT ON audit_events WHEN NEW.action='login' BEGIN SELECT RAISE(ABORT,'fixture'); END");
    try {
        $response=http_request('/index.php',$cookies,['_csrf'=>http_csrf($page),'action'=>'login','username'=>'Andreia','password'=>$password]);
        same(500,$response['status']);
        same(302,http_request('/index.php?page=dashboard',$cookies)['status']);
    } finally { $pdo->exec('DROP TRIGGER fixture_login_audit'); }
});
