<?php

declare(strict_types=1);

namespace XuiPanel\Controllers;

use RuntimeException;
use XuiPanel\Core\Csrf;
use XuiPanel\Services\XuiClient;

final class PasswordController
{
    public function index(): void
    {
        $user = require_auth();
        view('password', [
            'title' => 'Change Password',
            'user' => $user,
        ]);
    }

    public function update(): void
    {
        $user = require_auth();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            flash('danger', 'Session verification failed.');
            redirect('password');
        }

        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        $min = (int)config('security.password_min', 6);
        $max = (int)config('security.password_max', 14);
        $pattern = (string)config('security.password_pattern', '/^[A-Za-z0-9]+$/');

        if ($newPassword === '' || $confirmPassword === '') {
            flash('danger', 'Please fill in all fields.');
            redirect('password');
        }

        if ($newPassword !== $confirmPassword) {
            flash('danger', 'The new password and confirmation must match.');
            redirect('password');
        }

        $length = strlen($newPassword);
        if ($length < $min || $length > $max || !preg_match($pattern, $newPassword)) {
            flash('danger', "The new password must be {$min}-{$max} characters and contain letters and numbers only.");
            redirect('password');
        }

        try {
            $xui = new XuiClient();
            $line = $xui->getLine($user['line_id']);
            $xui->saveLinePassword($user['line_id'], $newPassword, $line, $user['username']);

            unset($_SESSION['user']);
            session_regenerate_id(true);
            flash('success', 'Your password has been updated. Please sign in again with your new password.');
            redirect('login');
        } catch (RuntimeException $e) {
            $message = (bool)config('features.show_raw_api_errors', false)
                ? $e->getMessage()
                : 'The password could not be updated. Please try again.';
            flash('danger', $message);
        }

        redirect('password');
    }
}
