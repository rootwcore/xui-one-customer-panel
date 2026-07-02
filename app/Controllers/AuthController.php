<?php

declare(strict_types=1);

namespace XuiPanel\Controllers;

use RuntimeException;
use XuiPanel\Core\Csrf;
use XuiPanel\Core\RateLimiter;
use XuiPanel\Services\XuiClient;

final class AuthController
{
    public function showLogin(): void
    {
        if (current_user()) {
            redirect('dashboard');
        }
        view('login', ['title' => 'Sign In'], false);
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            flash('danger', 'Session verification failed. Please refresh the page and try again.');
            redirect('login');
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $limiter = new RateLimiter();

        if ($username === '' || $password === '') {
            flash('danger', 'Username and password are required.');
            redirect('login');
        }

        if ($limiter->tooManyAttempts($ip, $username)) {
            flash('danger', 'Too many failed login attempts. Please try again later.');
            redirect('login');
        }

        try {
            $xui = new XuiClient();
            $playerResponse = $xui->authenticatePlayer($username, $password);
            $playerUserInfo = is_array($playerResponse['user_info'] ?? null) ? $playerResponse['user_info'] : [];

            $lineId = $xui->extractLineId($playerUserInfo);
            $line = [];

            if ($lineId !== null) {
                $line = $xui->getLine($lineId);
            }

            if ($line === []) {
                $line = $xui->findLineByUsername($username) ?? [];
                $lineId = $xui->extractLineId($line);
            }

            if (!$lineId) {
                throw new RuntimeException('The account was verified, but account details could not be loaded.');
            }

            if ($line === []) {
                $line = $xui->getLine($lineId);
            }

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'username' => $username,
                'line_id' => $lineId,
                'login_at' => time(),
                'password' => $password,
            ];

            $limiter->hit($ip, $username, true);
            flash('success', 'Signed in successfully.');
            redirect('dashboard');
        } catch (RuntimeException $e) {
            $limiter->hit($ip, $username, false);
            $message = (bool)config('features.show_raw_api_errors', false)
                ? $e->getMessage()
                : 'Invalid information.';
            flash('danger', $message);
            redirect('login');
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        redirect('login');
    }
}