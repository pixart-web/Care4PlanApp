<?php
$user = require_auth();
$page = $_GET['page'] ?? '';
if ($page === 'settings') {
    require_role(['ADMIN', 'EDITOR']);
}
$titles = ['settings' => 'Configurações'];
$title = $titles[$page] ?? 'Página';

layout($title . ' - Care4All', function () use ($title) { ?>
    <div class="card">
        <h1><?= e($title) ?></h1>
        <p class="muted" style="margin-top:8px">Esta area esta preparada para receber a funcionalidade numa proxima iteracao.</p>
    </div>
<?php });


