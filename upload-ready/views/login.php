<?php
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/app.css?v=20261103-ui2">
    <link rel="stylesheet" href="assets/atelier.css?v=20261103-ui2">
    <link rel="stylesheet" href="assets/care-ui.css?v=20261103-ui2">
    <script src="assets/vendor/lucide-0.468.0.min.js" defer></script>
    <script src="assets/app.js?v=20261103-ui2" defer></script>
</head>
<body class="atelier-login">
<main class="login-page">
    <section class="login-card" aria-labelledby="login-title">
        <div class="login-brand">
            <img class="brand-logo-large" src="assets/logo-care4all.png" alt="<?= APP_NAME ?>">
            <div class="login-name"><?= e(APP_NAME) ?></div>
            <p class="subtitle">Gestão de escalas de apoio domiciliário</p>
        </div>
        <h1 class="login-title" id="login-title">Entrar</h1>
        <?php if ($flashError): ?><div class="alert alert-error" role="alert"><?= e($flashError) ?></div><?php endif; ?>
        <form method="post" class="login-form">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="login">
            <label class="field"><span class="label">Nome de utilizador ou email</span><input class="input" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus></label>
            <label class="field"><span class="label">Palavra-passe</span><input class="input" name="password" type="password" autocomplete="current-password" required></label>
            <button class="btn btn-primary" type="submit"><span>Entrar</span><i data-lucide="arrow-right" aria-hidden="true"></i></button>
        </form>
        <p class="login-company"><?= e(COMPANY_NAME) ?></p>
    </section>
</main>
</body>
</html>


