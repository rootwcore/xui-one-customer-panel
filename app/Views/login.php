<?php
use XuiPanel\Core\Csrf;
$flashMessages = consume_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Sign In - <?= e(config('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('assets/img/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
    <script defer src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
</head>
<body class="login-body">
<div class="page-loader" data-page-loader aria-hidden="true">
    <div class="loader-card">
        <span class="loader-ring"></span>
        <strong>Please wait</strong>
        <small>Signing you in...</small>
    </div>
</div>
<main class="login-container">
    <section class="login-card single">
        <a class="brand login-brand" href="<?= e(app_url('login')) ?>">
            <span class="brand-mark"><?= e(config('app.logo_text', 'X')) ?></span>
            <span>
                <strong><?= e(config('app.name')) ?></strong>
                <small>Customer Area</small>
            </span>
        </a>

        <div class="login-heading">
            <span class="eyebrow">Secure Access</span>
            <h1>Sign in</h1>
        </div>

        <?php if ($flashMessages !== []): ?>
            <div class="flash-stack compact">
                <?php foreach ($flashMessages as $flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('login')) ?>" class="form login-form" data-loading-form>
            <?= Csrf::field() ?>
            <label>
                <span>Username</span>
                <input type="text" name="username" autocomplete="username" placeholder="Enter your username" required autofocus>
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
            </label>
            <button type="submit" class="btn primary full" data-loading-text="Signing in...">Sign In</button>
        </form>
    </section>
</main>
</body>
</html>