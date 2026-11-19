<?php
$user = require_role(['CARER']);
$stmt = db()->prepare("SELECT DISTINCT patients.* FROM patients JOIN services ON services.patient_id = patients.id WHERE services.carer_id = ? AND services.status != 'CANCELLED' AND COALESCE(patients.status, '') NOT IN ('Suspenso', 'Terminado') ORDER BY patients.name");
$stmt->execute([(int) $user['carer_id']]);
$patients = $stmt->fetchAll();

function carer_patient_value(mixed $value): string
{
    $text = trim((string) $value);
    return $text === '' ? 'Não indicado' : $text;
}

layout('Os meus clientes - Care4All', function () use ($patients) { ?>
    <link rel="stylesheet" href="assets/atelier-scales.css?v=20261119">
    <div class="page-head"><div><p class="eyebrow">Informação de apoio</p><h1>Os meus clientes</h1><p class="muted">Dados dos clientes associados às suas escalas. Utilize esta informação apenas no âmbito dos cuidados.</p></div></div>
    <?php if (!$patients): ?><div class="card empty-state"><h2>Sem clientes associados</h2><p class="muted">Os clientes aparecem aqui quando lhe é atribuída uma escala.</p></div><?php endif; ?>
    <div class="grid patient-care-grid">
        <?php foreach ($patients as $patient): ?>
            <article class="card" id="client-<?= (int)$patient['id'] ?>">
                <header class="section-head"><div><h2><?= e($patient['name']) ?></h2><p class="muted"><?= e(carer_patient_value($patient['municipality'] ?? $patient['city'] ?? '')) ?></p></div><?php if (($patient['house_key'] ?? '') === 'Sim'): ?><span class="badge">🔑 Empresa tem chave</span><?php endif; ?></header>
                <dl class="service-details-list">
                    <dt>Morada</dt><dd><?= e(carer_patient_value($patient['address'] ?? '')) ?></dd>
                    <dt>Telefone</dt><dd><?= e(carer_patient_value($patient['phone'] ?? '')) ?></dd>
                    <dt>Contacto de emergência</dt><dd><?= e(carer_patient_value(trim(($patient['emergency_contact_name'] ?? '') . ' · ' . ($patient['emergency_contact_phone'] ?? ''), ' ·'))) ?></dd>
                    <dt>Mobilidade</dt><dd><?= e(carer_patient_value($patient['mobility'] ?? '')) ?></dd>
                    <dt>Cognição</dt><dd><?= e(carer_patient_value($patient['cognition'] ?? '')) ?></dd>
                    <dt>Risco de queda</dt><dd><?= e(carer_patient_value($patient['fall_risk'] ?? '')) ?></dd>
                    <dt>Incontinência</dt><dd><?= e(carer_patient_value($patient['incontinence'] ?? '')) ?></dd>
                    <dt>Animais</dt><dd><?= e(carer_patient_value($patient['pets'] ?? '')) ?></dd>
                </dl>
                <?php foreach ([
                    'Rotina do cliente' => 'daily_routine', 'Observações' => 'observations',
                    'Diagnósticos relevantes' => 'diagnoses', 'Alergias' => 'allergies', 'Medicação habitual' => 'medication',
                    'Restrições alimentares' => 'food_restrictions'
                ] as $label => $field): $value = trim((string)($patient[$field] ?? '')); if ($value === '') continue; ?>
                    <section class="detail-notes"><h3><?= e($label) ?></h3><p><?= nl2br(e($value)) ?></p></section>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php });
