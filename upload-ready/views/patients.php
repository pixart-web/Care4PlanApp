<?php
require_role(['ADMIN', 'EDITOR']);
$pdo = db();
$patients = $pdo->query('SELECT * FROM patients ORDER BY name')->fetchAll();
$patientFields = [
    'name', 'birth_date', 'age', 'tax_number', 'sns_number', 'address', 'postal_code', 'city', 'phone', 'email',
    'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'emergency_contact_email',
    'doctor_name', 'health_unit', 'diagnoses', 'allergies', 'medication', 'medication_schedule', 'mobility',
    'cognition', 'fall_risk', 'incontinence', 'food_restrictions', 'care_needs', 'daily_routine', 'preferences',
    'avoid', 'special_instructions', 'home_access', 'house_key', 'pets', 'equipment', 'status', 'support_start_date',
    'contracted_hours', 'municipality', 'client_special_rate', 'client_hourly_rate', 'service_type', 'admin_notes', 'observations'
];

function patient_form_fields(array $patients = []): void
{ ?>
    <?php profile_photo_field('patient'); ?>
    <div class="form-section"><h3>Dados pessoais</h3><div class="form-grid">
        <label class="field"><span class="label">Nome</span><input class="input" name="name" required></label>
        <label class="field"><span class="label">Data de nascimento</span><input class="input" name="birth_date" type="date"></label>
        <label class="field"><span class="label">Idade</span><input class="input" name="age" type="number" min="0" max="120"></label>
        <label class="field"><span class="label">NIF</span><input class="input" name="tax_number"></label>
        <label class="field"><span class="label">Número do SNS</span><input class="input" name="sns_number"></label>
        <label class="field"><span class="label">Telefone</span><input class="input" name="phone"></label>
        <label class="field"><span class="label">Email</span><input class="input" name="email" type="email"></label>
    </div></div>

    <label class="field"><span class="label">Concelho</span><select class="input" name="municipality"><option value="">Sem concelho</option><?php foreach (scheduling_municipalities() as $place): ?><option><?= e($place) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span class="label">Valor/hora — fins de semana e feriados</span><input class="input" name="client_special_rate" type="number" min="0" max="10000" step="0.01"><span class="subtitle">Em branco: aplica-se o valor dos dias úteis.</span></label>
    <div class="form-section"><h3>Morada</h3><div class="form-grid">
        <label class="field form-span-2"><span class="label">Morada</span><input class="input" name="address" required></label>
        <label class="field"><span class="label">Código postal</span><input class="input" name="postal_code"></label>
        <label class="field"><span class="label">Localidade</span><input class="input" name="city"></label>
    </div></div>

    <div class="form-section"><h3>Contacto de emergência</h3><div class="form-grid">
        <label class="field"><span class="label">Nome do contacto</span><input class="input" name="emergency_contact_name"></label>
        <label class="field"><span class="label">Grau de parentesco</span><input class="input" name="emergency_contact_relationship"></label>
        <label class="field"><span class="label">Telefone</span><input class="input" name="emergency_contact_phone"></label>
        <label class="field"><span class="label">Email</span><input class="input" name="emergency_contact_email" type="email"></label>
    </div></div>

    <div class="form-section"><h3>Saúde e mobilidade</h3><div class="form-grid">
        <label class="field"><span class="label">Médico assistente</span><input class="input" name="doctor_name"></label>
        <label class="field"><span class="label">Centro de saúde/hospital</span><input class="input" name="health_unit"></label>
        <label class="field"><span class="label">Mobilidade</span><select class="input" name="mobility"><option value="">Selecionar</option><option>Autónomo</option><option>Bengala</option><option>Andarilho</option><option>Cadeira de rodas</option><option>Acamado</option></select></label>
        <label class="field"><span class="label">Cognição</span><select class="input" name="cognition"><option value="">Selecionar</option><option>Orientado</option><option>Confusão</option><option>Demência</option><option>Alzheimer</option><option>Outra</option></select></label>
        <label class="field"><span class="label">Risco de queda</span><select class="input" name="fall_risk"><option value="">Selecionar</option><option>Baixo</option><option>Médio</option><option>Alto</option></select></label>
        <label class="field"><span class="label">Incontinência</span><select class="input" name="incontinence"><option value="">Selecionar</option><option>Não</option><option>Urinária</option><option>Fecal</option><option>Mista</option></select></label>
        <label class="field form-span-2"><span class="label">Diagnósticos relevantes</span><textarea class="input textarea" name="diagnoses" rows="3"></textarea></label>
        <label class="field form-span-2"><span class="label">Alergias</span><textarea class="input textarea" name="allergies" rows="3"></textarea></label>
        <label class="field form-span-2"><span class="label">Medicação habitual</span><textarea class="input textarea" name="medication" rows="3"></textarea></label>
        <label class="field form-span-2"><span class="label">Horários de medicação</span><textarea class="input textarea" name="medication_schedule" rows="3"></textarea></label>
        <label class="field form-span-2"><span class="label">Restrições alimentares</span><textarea class="input textarea" name="food_restrictions" rows="3"></textarea></label>
    </div></div>

    <div class="form-section"><h3>Cuidados necessários</h3><div class="form-grid">
        <label class="field form-span-2"><span class="label">Tipo de apoio necessário</span><textarea class="input textarea" name="care_needs" rows="3"></textarea></label>
        <label class="field form-span-2"><span class="label">Rotina diária</span><textarea class="input textarea" name="daily_routine" rows="3"></textarea></label>
        <label class="field form-span-2"><span class="label">Preferências do cliente</span><textarea class="input textarea" name="preferences" rows="3"></textarea></label>
        <label class="field form-span-2"><span class="label">O que evitar</span><textarea class="input textarea" name="avoid" rows="3"></textarea></label>
        <label class="field form-span-2"><span class="label">Instruções especiais</span><textarea class="input textarea" name="special_instructions" rows="3"></textarea></label>
        <label class="field"><span class="label">Temos a chave de casa?</span><select class="input" name="house_key"><option value="">Por confirmar</option><option>Sim</option><option>Não</option></select></label>
        <label class="field"><span class="label">Acesso à habitação</span><input class="input" name="home_access"></label>
        <label class="field"><span class="label">Animais em casa</span><input class="input" name="pets"></label>
        <label class="field form-span-2"><span class="label">Equipamentos</span><textarea class="input textarea" name="equipment" rows="3"></textarea></label>
    </div></div>

    <div class="form-section"><h3>Dados administrativos</h3><div class="form-grid">
        <label class="field"><span class="label">Estado</span><select class="input" name="status"><option>Ativo</option><option>Suspenso</option><option>Terminado</option></select></label>
        <label class="field"><span class="label">Data de início do apoio</span><input class="input" name="support_start_date" type="date"></label>
        <label class="field"><span class="label">Horas contratadas</span><input class="input" name="contracted_hours"></label>
        <label class="field"><span class="label">Valor/hora — dias úteis</span><input class="input" name="client_hourly_rate" type="number" min="0" step="0.01"></label>
        <label class="field"><span class="label">Tipo de serviço</span><select class="input" name="service_type"><option value="">Selecionar</option><option>Regular</option><option>Pontual</option><option>Noite</option><option>Fim de semana</option><option>24h</option></select></label>
        <label class="field form-span-2"><span class="label">Observações administrativas</span><textarea class="input textarea" name="admin_notes" rows="3"></textarea></label>
        <label class="field form-span-2"><span class="label">Observações gerais</span><textarea class="input textarea" name="observations" rows="4"></textarea></label>
    </div></div>
<?php }

