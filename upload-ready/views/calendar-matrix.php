<?php
$rowByCarer=($_GET['rows']??'clients')==='carers';
$matrixPatients = [];
$matrixServices = [];
foreach ($servicesByDate as $date => $services) {
    foreach ($services as $service) {
        $rowId=$rowByCarer?$service['carer_id']:$service['patient_id'];
        $matrixPatients[$rowId]=$rowByCarer?$service['carer_name']:$service['patient_name'];
        if($rowByCarer)$service['_display_person']=$service['patient_name'];
        $matrixServices[$rowId][$date][]=$service;
    }
}
if (!$rowByCarer && !$selectedStatus && !$search) {
    foreach ($patients as $patient) {
        if ((!$selectedPatient || $selectedPatient === (int)$patient['id']) && ($selectedMunicipality === 'all' || ($selectedMunicipality === '__unassigned' ? empty($patient['municipality']) : ($patient['municipality'] ?? '') === $selectedMunicipality))) $matrixPatients[$patient['id']] = $patient['name'];
    }
}
if($rowByCarer && !$selectedStatus && !$search)foreach($carers as $carer)if(!$selectedCarer || $selectedCarer===(int)$carer['id'])$matrixPatients[$carer['id']]=$carer['name'];
asort($matrixPatients, SORT_NATURAL | SORT_FLAG_CASE);
