<?php
require_role(['ADMIN', 'EDITOR']);
$pdo = db();
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$version = $pdo->query('SELECT version FROM schema_migrations ORDER BY applied_at DESC LIMIT 1')->fetchColumn();
layout('Configurações - Care4All', function () use ($secure, $version) { ?>
    <div class="page-head"><div><h1>Configurações</h1><p class="muted">Care4All V2</p></div></div>
    <section class="list-panel">
        <div class="list-panel-head"><h2>Aplicação</h2></div>
        <dl class="settings-list">
            <dt>Empresa</dt><dd><?= e(COMPANY_NAME) ?></dd>
            <dt>Idioma</dt><dd>Português de Portugal</dd>
            <dt>Fuso horário</dt><dd><?= e(APP_TIMEZONE) ?></dd>
            <dt>Ligação</dt><dd><?= $secure ? 'HTTPS' : 'HTTP' ?></dd>
            <dt>Versão do esquema</dt><dd><?= e((string) $version) ?></dd>
            <dt>Base de dados</dt><dd>SQLite</dd>
        </dl>
    </section>
<?php });


