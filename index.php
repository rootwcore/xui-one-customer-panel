<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use XuiPanel\Controllers\AuthController;
use XuiPanel\Controllers\BouquetController;
use XuiPanel\Controllers\DashboardController;
use XuiPanel\Controllers\PasswordController;

$page = trim((string)($_GET['page'] ?? ''));

if ($page === '') {
    $requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($requestPath, $scriptDir)) {
        $requestPath = substr($requestPath, strlen($scriptDir));
    }

    $candidate = trim($requestPath, '/');
    if ($candidate !== '' && !str_contains($candidate, '.')) {
        $page = $candidate;
    }
}

$page = $page === '' ? (current_user() ? 'dashboard' : 'login') : $page;
$GLOBALS['current_page'] = $page;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    match ($page) {
        'login' => $method === 'POST' ? (new AuthController())->login() : (new AuthController())->showLogin(),
        'logout' => (new AuthController())->logout(),
        'dashboard' => (new DashboardController())->index(),
        'bouquets' => $method === 'POST' ? (new BouquetController())->update() : (new BouquetController())->index(),
        'password' => $method === 'POST' ? (new PasswordController())->update() : (new PasswordController())->index(),
        default => (function (): void {
            http_response_code(404);
            view('error', ['title' => 'Page Not Found', 'message' => 'The page you are looking for could not be found.']);
        })(),
    };
} catch (Throwable $e) {
    http_response_code(500);
    $message = (bool)config('app.debug', false) ? $e->getMessage() : 'An unexpected error occurred.';
    view('error', ['title' => 'Error', 'message' => $message]);
}