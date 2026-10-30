<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$file=getenv('CARE4ALL_DB_FILE');
$password=getenv('CARE4ALL_TEST_PASSWORD');
if (is_file($root.'/upload-ready/config-local.php')) { fwrite(STDERR,"Refusing local configuration override.\n"); exit(2); }
$runtime=$root.'/.runtime';
if (!is_dir($runtime)) mkdir($runtime,0700,true);
$parent=is_string($file)?realpath(dirname($file)):false;
if (!$parent || strcasecmp($parent,(string)realpath($runtime))!==0 || !preg_match('/^fixture-[a-zA-Z0-9_-]+\.sqlite$/D',basename($file)) || file_exists($file) || is_link($file)) {
    fwrite(STDERR,"CARE4ALL_DB_FILE must name a NEW .runtime/fixture-NAME.sqlite.\n"); exit(2);
}
if (!is_string($password) || strlen($password)<12) { fwrite(STDERR,"Set CARE4ALL_TEST_PASSWORD >=12 characters.\n"); exit(2); }
putenv('CARE4ALL_INITIAL_ADMIN_PASSWORD='.$password);
require $root.'/upload-ready/bootstrap.php';
if (DB_FILE!==$file) throw new RuntimeException('Unsafe database override.');
$pdo=db();
$pdo->beginTransaction();
try {
    $stamp=now();
    $carerInsert=$pdo->prepare('INSERT INTO carers(name,phone,email,color_hex,created_at,updated_at) VALUES(?,?,?,?,?,?)');
    $patientInsert=$pdo->prepare('INSERT INTO patients(name,address,client_hourly_rate,created_at,updated_at) VALUES(?,?,?,?,?)');
    $userInsert=$pdo->prepare('INSERT INTO users(name,username,password,role,carer_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?)');
    $serviceInsert=$pdo->prepare('INSERT INTO services(patient_id,carer_id,starts_at,ends_at,hourly_rate,status,municipality,observations,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
    $municipalities=scheduling_municipalities();
    for ($i=1;$i<=12;$i++) {
        $carerInsert->execute(['Fixture Carer '.$i,'000000000','carer'.$i.'@example.invalid',sprintf('#%06x',0x224466+$i*731),$stamp,$stamp]);
        $carer=(int)$pdo->lastInsertId();
        $patientInsert->execute(['Fixture Patient '.$i,'Fictional address '.$i,20,$stamp,$stamp]);
        $patient=(int)$pdo->lastInsertId();
        $userInsert->execute(['Fixture Carer '.$i,'fixture-carer-'.$i,password_hash($password,PASSWORD_DEFAULT),'CARER',$carer,$stamp,$stamp]);
        $pdo->prepare('UPDATE carers SET user_id=? WHERE id=?')->execute([(int)$pdo->lastInsertId(),$carer]);
        for ($day=-2;$day<=5;$day++) {
            $date=(new DateTimeImmutable('today'))->modify(($day>=0?'+':'').$day.' days')->format('Y-m-d');
            $hour=8+($i%8);
            $serviceInsert->execute([$patient,$carer,sprintf('%s %02d:00:00',$date,$hour),sprintf('%s %02d:30:00',$date,$hour+1),10,['SCHEDULED','DONE','CANCELLED'][$i%3],$municipalities[$i%count($municipalities)],'Synthetic fixture only',$stamp,$stamp]);
        }
    }
    $userInsert->execute(['Fixture Editor','fixture-editor',password_hash($password,PASSWORD_DEFAULT),'EDITOR',null,$stamp,$stamp]);
    $pdo->commit();
    echo "Fixture created: ".$file."\nUsers: Andreia (ADMIN), fixture-editor (EDITOR), fixture-carer-1 (CARER). Password: CARE4ALL_TEST_PASSWORD environment only.\n";
} catch (Throwable $error) { $pdo->rollBack(); throw $error; }
