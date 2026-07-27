<?php
$active = $_GET['page'] ?? 'dashboard';
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
?>
<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-head">
            <div class="brand">
                <img class="brand-logo" src="assets/logo-care4all.png" alt="<?= APP_NAME ?>">
                <div>
                    <div><?= APP_NAME ?></div>
                    <div class="subtitle"><?= COMPANY_NAME ?></div>
                </div>
            </div>
        </div>
        <nav class="nav">
            <?php foreach ($nav as $key => $label): ?>
                <a class="<?= $active === $key ? 'active' : '' ?>" href="index.php?page=<?= e($key) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <div class="session-bar">
                <strong>Olá, <?= e($user['name'] ?? '') ?></strong>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn btn-secondary" type="submit">Sair</button>
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
        <div class="session-bar mobile-session">
            <strong>Olá, <?= e($user['name'] ?? '') ?></strong>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-secondary" type="submit">Sair</button>
            </form>
        </div>
    </header>

    <main class="main">
        <?php if ($flash): ?><div class="alert alert-ok"><?= e($flash) ?></div><?php endif; ?>
        <?php if ($flashError): ?><div class="alert alert-error"><?= e($flashError) ?></div><?php endif; ?>
        <?php $content(); ?>
    </main>

    <nav class="bottom-nav">
        <?php foreach ($bottom as $key => $label): ?>
            <a class="<?= $active === $key ? 'active' : '' ?>" href="index.php?page=<?= e($key) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</div>
<script src="assets/app.js"></script>
</body>
</html>

