<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

$configFile = BASE_PATH . '/config.php';
if (!is_file($configFile)) {
    $configFile = BASE_PATH . '/config.example.php';
}

$GLOBALS['config'] = require $configFile;

require BASE_PATH . '/app/helpers.php';

date_default_timezone_set((string)config('app.timezone', 'Europe/Istanbul'));

if ((bool)config('app.debug', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

$sessionName = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)config('app.session_name', 'xui_customer_panel')) ?: 'xui_customer_panel';
session_name($sessionName);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'XuiPanel\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});