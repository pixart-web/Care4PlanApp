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
$careMessages = [
    'O teu cuidado faz a diferença, mesmo nos gestos mais pequenos.',
    'Hoje, oferece um sorriso. Pode ser o abraço de que alguém precisa.',
    'Quem cuida também merece carinho. Guarda um momento para ti.',
    'Um dia de cada vez, com calma, presença e coração.',
    'Por trás de cada dia bem organizado, há uma equipa que se importa.',
    'A tua presença traz conforto. Obrigada por estares desse lado.',
    'Não precisas de fazer tudo sozinha. Juntas, cuidamos melhor.',
    'Cada escuta atenta é uma forma bonita de cuidar.',
    'Celebra as pequenas conquistas. Elas também fazem grandes dias.',
    'Que hoje haja espaço para a gentileza, para os outros e para ti.'
];
$messageIndex = (int) floor(strtotime('today') / 86400) % count($careMessages);
$careHour = (int) date('G');
$careGreeting = $careHour < 12 ? 'Bom dia' : ($careHour < 19 ? 'Boa tarde' : 'Boa noite');
$careSun = [];
for ($offset = 0; $offset < 8; $offset++) {
    $sunDate = strtotime('today +' . $offset . ' days');
    $sun = date_sun_info($sunDate, 37.15, -8.52);
    $careSun[date('Y-m-d', $sunDate)] = [$sun['sunrise'] * 1000, $sun['sunset'] * 1000];
}
$careIsDay = time() * 1000 >= $careSun[date('Y-m-d')][0] && time() * 1000 < $careSun[date('Y-m-d')][1];
?>
<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="assets/app.css?v=20261103-ui2">
    <link rel="stylesheet" href="assets/atelier.css?v=20261103-ui2">
    <link rel="stylesheet" href="assets/care-ui.css?v=20261103-ui2">
    <script src="assets/vendor/lucide-0.468.0.min.js?v=20261103-ui2" defer></script>
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
        <div class="workspace-head"><div class="workspace-context"><span><?= $isCarer ? 'Área da cuidadora' : 'Gestão de cuidados' ?></span><i data-lucide="chevron-right" aria-hidden="true"></i><strong><?= e($contextLabel) ?></strong></div><div class="care-day-widget" aria-label="Hora e previsão em Portimão">
            <div class="care-clock"><span class="care-sky" data-care-sky aria-label="<?= $careIsDay ? 'Dia em Portimão' : 'Noite em Portimão' ?>"><i data-lucide="<?= $careIsDay ? 'sun' : 'moon' ?>" aria-hidden="true"></i></span><div><time data-care-clock datetime="<?= date('c') ?>"><?= date('H:i') ?></time><span data-care-date><?= date('d/m/Y') ?> · Lisboa</span></div></div>
            <details class="care-weather"><summary><i data-lucide="thermometer" aria-hidden="true"></i><span>Portimão · mín/máx <strong data-care-temperature>Ver previsão</strong></span></summary><div class="care-weather-panel"><strong>Temperatura prevista para hoje</strong><p data-care-forecast aria-live="polite">A carregar a previsão…</p><small>Mínima e máxima diárias, não temperatura atual.</small><a href="https://www.ipma.pt/pt/otempo/prev.localidade.hora/#Faro&Portim%C3%A3o" target="_blank" rel="noopener noreferrer">Fonte: IPMA</a><button class="btn btn-secondary" type="button" data-weather-retry>Atualizar previsão</button></div></details>
        </div></div>
        <?php if ($flash): ?><div class="alert alert-ok" role="status"><?= e($flash) ?></div><?php endif; ?>
        <?php if ($flashError): ?><div class="alert alert-error" role="alert"><?= e($flashError) ?></div><?php endif; ?>
        <?php if (in_array($active, ['dashboard', 'scales'], true)): ?>
        <section class="care-welcome" aria-label="Bem-vindo à Care4All">
            <div class="care-welcome-copy"><h2><span data-care-greeting><?= e($careGreeting) ?></span>, <?= e(explode(' ', trim($user['name'] ?? ''))[0]) ?></h2><p>Cuidar. Organizar. Fazer a diferença.</p></div>
            <img src="assets/care-at-home.webp" alt="Cuidadora a acompanhar uma senhora em casa">

        </section>
        <?php endif; ?>
        <section class="care-message" aria-label="Uma mensagem para ti"><span class="care-message-icon"><i data-lucide="heart-handshake" aria-hidden="true"></i></span><div><span class="care-message-label">Um carinho para o teu dia</span><p data-care-message aria-live="polite"><?= e($careMessages[$messageIndex]) ?></p></div><button type="button" class="icon-button" data-care-next aria-label="Ler outra mensagem" title="Ler outra mensagem"><i data-lucide="refresh-cw" aria-hidden="true"></i></button></section>
        <?php $content(); ?>
    </main>

    <nav class="bottom-nav" aria-label="Acesso rápido">
        <?php foreach ($bottom as $key => $label): ?>
            <a class="<?= $active === $key ? 'active' : '' ?>" <?= $active === $key ? 'aria-current="page"' : '' ?> href="index.php?page=<?= e($key) ?>"><i data-lucide="<?= e($navIcons[$key]) ?>" aria-hidden="true"></i><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
    </nav>
</div>
<script type="application/json" id="care-day-data"><?= json_encode(['messages' => $careMessages, 'index' => $messageIndex, 'sun' => $careSun], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/app.js?v=20261103-ui2" defer></script>
<script src="assets/care-day.js?v=20261103-ui2" defer></script>
</body>
</html>

