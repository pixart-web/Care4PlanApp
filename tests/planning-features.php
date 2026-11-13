<?php
declare(strict_types=1);

test('National holidays include Easter Good Friday and Corpus Christi',function():void{
    $dates=national_holidays(2026); same(13,count(array_unique($dates)));
    foreach(['2026-04-03','2026-04-05','2026-06-04','2026-12-01','2026-12-08'] as $date)check(in_array($date,$dates,true));
    check(!special_rate_day('2026-11-04'));check(special_rate_day('2026-11-07'));check(!special_rate_day('2026-02-17'),'Carnival must not become a national holiday');
});
test('Rates split at midnight and preserve legacy single-rate values',function():void{
    $row=['starts_at'=>'2026-11-06 23:00:00','ends_at'=>'2026-11-07 01:00:00','hourly_rate'=>10,'hourly_rate_special'=>20];
    same(30.0,service_rate_amount($row)); same(30.0,service_pocket_value($row));
    $row['hourly_rate_special']=null;same(20.0,service_rate_amount($row));
    $row['starts_at']='2026-12-01 09:00:00';$row['ends_at']='2026-12-01 11:00:00';$row['hourly_rate_special']=20;same(40.0,service_rate_amount($row));
});
test('Birth dates mandatory for carers; birthdays do not mutate profiles',function()use($pdo,$carer,$patient):void{
    rejects(fn()=>profile_extra_values('save_carer',[]));
    rejects(fn()=>profile_extra_values('save_carer',['birth_date'=>'2026-02-30']));
    $pdo->beginTransaction();try{
        $pdo->prepare('UPDATE carers SET birth_date=? WHERE id=?')->execute(['1980-11-04',$carer]);
        $pdo->prepare('UPDATE patients SET birth_date=? WHERE id=?')->execute(['1950-11-04',$patient]);
        $before=snapshot($pdo);same(2,count(profile_birthdays($pdo,'2026-11-04')));same($before,snapshot($pdo));
        same(0,count(profile_birthdays($pdo,'2026-11-05')));
    }finally{$pdo->rollBack();}
});
$featureData=array_replace($base,['starts_at'=>'2032-03-01T09:00','ends_at'=>'2032-03-01T10:00','hourly_rate'=>10,'hourly_rate_special'=>20,'idempotency_key'=>bin2hex(random_bytes(16))]);
test('Three daily slots recur atomically with independent series',function()use($pdo,$featureData):void{
    $data=array_replace($featureData,['extra_times'=>'1','extra_start_2'=>'14:00','extra_end_2'=>'15:00','extra_start_3'=>'20:00','extra_end_3'=>'21:00','recurrence_mode'=>'dates','recurrence_dates'=>['2032-03-02']]);
    $preview=scheduling_preview($pdo,$data);check($preview['valid']);same(6,$preview['count']);
    $result=scheduling_commit($pdo,$data);same(6,$result['created_count']);check(scheduling_commit($pdo,$data)['replayed']);
    $ids=implode(',',$result['service_ids']);same(3,(int)$pdo->query('SELECT COUNT(DISTINCT series_key) FROM services WHERE id IN ('.$ids.')')->fetchColumn());
});
test('Additional daily slots cannot overlap or exceed three via malformed times',function()use($pdo,$featureData):void{
    $data=array_replace($featureData,['starts_at'=>'2032-03-03T09:00','ends_at'=>'2032-03-03T10:00','extra_times'=>'1','extra_start_2'=>'09:30','extra_end_2'=>'11:00']);
    $before=snapshot($pdo);check(!scheduling_preview($pdo,$data)['valid']);same($before,snapshot($pdo));
    $data['extra_start_2']='25:30';check(!scheduling_preview($pdo,$data)['valid']);
});
test('Series editing changes matching hours and preserves other daily slots',function()use($pdo,$featureData):void{
    $id=(int)$pdo->query("SELECT id FROM services WHERE starts_at='2032-03-01 09:00:00'")->fetchColumn();
    $data=array_replace($featureData,['id'=>$id,'starts_at'=>'2032-03-01T10:00','ends_at'=>'2032-03-01T11:00','edit_series'=>'1','idempotency_key'=>bin2hex(random_bytes(16))]);
    same(2,scheduling_preview($pdo,$data)['count']);$result=scheduling_commit($pdo,$data);same(2,$result['updated_count']);
    check(scheduling_commit($pdo,$data)['replayed']);
    same(1,(int)$pdo->query("SELECT COUNT(*) FROM services WHERE starts_at='2032-03-02 10:00:00'")->fetchColumn());
    same(1,(int)$pdo->query("SELECT COUNT(*) FROM services WHERE starts_at='2032-03-02 14:00:00'")->fetchColumn());
});
test('Monthly copy keeps weekday ordinal and rejects duplicate replay with a new key',function()use($pdo):void{
    $data=['planning_mode'=>'copy_month','source_month'=>'2032-03','idempotency_key'=>bin2hex(random_bytes(16))];
    $plan=scheduling_plan($pdo,$data);same(6,count($plan['occurrences']));
    same('2032-04-05',substr($plan['occurrences'][0]['starts_at'],0,10)); // First Monday -> first Monday.
    $result=scheduling_commit($pdo,$data);same(6,$result['created_count']);check(scheduling_commit($pdo,$data)['replayed']);
    $data['idempotency_key']=bin2hex(random_bytes(16));check(!scheduling_preview($pdo,$data)['valid']);
    same(6,(int)$pdo->query("SELECT COUNT(*) FROM services WHERE starts_at>='2032-04-01' AND starts_at<'2032-05-01'")->fetchColumn());
});
test('A missing fifth weekday is reported and requires explicit consent',function()use($pdo,$featureData):void{
    $data=array_replace($featureData,['starts_at'=>'2032-03-29T09:00','ends_at'=>'2032-03-29T10:00','idempotency_key'=>bin2hex(random_bytes(16))]);
    check(scheduling_commit($pdo,$data)['valid']);
    $plan=planning_month($pdo,['source_month'=>'2032-03']);same(1,count($plan['unmatched']));check(!$plan['allow_unmatched']);
});
test('Series conflicts fail as a whole without moving any service',function()use($pdo,$featureData):void{
    $id=(int)$pdo->query("SELECT id FROM services WHERE starts_at='2032-03-01 10:00:00'")->fetchColumn();
    $data=array_replace($featureData,['id'=>$id,'starts_at'=>'2032-03-01T14:00','ends_at'=>'2032-03-01T15:00','edit_series'=>'1','idempotency_key'=>bin2hex(random_bytes(16))]);
    $before=snapshot($pdo);check(!scheduling_commit($pdo,$data)['valid']);same($before,snapshot($pdo));
});
test('Service price snapshots survive changes to profile tariffs',function()use($pdo,$featureData,$patient):void{
    $pdo->beginTransaction();try{
        $pdo->prepare('UPDATE patients SET client_hourly_rate=99,client_special_rate=199 WHERE id=?')->execute([$patient]);
        $row=$pdo->query("SELECT * FROM services WHERE starts_at='2032-03-02 14:00:00'")->fetch();
        same(20.0,(float)$row['client_rate_snapshot']);same(20.0,(float)$row['hourly_rate_special']);
    }finally{$pdo->rollBack();}
});
test('Reassigning a service uses the new client prices',function()use($pdo,$featureData,$patient):void{
    $pdo->beginTransaction();try{
        $other=(int)$pdo->query('SELECT id FROM patients WHERE id!='.(int)$patient.' LIMIT 1')->fetchColumn();
        $pdo->prepare('UPDATE patients SET client_hourly_rate=55,client_special_rate=75 WHERE id=?')->execute([$other]);
        $id=(int)$pdo->query("SELECT id FROM services WHERE starts_at='2032-03-02 14:00:00'")->fetchColumn();
        $plan=scheduling_plan($pdo,array_replace($featureData,['id'=>$id,'patient_id'=>$other,'starts_at'=>'2032-03-02T14:00','ends_at'=>'2032-03-02T15:00']));
        same(55.0,(float)$plan['client_rate_snapshot']);same(75.0,(float)$plan['client_special_snapshot']);
    }finally{$pdo->rollBack();}
});

