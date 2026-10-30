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
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main class="login-page">
    <section class="login-card" aria-labelledby="login-title">
        <div class="login-brand">
            <img class="brand-logo-large" src="assets/logo-care4all.png" alt="<?= APP_NAME ?>">
            <h1 class="login-title" id="login-title"><?= e(APP_NAME) ?></h1>
            <p class="subtitle">Gestão de escalas de apoio domiciliário</p>
        </div>
        <?php if ($flashError): ?><div class="alert alert-error" role="alert" style="margin-top:18px"><?= e($flashError) ?></div><?php endif; ?>
        <form method="post" style="margin-top:24px">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="login">
            <label class="field"><span class="label">Nome de utilizador ou email</span><input class="input" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus></label>
            <label class="field"><span class="label">Palavra-passe</span><input class="input" name="password" type="password" autocomplete="current-password" required></label>
            <button class="btn btn-primary" style="width:100%" type="submit">Entrar</button>
        </form>
        <p class="login-company"><?= e(COMPANY_NAME) ?></p>
    </section>
</main>
</body>
</html>


