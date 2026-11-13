<?php
layout('Confirmar segunda cuidadora', function () use ($result) { ?>
    <dialog open aria-labelledby="shared-title" class="card"><h2 id="shared-title">O cliente já tem cuidadora neste horário</h2>
    <?php foreach ($result['shared_visits'] as $visit): ?><p><?= e($visit['name']) ?> · <?= e($visit['starts_at']) ?> – <?= e($visit['ends_at']) ?></p><?php endforeach; ?>
    <form method="post"><?php foreach ($_POST as $key=>$value):
        if ($key === 'shared_confirmation') continue;
        foreach ((array)$value as $part): ?>
        <input type="hidden" name="<?= e($key.(is_array($value)?'[]':'')) ?>" value="<?= e((string)$part) ?>">
        <?php endforeach; endforeach; ?>
        <input type="hidden" name="shared_confirmation" value="<?= e($result['shared_confirmation']) ?>">
        <a class="btn btn-secondary" href="index.php?page=scales">Cancelar</a><button class="btn btn-primary">Prosseguir com duas cuidadoras</button>
    </form></dialog>
<?php });
