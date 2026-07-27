<?php
$user = require_role(['CARER']);
$stmt = db()->prepare('SELECT * FROM carers WHERE id = ?');
$stmt->execute([$user['carer_id']]);
$carer = $stmt->fetch();

layout('Perfil - Care4All', function () use ($carer) { ?>
    <div class="page-head"><div><h1>Perfil</h1><p class="muted">Atualiza os teus dados de contacto.</p></div></div>
    <div class="card" style="max-width:680px">
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="save_profile">
            <label class="field"><span class="label">Nome</span><input class="input" value="<?= e($carer['name']) ?>" disabled></label>
            <label class="field"><span class="label">Idade</span><input class="input" name="age" type="number" min="16" max="100" value="<?= e((string) $carer['age']) ?>"></label>
            <label class="field"><span class="label">Telefone</span><input class="input" name="phone" value="<?= e($carer['phone']) ?>" required></label>
            <label class="field"><span class="label">Email</span><input class="input" name="email" type="email" value="<?= e($carer['email']) ?>"></label>
            <button class="btn btn-primary">Guardar</button>
        </form>
    </div>
<?php });

