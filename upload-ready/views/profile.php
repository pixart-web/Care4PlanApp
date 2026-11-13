<?php
$user = require_auth();
$stmt = db()->prepare('SELECT * FROM carers WHERE id = ?');
$stmt->execute([$user['carer_id']]);
$carer = $user['role'] === 'CARER' ? $stmt->fetch() : null;

layout('Perfil - Care4All', function () use ($carer) { ?>
    <div class="page-head"><div><h1>Perfil</h1><p class="muted">Atualiza os teus dados de contacto.</p></div></div>
    <?php if ($carer): ?><div class="card" style="max-width:680px">
        <form method="post" enctype="multipart/form-data">
            <?php profile_photo_field('carer', (int)$carer['id']); ?>
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="save_profile">
            <label class="field"><span class="label">Nome</span><input class="input" value="<?= e($carer['name']) ?>" disabled></label>
            <input type="hidden" name="age" value="<?= e((string)$carer['age']) ?>"><label class="field"><span class="label">Data de nascimento</span><input class="input" name="birth_date" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($carer['birth_date'] ?? '') ?>" required></label>
            <label class="field"><span class="label">Telefone</span><input class="input" name="phone" value="<?= e($carer['phone']) ?>" required></label>
            <label class="field"><span class="label">Email</span><input class="input" name="email" type="email" value="<?= e($carer['email']) ?>"></label>
            <button class="btn btn-primary">Guardar</button>
        </form>
    </div>
    <?php endif; ?>
    <section class="card"><h2>Alterar palavra-passe</h2><form method="post">
        <input type="hidden" name="action" value="change_password"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label class="field">Palavra-passe atual<input class="input" type="password" name="current_password" autocomplete="current-password" required></label>
        <label class="field">Nova palavra-passe (mínimo 12 caracteres)<input class="input" type="password" name="new_password" autocomplete="new-password" minlength="12" required></label>
        <label class="field">Confirmar nova palavra-passe<input class="input" type="password" name="confirm_password" autocomplete="new-password" required></label>
        <button class="btn btn-primary">Alterar palavra-passe</button>
    </form></section>
<?php });

