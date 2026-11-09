<?php
declare(strict_types=1);

function planning_prices(PDO $pdo,array $item): array
{
    $stmt=$pdo->prepare('SELECT client_hourly_rate,client_special_rate FROM patients WHERE id=?'); $stmt->execute([$item['patient_id']]); $client=$stmt->fetch();
    $stmt=$pdo->prepare('SELECT hourly_rate_weekday,hourly_rate_special FROM carers WHERE id=?'); $stmt->execute([$item['carer_id']]); $carer=$stmt->fetch();
    $special=$item['hourly_rate_special']??$carer['hourly_rate_special']??$item['hourly_rate'];
    if ($special==='') $special=$item['hourly_rate'];
    if (!is_numeric($special) || !is_finite((float)$special) || $special<0 || $special>10000) throw new InvalidArgumentException('Introduza uma tarifa de fins de semana e feriados válida.');
    return ['hourly_rate_special'=>(float)$special,'client_rate_snapshot'=>$item['client_rate_snapshot']??(float)($client['client_hourly_rate']??0),
        'client_special_snapshot'=>$item['client_special_snapshot']??(float)($client['client_special_rate']??$client['client_hourly_rate']??0)];
}

function planning_series(PDO $pdo,array $service): array
{
    $ids=[];
    if (!empty($service['series_key'])) {
        $stmt=$pdo->prepare('SELECT id FROM services WHERE series_key=?'); $stmt->execute([$service['series_key']]); $ids=$stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        // Recover membership of pre-existing recurring batches without rewriting history.
        foreach ($pdo->query('SELECT result_json FROM service_batches ORDER BY created_at') as $batch) {
            $result=json_decode($batch['result_json'],true);
            if (($result['created_count']??0)>1 && in_array((int)$service['id'],$result['service_ids']??[],true)) { $ids=$result['service_ids']; break; }
        }
    }
    if (!$ids) return [$service];
    $stmt=$pdo->prepare('SELECT * FROM services WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).") AND status='SCHEDULED' AND patient_id=? AND carer_id=? AND substr(starts_at,12)=? AND substr(ends_at,12)=? ORDER BY id");
    $stmt->execute([...$ids,$service['patient_id'],$service['carer_id'],substr($service['starts_at'],11),substr($service['ends_at'],11)]);
    return $stmt->fetchAll();
}

function scheduling_plan(PDO $pdo,array $data): array
{
    if (($data['planning_mode']??'')==='copy_month') return planning_month($pdo,$data);
    $plan=scheduling_base_plan($pdo,$data);
    $existing=null;
    if ($plan['id']) { $stmt=$pdo->prepare('SELECT * FROM services WHERE id=?'); $stmt->execute([$plan['id']]); $existing=$stmt->fetch(); }
    $prices=planning_prices($pdo,$plan+['hourly_rate_special'=>$data['hourly_rate_special']??null]+($existing && (int)$existing['patient_id']===$plan['patient_id']?array_intersect_key($existing,array_flip(['client_rate_snapshot','client_special_snapshot'])):[]));
    $plan+=$prices;
    if (($data['edit_series']??'')==='1') {
        $plan['skip_conflicts']=false;
        if (!$existing || $existing['status']!=='SCHEDULED') throw new InvalidArgumentException('Só pode alterar em conjunto serviços agendados da mesma série.');
        $start=$plan['occurrences'][0]['starts_at']; $end=$plan['occurrences'][0]['ends_at'];
        $offset=(int)scheduling_date(substr($start,0,10))->diff(scheduling_date(substr($end,0,10)))->format('%r%a');
        $plan['occurrences']=[];
        foreach (planning_series($pdo,$existing) as $member) {
            $day=substr($member['starts_at'],0,10);
            $newStart=scheduling_local_time($day.' '.substr($start,11))->format('Y-m-d H:i:s');
            $newEnd=scheduling_local_time(scheduling_date($day)->modify('+'.$offset.' days')->format('Y-m-d').' '.substr($end,11))->format('Y-m-d H:i:s');
            // Only the hours propagate; each other day's client, carer, notes and prices stay intact.
            $entry=array_intersect_key($member,array_flip(['id','patient_id','carer_id','starts_at','ends_at','hourly_rate','hourly_rate_special','client_rate_snapshot','client_special_snapshot','observations','municipality'])); $entry['starts_at']=$newStart; $entry['ends_at']=$newEnd;
            if ((int)$member['id']===$plan['id']) $entry=array_merge($entry,$plan,['starts_at'=>$start,'ends_at'=>$end]);
            unset($entry['occurrences']);
            $plan['occurrences'][]=$entry;
        }
        if (!$plan['occurrences']) throw new InvalidArgumentException('Não existem outros serviços agendados nesta série.');
    } else {
        $slots=[['starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at'],'family'=>'slot-1']];
        if (!$plan['id'] && ($data['extra_times']??'')==='1') {
            for ($slot=2;$slot<=3;$slot++) {
                $start=$data['extra_start_'.$slot]??''; $end=$data['extra_end_'.$slot]??'';
                if ($start==='' && $end==='') continue;
                if (!preg_match('/^\d{2}:\d{2}$/D',$start) || !preg_match('/^\d{2}:\d{2}$/D',$end)) throw new InvalidArgumentException('Preencha a entrada e saída dos horários adicionais.');
                $date=substr($data['starts_at'],0,10);
                if ($end<=$start) throw new InvalidArgumentException('Os horários adicionais devem terminar depois da entrada, no mesmo dia.');
                $slots[]=['starts_at'=>$date.'T'.$start,'ends_at'=>$date.'T'.$end,'family'=>'slot-'.$slot];
            }
            if(count($slots)===1)throw new InvalidArgumentException('Preencha pelo menos um horário adicional.');
        }
        $entries=[];
        foreach ($slots as $slot) {
            $slotPlan=count($slots)===1?$plan:scheduling_base_plan($pdo,array_replace($data,$slot));
            foreach ($slotPlan['occurrences'] as $entry) { $entry['family']=$slot['family']; $entries[]=$entry; }
        }
        $plan['occurrences']=$entries;
    }
    return $plan;
}

