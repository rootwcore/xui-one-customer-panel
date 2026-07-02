<?php
$title = $title ?? config('app.name', 'XUI Customer Panel');
$user = current_user();
$flashMessages = consume_flash();
$currentPage = $GLOBALS['current_page'] ?? ($_GET['page'] ?? 'dashboard');
$contact = contact_link();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> - <?= e(config('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('assets/img/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
    <script defer src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
</head>
<body>
<div class="page-loader" data-page-loader aria-hidden="true">
    <div class="loader-card">
        <span class="loader-ring"></span>
        <strong>Please wait</strong>
        <small>Loading your panel...</small>
    </div>
</div>
<div class="app-shell">
    <header class="site-header">
        <div class="site-header-inner">
            <a class="brand" href="<?= e(app_url('dashboard')) ?>">
                <span class="brand-mark"><?= e(config('app.logo_text', 'X')) ?></span>
                <span>
                    <strong><?= e(config('app.name')) ?></strong>
                    <small>Customer Area</small>
                </span>
            </a>

            <button class="menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-label="Open menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="top-nav" data-main-nav aria-label="Main menu">
                <a class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= e(app_url('dashboard')) ?>">Dashboard</a>
                <a class="nav-item <?= $currentPage === 'bouquets' ? 'active' : '' ?>" href="<?= e(app_url('bouquets')) ?>">Bouquets</a>
                <a class="nav-item <?= $currentPage === 'password' ? 'active' : '' ?>" href="<?= e(app_url('password')) ?>">Password</a>
                <?php if ($contact !== null): ?>
                    <a class="nav-item contact-nav" href="<?= e($contact['href']) ?>" target="<?= e($contact['target']) ?>" <?= $contact['external'] ? 'rel="noopener noreferrer"' : '' ?>><?= e($contact['label']) ?></a>
                <?php endif; ?>
            </nav>

            <div class="user-menu">
                <span><?= e($user['username'] ?? '-') ?></span>
                <a class="logout-link" href="<?= e(app_url('logout')) ?>">Sign Out</a>
            </div>
        </div>
    </header>

    <main class="main">
        <?php if ($flashMessages !== []): ?>
            <div class="flash-stack">
                <?php foreach ($flashMessages as $flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="site-footer">
        <span>Built by</span>
        <a href="https://github.com/rootwcore" target="_blank" rel="noopener noreferrer">RootWebCore</a>
    </footer>
</div>
</body>
</html>