<?php
$active = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'dashboard';
$flash = $_SESSION['flash'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_error']);

$isCarer = $user && $user['role'] === 'CARER';
$nav = $isCarer
    ? ['dashboard' => 'Dashboard', 'scales' => 'Escalas', 'pocket' => 'Pocket', 'profile' => 'Perfil', 'reports' => 'Relatórios']
    : ['dashboard' => 'Dashboard', 'scales' => 'Escalas', 'carers' => 'Cuidadoras', 'patients' => 'Utentes', 'reports' => 'Relatórios', 'settings' => 'Configurações'];
if ($user && $user['role'] === 'ADMIN') {
    $nav = array_slice($nav, 0, 4, true) + ['users' => 'Utilizadores', 'finances' => 'Finanças'] + array_slice($nav, 4, null, true);
}
$bottom = $isCarer
    ? ['dashboard' => 'Início', 'scales' => 'Escalas', 'pocket' => 'Pocket', 'profile' => 'Perfil']
    : ['dashboard' => 'Início', 'scales' => 'Escalas', 'carers' => 'Equipa', 'patients' => 'Utentes'];
$navIcons = ['dashboard' => 'layout-dashboard', 'scales' => 'calendar-days', 'pocket' => 'wallet', 'profile' => 'user-round', 'reports' => 'notebook-pen', 'carers' => 'users-round', 'patients' => 'heart-handshake', 'settings' => 'settings-2', 'users' => 'contact-round', 'finances' => 'chart-no-axes-combined'];
$contextLabel = $nav[$active] ?? $title;
?>
<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/atelier.css">
    <script src="assets/vendor/lucide-0.468.0.min.js" defer></script>
</head>
<body class="atelier-app" data-page="<?= e($active) ?>">
<a class="skip-link" href="#main-content">Saltar para o conteúdo</a>
<div class="app-shell">
    <aside class="sidebar" aria-label="Menu principal">
        <div class="sidebar-head">
            <div class="brand">
                <img class="brand-logo" src="assets/logo-care4all.png" alt="<?= APP_NAME ?>">
                <div>
                    <div><?= APP_NAME ?></div>
                    <div class="subtitle"><?= COMPANY_NAME ?></div>
                </div>
            </div>
        </div>
        <nav class="nav" aria-label="Navegação principal">
            <?php foreach ($nav as $key => $label): ?>
                <a class="<?= $active === $key ? 'active' : '' ?>" <?= $active === $key ? 'aria-current="page"' : '' ?> href="index.php?page=<?= e($key) ?>"><i data-lucide="<?= e($navIcons[$key]) ?>" aria-hidden="true"></i><span><?= e($label) ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <div class="session-bar">
                <strong title="Olá, <?= e($user['name'] ?? '') ?>">Olá, <?= e($user['name'] ?? '') ?></strong>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn btn-secondary session-logout" type="submit" aria-label="Sair" title="Sair"><i data-lucide="log-out" aria-hidden="true"></i><span class="sr-only">Sair</span></button>
                </form>
            </div>
        </div>
    </aside>

    <header class="top-mobile">
        <div class="mobile-brand">
            <img src="assets/logo-care4all.png" alt="<?= APP_NAME ?>">
            <div>
                <strong><?= APP_NAME ?></strong>
            </div>
        </div>
        <button class="navigation-toggle" type="button" aria-label="Abrir menu de navegação" aria-expanded="false" aria-controls="mobile-navigation" data-navigation-toggle><i data-lucide="menu" aria-hidden="true"></i></button>
    </header>
    <nav id="mobile-navigation" class="mobile-navigation" aria-label="Todas as páginas" hidden>
        <div class="mobile-user"><strong><?= e($user['name'] ?? '') ?></strong><span class="muted"><?= $isCarer ? 'Cuidadora' : ($user['role'] === 'ADMIN' ? 'Administração' : 'Coordenação') ?></span></div>
        <?php foreach ($nav as $key => $label): ?><a class="<?= $active === $key ? 'active' : '' ?>" <?= $active === $key ? 'aria-current="page"' : '' ?> href="index.php?page=<?= e($key) ?>"><i data-lucide="<?= e($navIcons[$key]) ?>" aria-hidden="true"></i><span><?= e($label) ?></span></a><?php endforeach; ?>
        <form method="post"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="logout"><button class="btn btn-secondary" type="submit">Sair</button></form>
    </nav>

    <main class="main" id="main-content" tabindex="-1">
        <div class="workspace-head"><div class="workspace-context"><span><?= $isCarer ? 'Área da cuidadora' : 'Gestão de cuidados' ?></span><i data-lucide="chevron-right" aria-hidden="true"></i><strong><?= e($contextLabel) ?></strong></div><span class="workspace-company"><?= e(COMPANY_NAME) ?></span></div>
        <?php if ($flash): ?><div class="alert alert-ok" role="status"><?= e($flash) ?></div><?php endif; ?>
        <?php if ($flashError): ?><div class="alert alert-error" role="alert"><?= e($flashError) ?></div><?php endif; ?>
        <?php $content(); ?>
    </main>

    <nav class="bottom-nav" aria-label="Acesso rápido">
        <?php foreach ($bottom as $key => $label): ?>
            <a class="<?= $active === $key ? 'active' : '' ?>" <?= $active === $key ? 'aria-current="page"' : '' ?> href="index.php?page=<?= e($key) ?>"><i data-lucide="<?= e($navIcons[$key]) ?>" aria-hidden="true"></i><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
    </nav>
</div>
<script src="assets/app.js" defer></script>
</body>
</html>