$pdo->prepare('INSERT INTO carers(name,phone,color_hex,created_at,updated_at) VALUES(?,?,?,?,?)')
    ->execute(['Second fixture carer','000000000','#456789',now(),now()]);
test('An additional carer requires confirmation and a cancelled slot can be reused',function()use($pdo,$featureData,$carer):void{
    $other=(int)$pdo->query('SELECT id FROM carers WHERE id!='.(int)$carer.' LIMIT 1')->fetchColumn();
    $slot=array_replace($featureData,['starts_at'=>'2036-04-01T09:00','ends_at'=>'2036-04-01T10:00','idempotency_key'=>bin2hex(random_bytes(16))]);
    $first=scheduling_commit($pdo,$slot);check($first['valid']);
    $slot['carer_id']=$other;$slot['idempotency_key']=bin2hex(random_bytes(16));
    $warning=scheduling_commit($pdo,$slot);check(!$warning['valid']);same(1,count($warning['shared_visits']));
    $slot['shared_confirmation']=$warning['shared_confirmation'];$second=scheduling_commit($pdo,$slot);check($second['valid']);
    $pdo->prepare("UPDATE services SET status='CANCELLED' WHERE id=?")->execute([$second['service_ids'][0]]);
    $slot['idempotency_key']=bin2hex(random_bytes(16));
    $fresh=scheduling_commit($pdo,$slot);check(!$fresh['valid']);
    $slot['shared_confirmation']=$fresh['shared_confirmation'];
    $result=scheduling_commit($pdo,$slot);check($result['valid']);
    same(0,(int)$pdo->query("SELECT COUNT(*) FROM services WHERE id=".(int)$second['service_ids'][0])->fetchColumn());
    $slot['idempotency_key']=bin2hex(random_bytes(16));check(!scheduling_commit($pdo,$slot)['valid']);
});
test('Replacing a carer affects only selected and later series dates',function()use($pdo,$featureData,$carer):void{
    $other=(int)$pdo->query('SELECT id FROM carers WHERE id!='.(int)$carer.' LIMIT 1')->fetchColumn();
    $slot=array_replace($featureData,['starts_at'=>'2036-05-01T09:00','ends_at'=>'2036-05-01T10:00','recurrence_mode'=>'dates','recurrence_dates'=>['2036-05-03','2036-05-05'],'idempotency_key'=>bin2hex(random_bytes(16))]);
    $created=scheduling_commit($pdo,$slot);check($created['valid']);
    $change=array_replace($slot,['id'=>$created['service_ids'][1],'carer_id'=>$other,'starts_at'=>'2036-05-03T09:00','ends_at'=>'2036-05-03T10:00','edit_series'=>'future','recurrence_mode'=>'none','idempotency_key'=>bin2hex(random_bytes(16))]);
    $result=scheduling_commit($pdo,$change);same(2,$result['updated_count']);
    same((int)$carer,(int)$pdo->query('SELECT carer_id FROM services WHERE id='.(int)$created['service_ids'][0])->fetchColumn());
});

test('Preview detects a changed skip set inside the write transaction',function()use($pdo,$featureData):void{
    $slot=array_replace($featureData,['starts_at'=>'2037-04-01T09:00','ends_at'=>'2037-04-01T10:00','idempotency_key'=>bin2hex(random_bytes(16))]);
    $batch=array_replace($slot,['idempotency_key'=>bin2hex(random_bytes(16)),'skip_conflicts'=>'1','recurrence_mode'=>'dates','recurrence_dates'=>['2037-04-02']]);
    check(scheduling_preview($pdo,$batch,$hash)['valid']);
    check(scheduling_commit($pdo,$slot)['valid']);
    $before=snapshot($pdo);
    rejects(fn()=>scheduling_commit($pdo,$batch,$hash));
    same($before,snapshot($pdo));
});
test('Rounding of weekday and special components is preserved',function():void{
    $parts=service_rate_parts(['starts_at'=>'2026-11-13 23:45','ends_at'=>'2026-11-14 00:15','hourly_rate'=>10.02]);
    same(['weekday'=>2.51,'special'=>2.51],$parts);
});
