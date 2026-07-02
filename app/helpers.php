<?php

declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    $config = $GLOBALS['config'] ?? [];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($config) || !array_key_exists($segment, $config)) {
            return $default;
        }
        $config = $config[$segment];
    }
    return $config;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $page = '', array $params = []): string
{
    $base = rtrim((string)config('app.base_url', ''), '/');

    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $base = ($https ? 'https://' : 'http://') . $host . ($scriptDir === '' ? '' : $scriptDir);
    }

    $url = $base . ($page === '' ? '/' : '/' . ltrim($page, '/'));
    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

function redirect(string $page = 'dashboard'): never
{
    header('Location: ' . app_url($page));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function view(string $template, array $data = [], bool $layout = true): void
{
    extract($data, EXTR_SKIP);
    $viewFile = BASE_PATH . '/app/Views/' . $template . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException('View not found: ' . $template);
    }

    if (!$layout) {
        require $viewFile;
        return;
    }

    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require BASE_PATH . '/app/Views/layout.php';
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login');
    }
    return $user;
}

function array_get_any(array $data, array $keys, mixed $default = null): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
    }
    return $default;
}

function normalize_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return ((int)$value) === 1;
    }
    return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'enabled', 'active'], true);
}

function app_timezone(): DateTimeZone
{
    return new DateTimeZone((string)config('app.timezone', 'Europe/Istanbul'));
}

function format_datetime(mixed $value, string $empty = 'Unknown'): string
{
    if ($value === null || $value === '' || $value === false) {
        return $empty;
    }

    try {
        $timezone = app_timezone();

        if (is_numeric($value)) {
            $timestamp = (int)$value;
            if ($timestamp <= 0) {
                return 'Unlimited';
            }
            $date = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        } else {
            $date = new DateTimeImmutable((string)$value);
            $date = $date->setTimezone($timezone);
        }

        return $date->format('d/m/Y - H:i');
    } catch (Throwable) {
        return (string)$value;
    }
}

function format_datetime_from_epoch(mixed $epoch): string
{
    return format_datetime($epoch, 'Unlimited');
}

function days_remaining(mixed $epoch): ?int
{
    if (!$epoch || !is_numeric($epoch)) {
        return null;
    }
    $seconds = ((int)$epoch) - time();
    return (int)ceil($seconds / 86400);
}

function asset_url(string $path): string
{
    return app_url('') . ltrim($path, '/');
}


function contact_link(): ?array
{
    if (!(bool)config('contact.enabled', true)) {
        return null;
    }

    $value = trim((string)config('contact.value', ''));
    if ($value === '') {
        return null;
    }

    $label = trim((string)config('contact.label', 'Contact'));
    $type = strtolower(trim((string)config('contact.type', 'auto')));

    if ($type === 'email' || ($type === 'auto' && filter_var($value, FILTER_VALIDATE_EMAIL))) {
        return [
            'label' => $label === '' ? 'Contact' : $label,
            'href' => 'mailto:' . $value,
            'target' => '_self',
            'external' => false,
        ];
    }

    $href = $value;
    if (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $href)) {
        $href = 'https://' . $href;
    }

    return [
        'label' => $label === '' ? 'Contact' : $label,
        'href' => $href,
        'target' => '_blank',
        'external' => true,
    ];
}

function playlist_url_for(string $username, string $password): string
{
    if (!(bool)config('playlist.enabled', true)) {
        return '';
    }

    $base = rtrim((string)config('xui.base_url', ''), '/');
    if ($base === '' || $username === '' || $password === '') {
        return '';
    }

    $path = trim((string)config('playlist.path', 'get.php'), '/');
    $params = [
        'username' => $username,
        'password' => $password,
        'type' => (string)config('playlist.type', 'm3u_plus'),
        'output' => (string)config('playlist.output', 'ts'),
    ];

    return $base . '/' . $path . '?' . http_build_query($params);
}