layout('Clientes - Care4All', function () use ($patients, $patientFields) { ?>
    <div class="page-head"><div><h1>Clientes</h1><p class="muted">Clientes acompanhados pela equipa de apoio domiciliário.</p></div></div>

    <section class="card action-panel"><div class="action-panel-text"><h2>Gestão de clientes</h2><p class="muted">Adicione, altere ou apague clientes através das opções abaixo.</p></div><div class="btn-row action-buttons"><button class="btn btn-primary" type="button" data-open-modal="add-patient-modal">Adicionar</button><button class="btn btn-secondary" type="button" data-open-modal="edit-patient-modal" <?= empty($patients) ? 'disabled' : '' ?>>Alterar</button><button class="btn btn-danger" type="button" data-open-modal="delete-patient-modal" <?= empty($patients) ? 'disabled' : '' ?>>Apagar</button></div></section>

    <section class="card list-panel"><div class="list-panel-head"><div><h2>Lista de clientes</h2><p class="muted"><?= count($patients) ?> cliente<?= count($patients) === 1 ? '' : 's' ?> registado<?= count($patients) === 1 ? '' : 's' ?>.</p></div></div>
        <?php if (empty($patients)): ?><div class="empty-state"><strong>Ainda não existem clientes.</strong><p class="muted">Clique em Adicionar para criar o primeiro registo.</p></div><?php else: ?>
            <div class="table-wrap desktop-table"><table><thead><tr><th>Nome</th><th>Morada</th><th>Contacto</th><th>Mobilidade</th><th>Estado</th><th>Contacto de emergência</th><th>Chave de casa</th></tr></thead><tbody><?php foreach ($patients as $patient): ?><tr><td><?php profile_photo_thumb('patient',(int)$patient['id']); ?><strong><?= e($patient['name']) ?></strong> <a class="btn btn-secondary" href="index.php?page=scales&amp;patient_id=<?= $patient['id'] ?>&amp;municipality=all&amp;new_service=1">Agendar serviço</a></td><td><a href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode(implode(', ',array_filter([$patient['address'],$patient['postal_code'],$patient['city']]))) ?>" target="_blank" rel="noopener noreferrer"><?= e($patient['address']) ?> · Google Maps</a></td><td><?= e($patient['phone'] ?? '') ?: '-' ?></td><td><?= e($patient['mobility'] ?? '') ?: '-' ?></td><td><span class="badge"><?= e($patient['status'] ?? '') ?: 'Ativo' ?></span></td><td><?= e(trim(($patient['emergency_contact_name'] ?? '').' '.($patient['emergency_contact_phone'] ?? ''))) ?: 'Por indicar' ?></td><td><?= e($patient['house_key'] ?: 'Por confirmar') ?></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="mobile-cards"><?php foreach ($patients as $patient): ?><article class="mobile-item"><?php profile_photo_thumb('patient',(int)$patient['id']); ?><strong><?= e($patient['name']) ?></strong> <a class="btn btn-secondary" href="index.php?page=scales&amp;patient_id=<?= $patient['id'] ?>&amp;municipality=all&amp;new_service=1">Agendar serviço</a><div class="mobile-line"><span>Morada</span><span><a href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode(implode(', ',array_filter([$patient['address'],$patient['postal_code'],$patient['city']]))) ?>" target="_blank" rel="noopener noreferrer"><?= e($patient['address']) ?> · Google Maps</a></span></div><div class="mobile-line"><span>Telefone</span><span><?= e($patient['phone'] ?? '') ?: '-' ?></span></div><div class="mobile-line"><span>Mobilidade</span><span><?= e($patient['mobility'] ?? '') ?: '-' ?></span></div><div class="mobile-line"><span>Estado</span><span><?= e($patient['status'] ?? '') ?: 'Ativo' ?></span></div><div class="mobile-line"><span>Emergência</span><span><?= e(trim(($patient['emergency_contact_name'] ?? '').' '.($patient['emergency_contact_phone'] ?? ''))) ?: 'Por indicar' ?></span></div><div class="mobile-line"><span>Chave de casa</span><span><?= e($patient['house_key'] ?: 'Por confirmar') ?></span></div></article><?php endforeach; ?></div>
        <?php endif; ?>
    </section>

    <div class="modal-backdrop" id="add-patient-modal" hidden><section class="modal-card modal-card-lg" role="dialog" aria-modal="true" aria-labelledby="add-patient-title"><div class="modal-head"><div><h2 id="add-patient-title">Adicionar cliente</h2><p class="muted">Registe a ficha completa do cliente.</p></div><button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button></div><form method="post" enctype="multipart/form-data" class="modal-body"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="save_patient"><input type="hidden" name="id" value=""><?php patient_form_fields(); ?><div class="btn-row modal-actions"><button class="btn btn-primary">Adicionar</button><button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button></div></form></section></div>

    <div class="modal-backdrop" id="edit-patient-modal" hidden><section class="modal-card modal-card-lg" role="dialog" aria-modal="true" aria-labelledby="edit-patient-title"><div class="modal-head"><div><h2 id="edit-patient-title">Alterar cliente</h2><p class="muted">Selecione um cliente e atualize a ficha.</p></div><button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button></div><form method="post" class="modal-body"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="save_patient"><input type="hidden" name="id" value=""><label class="field"><span class="label">Selecionar cliente</span><select class="input" data-patient-selector required><option value="">Escolher cliente</option><?php foreach ($patients as $patient): ?><option value="<?= $patient['id'] ?>" data-has-photo="<?= has_profile_photo('patient',(int)$patient['id'])?'1':'0' ?>" <?php foreach ($patientFields as $field): ?>data-<?= str_replace('_', '-', $field) ?>="<?= e((string) ($patient[$field] ?? '')) ?>" <?php endforeach; ?>><?= e($patient['name']) ?> - <?= e($patient['address']) ?></option><?php endforeach; ?></select></label><?php patient_form_fields(); ?><div class="btn-row modal-actions"><button class="btn btn-primary">Alterar</button><button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button></div></form></section></div>

    <div class="modal-backdrop" id="delete-patient-modal" hidden><section class="modal-card modal-card-sm" role="dialog" aria-modal="true" aria-labelledby="delete-patient-title"><div class="modal-head"><div><h2 id="delete-patient-title">Apagar cliente</h2><p class="muted">Selecione o cliente que pretende apagar.</p></div><button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button></div><form method="post" class="modal-body" data-confirm="Apagar este cliente?"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="delete_patient"><label class="field"><span class="label">Cliente</span><select class="input" name="id" required><option value="">Escolher cliente</option><?php foreach ($patients as $patient): ?><option value="<?= $patient['id'] ?>"><?= e($patient['name']) ?> - <?= e($patient['address']) ?></option><?php endforeach; ?></select></label><div class="btn-row modal-actions"><button class="btn btn-danger">Apagar</button><button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button></div></form></section></div>
<?php });