function planning_month(PDO $pdo,array $data): array
{
    $source=$data['source_month']??'';
    if (!preg_match('/^\d{4}-\d{2}$/D',$source)) throw new InvalidArgumentException('Selecione o mês de origem.');
    $first=scheduling_date($source.'-01'); $next=$first->modify('+1 month'); $last=$next->modify('+1 month');
    $stmt=$pdo->prepare("SELECT * FROM services WHERE starts_at>=? AND starts_at<? AND status!='CANCELLED' ORDER BY starts_at,id");
    $stmt->execute([$first->format('Y-m-d'),$next->format('Y-m-d')]); $rows=$stmt->fetchAll();
    if (!$rows || count($rows)>5000) throw new InvalidArgumentException('Selecione um mês com 1 a 5000 serviços não cancelados.');
    $entries=[]; $unmatched=[];
    foreach ($rows as $row) {
        $start=scheduling_local_time($row['starts_at']); $end=scheduling_local_time($row['ends_at']);
        $ordinal=intdiv((int)$start->format('j')-1,7);
        $offset=((int)$start->format('N')-(int)$next->format('N')+7)%7+7*$ordinal;
        $target=$next->modify('+'.$offset.' days');
        if ($target >= $last) { $unmatched[]=['service_id'=>(int)$row['id'],'starts_at'=>$row['starts_at']]; continue; }
        $days=(int)scheduling_date($start->format('Y-m-d'))->diff(scheduling_date($end->format('Y-m-d')))->format('%r%a');
        $entry=$row; $entry['id']=0;
        $entry['starts_at']=scheduling_local_time($target->format('Y-m-d').' '.$start->format('H:i:s'))->format('Y-m-d H:i:s');
        $entry['ends_at']=scheduling_local_time($target->modify('+'.$days.' days')->format('Y-m-d').' '.$end->format('H:i:s'))->format('Y-m-d H:i:s');
        $entry['family']=$row['series_key']?:'source-'.$row['id']; unset($entry['series_key']);
        $entry=array_replace($entry,planning_prices($pdo,$entry)); $entries[]=$entry;
    }
    if (!$entries) throw new InvalidArgumentException('Não existem serviços com correspondência no mês seguinte.');
    return ['id'=>0,'patient_id'=>0,'carer_id'=>0,'hourly_rate'=>0,'observations'=>'','municipality'=>'all','skip_conflicts'=>false,
        'occurrences'=>$entries,'unmatched'=>$unmatched,'allow_unmatched'=>($data['allow_unmatched']??'')==='1','source_month'=>$source,'target_month'=>$next->format('Y-m')];
}
