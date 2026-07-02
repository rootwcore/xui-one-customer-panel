<?php
$expDate = array_get_any($line, ['exp_date', 'expire_date', 'expiry_date', 'expiration']);
$remaining = days_remaining($expDate);
$maxConnections = array_get_any($line, ['max_connections', 'max_connections_count'], '-');
$activeConnections = array_get_any($line, ['active_cons', 'active_connections', 'connections'], '0');
$statusValue = array_get_any($line, ['status'], null);
$enabledValue = array_get_any($line, ['enabled', 'is_enabled'], null);
$status = $statusValue !== null ? (string)$statusValue : ($enabledValue !== null ? (normalize_bool($enabledValue) ? 'Active' : 'Inactive') : 'Unknown');
$allowedCount = count($allowedBouquets ?? []);
$activeCount = count($activeBouquets ?? []);
$m3uUrl = playlist_url_for((string)($user['username'] ?? ''), (string)($user['password'] ?? ''));
$dnsUrl = rtrim((string)config('xui.base_url', ''), '/');
$password = (string)($user['password'] ?? '');
?>
<section class="hero-panel">
    <div>
        <span class="eyebrow">Welcome Back</span>
        <h1>Hello, <?= e($user['username'] ?? '-') ?></h1>
        <p>Manage your channel groups and password.</p>
    </div>
    <div class="hero-actions">
        <a class="btn primary" href="<?= e(app_url('bouquets')) ?>">Manage Bouquets</a>
        <a class="btn secondary" href="<?= e(app_url('password')) ?>">Change Password</a>
    </div>
</section>

<section class="grid cards-4">
    <article class="stat-card glow cyan">
        <span class="stat-icon">⏳</span>
        <small>Remaining Time</small>
        <strong><?= $remaining === null ? 'Unknown' : e((string)$remaining . ' days') ?></strong>
        <em>Expires: <?= e(format_datetime_from_epoch($expDate)) ?></em>
    </article>
    <article class="stat-card purple">
        <span class="stat-icon">🔌</span>
        <small>Connection</small>
        <strong><?= e(is_array($activeConnections) ? (string)count($activeConnections) : (string)$activeConnections) ?> / <?= e(is_array($maxConnections) ? '-' : (string)$maxConnections) ?></strong>
        <em>Current / Maximum Connections</em>
    </article>
    <article class="stat-card green">
        <span class="stat-icon">✅</span>
        <small>Account Status</small>
        <strong><?= e($status) ?></strong>
        <em>Your account access status.</em>
    </article>
    <article class="stat-card orange">
        <span class="stat-icon">🧩</span>
        <small>Bouquet</small>
        <strong><?= e((string)$activeCount) ?> / <?= e((string)$allowedCount) ?></strong>
        <em>Active / Assigned Bouquets</em>
    </article>
</section>

<section class="panel account-panel">
    <div class="panel-head compact-head">
        <div>
            <span class="eyebrow">Account Summary</span>
            <h2>Your Details</h2>
        </div>
    </div>
    <div class="info-list summary-grid">
        <div><span>Username</span><strong><?= e($user['username'] ?? '-') ?></strong></div>
        <div><span>Password</span><strong><?= e($password !== '' ? $password : '-') ?></strong></div>
        <div><span>DNS URL</span><strong><?= e($dnsUrl !== '' ? $dnsUrl : '-') ?></strong></div>
        <div><span>Expiry Date</span><strong><?= e(format_datetime_from_epoch($expDate)) ?></strong></div>
    </div>
</section>

<section class="panel activity-panel">
    <div class="panel-head compact-head">
        <div>
            <span class="eyebrow">Streaming Access</span>
            <h2>M3U Playlist URL</h2>
        </div>
    </div>
    <div class="info-list playlist-grid">
        <div class="playlist-box wide">
            <span>M3U Playlist URL</span>
            <?php if ($m3uUrl !== ''): ?>
                <div class="copy-field">
                    <input type="text" value="<?= e($m3uUrl) ?>" readonly data-copy-source>
                    <button class="copy-btn" type="button" data-copy-button>Copy</button>
                </div>
            <?php else: ?>
                <strong>Not available</strong>
            <?php endif; ?>
        </div>
    </div>
</section>