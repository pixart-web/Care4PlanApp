<?php
declare(strict_types=1);

function http_request(string $path, array &$cookies, ?array $post = null): array {
    global $baseUrl;
    $headers = ['Connection: close'];
    if ($cookies) $headers[] = 'Cookie: ' . implode('; ', array_map(fn($k,$v)=>$k . '=' . $v,array_keys($cookies),$cookies));
    if ($post !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $context = stream_context_create(['http'=>['method'=>$post===null?'GET':'POST','header'=>implode("\r\n",$headers),'content'=>$post===null?'':http_build_query($post),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]);
    $body = file_get_contents($baseUrl . $path,false,$context);
    $responseHeaders = $http_response_header ?? [];
    if ($body === false || !$responseHeaders) throw new RuntimeException('HTTP request failed');
    preg_match('/\s(\d{3})\s/',$responseHeaders[0],$status);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i',$header,$match)) $cookies[$match[1]]=$match[2];
    }
    return ['status'=>(int)($status[1]??0),'body'=>$body,'headers'=>$responseHeaders];
}
function http_csrf(array $response): string {
    check((bool)preg_match('/name="_csrf"\s+value="([a-f0-9]+)"/',$response['body'],$match),'CSRF input missing');
    return $match[1];
}
function http_login(string $username, string $password): array {
    $cookies=[];
    $page=http_request('/index.php?page=login',$cookies); same(200,$page['status']);
    $old=$cookies['PHPSESSID']??null;
    $response=http_request('/index.php?page=login',$cookies,['_csrf'=>http_csrf($page),'action'=>'login','username'=>$username,'password'=>$password]);
    same(302,$response['status']); check(($cookies['PHPSESSID']??null)!==$old,'Session ID not regenerated');
    $dashboard=http_request('/index.php?page=dashboard',$cookies); same(200,$dashboard['status']);
    return ['cookies'=>$cookies,'csrf'=>http_csrf($dashboard)];
}
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
if (!$socket) throw new RuntimeException('Cannot allocate loopback port');
$address=stream_socket_get_name($socket,false); fclose($socket);
$baseUrl='http://' . $address;
$server=proc_open([PHP_BINARY,'-d','session.save_path=' . $temp,'-S',$address,'-t','upload-ready'],[0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,$root,null,['bypass_shell'=>true]);
if (!is_resource($server)) throw new RuntimeException('Cannot start PHP test server');
fclose($pipes[0]);
try {
    $ready=false;
    for ($i=0;$i<60;$i++) {
        $probe=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1);
        if ($probe) { fclose($probe); $ready=true; break; }
        usleep(100000);
    }
    if (!$ready) {
        $status=proc_get_status($server);
        throw new RuntimeException('PHP server did not start: ' . json_encode($status,JSON_INVALID_UTF8_SUBSTITUTE) . ' log=' . (string)@file_get_contents($temp.'/server.log'));
    }
    $admin=http_login('Andreia',$password);
    $editor=http_login('fixture-editor',$password);
    $care=http_login('fixture-carer',$password);
    require __DIR__ . '/technical-http.php';
    test('HTTP login regenerates sessions; cookies use HttpOnly and SameSite',function (): void {
        $cookies=[];
        $response=http_request('/index.php?page=login',$cookies);
        $headers=implode("\n",$response['headers']);
        check(stripos($headers,'HttpOnly')!==false); check(stripos($headers,'SameSite=Lax')!==false);
    });
    test('HTTP CSRF rejects missing, wrong and array token without mutation',function () use ($pdo,&$admin): void {
        $before=snapshot($pdo);
        foreach ([null,'wrong',['array']] as $token) {
            $post=['action'=>'cancel_service','id'=>1]; if ($token!==null) $post['_csrf']=$token;
            same(419,http_request('/index.php?page=scales',$admin['cookies'],$post)['status']);
        }
        same($before,snapshot($pdo));
    });
    test('HTTP ADMIN EDITOR CARER route permissions',function () use (&$admin,&$editor,&$care): void {
        foreach (['dashboard','scales','patients','carers','users','reports','finances','settings'] as $page) same(200,http_request('/index.php?page='.$page,$admin['cookies'])['status']);
        foreach (['dashboard','scales','patients','carers','reports','settings'] as $page) same(200,http_request('/index.php?page='.$page,$editor['cookies'])['status']);
        foreach (['users','finances'] as $page) same(403,http_request('/index.php?page='.$page,$editor['cookies'])['status']);
        foreach (['dashboard','scales','reports','profile','pocket'] as $page) same(200,http_request('/index.php?page='.$page,$care['cookies'])['status']);
        foreach (['patients','carers','users','finances','settings'] as $page) same(403,http_request('/index.php?page='.$page,$care['cookies'])['status']);
    });
    test('HTTP CARER forbidden writes and report patient ownership',function () use ($pdo,$otherPatient,$patient,&$care): void {
        $before=snapshot($pdo);
        same(403,http_request('/index.php?page=patients',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>'save_patient','name'=>'Forbidden','address'=>'No'])['status']);
        same(302,http_request('/index.php?page=reports',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>'save_report','title'=>'Forbidden report','body'=>'Fixture','patient_id'=>$otherPatient])['status']);
        same($before,snapshot($pdo));
        same(302,http_request('/index.php?page=reports',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>'save_report','title'=>'Allowed report','body'=>'Fixture','patient_id'=>$patient])['status']);
        same(1,(int)$pdo->query("SELECT COUNT(*) FROM reports WHERE title='Allowed report'")->fetchColumn());
    });
    test('HTTP reports GET does not mutate read markers or business rows',function () use ($pdo,$carer,$carerUser,$timestamp,&$care): void {
        $pdo->prepare('INSERT INTO reports(title,body,sender_user_id,sender_role,carer_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute(['Fixture admin report','Private fixture',1,'ADMIN',$carer,$timestamp,$timestamp]);
        $id=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO report_replies(report_id,sender_user_id,sender_role,body,created_at) VALUES(?,?,?,?,?)')->execute([$id,1,'ADMIN','Unread fixture reply',$timestamp]);
        $before=snapshot($pdo); same(200,http_request('/index.php?page=reports',$care['cookies'])['status']); same($before,snapshot($pdo));
    });
    test('HTTP foreign reports cannot be read replied to or deleted by CARER',function () use ($pdo,$timestamp,&$care): void {
        $pdo->prepare('INSERT INTO reports(title,body,sender_user_id,sender_role,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute(['Foreign private report','Foreign private body',1,'ADMIN',$timestamp,$timestamp]);
        $id=(int)$pdo->lastInsertId();
        $before=snapshot($pdo);
        check(!str_contains(http_request('/index.php?page=reports',$care['cookies'])['body'],'Foreign private report'));
        same(403,http_request('/index.php?page=reports',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>'reply_report','id'=>$id,'body'=>'Forbidden'])['status']);
        foreach (['mark_report_read','delete_report'] as $action) same(302,http_request('/index.php?page=reports',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>$action,'id'=>$id])['status']);
        same($before,snapshot($pdo));
    });
    test('HTTP recurring save requires unchanged server preview',function () use ($pdo,$base,&$admin): void {
        $data=array_replace($base,['starts_at'=>'2027-02-01T09:00','ends_at'=>'2027-02-01T10:00','recurrence_mode'=>'weekdays','recurrence_until'=>'2027-02-05','recurrence_weekdays'=>[1,2,3,4,5],'idempotency_key'=>bin2hex(random_bytes(16))]);
        $before=snapshot($pdo);
        same(302,http_request('/index.php?page=scales',$admin['cookies'],$data+['_csrf'=>$admin['csrf'],'action'=>'save_service'])['status']);
        same($before,snapshot($pdo));
        $response=http_request('/index.php?page=scales',$admin['cookies'],$data+['_csrf'=>$admin['csrf'],'action'=>'preview_service']);
        $preview=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR);
        $data['preview_token']=$preview['preview_token']; $data['hourly_rate']=11;
        same(302,http_request('/index.php?page=scales',$admin['cookies'],$data+['_csrf'=>$admin['csrf'],'action'=>'save_service'])['status']); same($before,snapshot($pdo));
    });
    test('HTTP real empty hidden id previews and saves a single service',function () use ($pdo,$base,&$admin): void {
        $data=array_replace($base,['id'=>'','starts_at'=>'2027-02-15T09:00','ends_at'=>'2027-02-15T10:00','recurrence_mode'=>'none','idempotency_key'=>bin2hex(random_bytes(16))]);
        $response=http_request('/index.php?page=scales',$admin['cookies'],$data+['_csrf'=>$admin['csrf'],'action'=>'preview_service']);
        same(200,$response['status']); $preview=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR); check($preview['valid']); same(1,$preview['count']);
        same(302,http_request('/index.php?page=scales',$admin['cookies'],$data+['_csrf'=>$admin['csrf'],'action'=>'save_service'])['status']);
        same(1,(int)$pdo->query("SELECT COUNT(*) FROM services WHERE starts_at='2027-02-15 09:00:00'")->fetchColumn());
    });
    test('HTTP actual save_patient rolls back when movement insert fails',function () use ($pdo,&$admin): void {
        $pdo->exec("CREATE TRIGGER fixture_patient_failure BEFORE INSERT ON entity_movements WHEN NEW.entity_type='PATIENT' BEGIN SELECT RAISE(ABORT,'fixture failure'); END");
        try {
            $before=snapshot($pdo);
            same(500,http_request('/index.php?page=patients',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'save_patient','name'=>'Rollback patient','address'=>'Fictional'])['status']);
            same($before,snapshot($pdo));
        } finally { $pdo->exec('DROP TRIGGER fixture_patient_failure'); }
    });
    test('HTTP actual save_carer rolls back when linked user insert fails',function () use ($pdo,$password,&$admin): void {
        $pdo->exec("CREATE TRIGGER fixture_carer_failure BEFORE INSERT ON users WHEN NEW.username='Rollback Carer' BEGIN SELECT RAISE(ABORT,'fixture failure'); END");
        try {
            $before=snapshot($pdo);
            same(500,http_request('/index.php?page=carers',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'save_carer','name'=>'Rollback Carer','phone'=>'000000000','email'=>'rollback@example.invalid','color_hex'=>'#654321','password'=>$password])['status']);
            same($before,snapshot($pdo));
        } finally { $pdo->exec('DROP TRIGGER fixture_carer_failure'); }
    });
    test('HTTP service preview and recurring save with replay',function () use ($pdo,$base,&$admin): void {
        $data=array_replace($base,['starts_at'=>'2027-01-04T09:00','ends_at'=>'2027-01-04T10:00','recurrence_mode'=>'weekdays','recurrence_until'=>'2027-01-08','recurrence_weekdays'=>[1,2,3,4,5],'idempotency_key'=>bin2hex(random_bytes(16))]);
        $preview=http_request('/index.php?page=scales',$admin['cookies'],$data+['_csrf'=>$admin['csrf'],'action'=>'preview_service']);
        same(200,$preview['status']); $json=json_decode($preview['body'],true,512,JSON_THROW_ON_ERROR);
        check($json['valid']??false); same(5,$json['count']); check(!empty($json['preview_token']));
        $data['preview_token']=$json['preview_token'];
        $post=$data+['_csrf'=>$admin['csrf'],'action'=>'save_service'];
        same(302,http_request('/index.php?page=scales',$admin['cookies'],$post)['status']);
        same(5,(int)$pdo->query("SELECT COUNT(*) FROM services WHERE starts_at BETWEEN '2027-01-04' AND '2027-01-09'")->fetchColumn());
        $before=snapshot($pdo); same(302,http_request('/index.php?page=scales',$admin['cookies'],$post)['status']); same($before,snapshot($pdo));
    });
    test('HTTP calendar server filters and escaped search',function () use ($service,$carer,$patient,&$admin): void {
        $query=['page'=>'scales','period'=>'day','scale_date'=>'2026-11-02','municipality'=>'Lagos','carer_id'=>$carer,'patient_id'=>$patient,'status'=>'SCHEDULED'];
        $response=http_request('/index.php?'.http_build_query($query),$admin['cookies']); same(200,$response['status']);
        check(str_contains($response['body'],'data-service-id="'.$service.'"'));
        $query['municipality']='Silves'; check(!str_contains(http_request('/index.php?'.http_build_query($query),$admin['cookies'])['body'],'data-service-id="'.$service.'"'));
        $query['municipality']='all'; $query['search']='no-such-fixture'; check(!str_contains(http_request('/index.php?'.http_build_query($query),$admin['cookies'])['body'],'data-service-id="'.$service.'"'));
        $query['search']='%'; same(200,http_request('/index.php?'.http_build_query($query),$admin['cookies'])['status']);
    });
    test('HTTP CLI backup has no web endpoint',function () use (&$admin): void {
        same(404,http_request('/maintenance/backup.php',$admin['cookies'])['status']);
    });
    test('HTTP save_user password change persists through subsequent bootstrap requests',function () use ($pdo,$password,&$admin): void {
        $andreia=$pdo->query("SELECT * FROM users WHERE username='Andreia'")->fetch();
        $changed=$password.'-http-changed';
        same(302,http_request('/index.php?page=users',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'save_user','id'=>$andreia['id'],'name'=>$andreia['name'],'username'=>$andreia['username'],'role'=>'ADMIN','carer_id'=>'','password'=>$changed])['status']);
        $query=$pdo->prepare('SELECT password FROM users WHERE id=?'); $query->execute([$andreia['id']]); $hash=$query->fetchColumn(); $query->closeCursor();
        check($hash!==$andreia['password']); check(password_verify($changed,$hash)); check(!password_verify($password,$hash));
        foreach (['dashboard','users','reports'] as $page) same(200,http_request('/index.php?page='.$page,$admin['cookies'])['status']);
        $query->execute([$andreia['id']]); same($hash,$query->fetchColumn()); $query->closeCursor();
        $newLogin=http_login('Andreia',$changed); same(200,http_request('/index.php?page=dashboard',$newLogin['cookies'])['status']);
    });
    test('HTTP CARER completion ownership and cancellation preservation',function () use ($pdo,$service,&$care,&$admin): void {
        same(302,http_request('/index.php?page=scales',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>'done_service','id'=>$service])['status']);
        same('DONE',$pdo->query('SELECT status FROM services WHERE id='.$service)->fetchColumn());
        same(302,http_request('/index.php?page=scales',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'cancel_service','id'=>$service])['status']);
        same('CANCELLED',$pdo->query('SELECT status FROM services WHERE id='.$service)->fetchColumn());
        same(403,http_request('/index.php?page=scales',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>'done_service','id'=>$service])['status']);
        same(302,http_request('/index.php?page=scales',$admin['cookies'],['_csrf'=>$admin['csrf'],'action'=>'delete_service','id'=>$service])['status']);
        same('CANCELLED',$pdo->query('SELECT status FROM services WHERE id='.$service)->fetchColumn());
    });
    test('HTTP logout invalidates authenticated session',function () use (&$care): void {
        same(302,http_request('/index.php?page=dashboard',$care['cookies'],['_csrf'=>$care['csrf'],'action'=>'logout'])['status']);
        same(302,http_request('/index.php?page=dashboard',$care['cookies'])['status']);
    });
    test('HTTP five failed logins throttle even correct credentials',function () use ($password): void {
        $cookies=[]; $login=http_request('/index.php?page=login',$cookies); $csrf=http_csrf($login);
        for ($i=0;$i<5;$i++) same(302,http_request('/index.php?page=login',$cookies,['_csrf'=>$csrf,'action'=>'login','username'=>'fixture-editor','password'=>$password.'-wrong'])['status']);
        same(302,http_request('/index.php?page=login',$cookies,['_csrf'=>$csrf,'action'=>'login','username'=>'fixture-editor','password'=>$password])['status']);
        same(302,http_request('/index.php?page=dashboard',$cookies)['status']);
    });
} finally {
    proc_terminate($server); proc_close($server);
}
