<?php

declare(strict_types=1);

namespace XuiPanel\Core;

final class RateLimiter
{
    private string $file;

    public function __construct()
    {
        $storage = BASE_PATH . '/storage';
        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }
        $this->file = $storage . '/login_attempts.json';
    }

    public function tooManyAttempts(string $ip, string $username): bool
    {
        $max = (int)config('security.login_max_attempts', 6);
        $decay = (int)config('security.login_decay_minutes', 10) * 60;
        $since = time() - $decay;
        $username = mb_strtolower($username);

        $attempts = $this->readAttempts();
        $count = 0;
        foreach ($attempts as $attempt) {
            if (($attempt['ip'] ?? '') === $ip
                && ($attempt['username'] ?? '') === $username
                && (int)($attempt['success'] ?? 0) === 0
                && (int)($attempt['attempted_at'] ?? 0) >= $since
            ) {
                $count++;
            }
        }

        return $count >= $max;
    }

    public function hit(string $ip, string $username, bool $success): void
    {
        $attempts = $this->readAttempts();
        $attempts[] = [
            'ip' => $ip,
            'username' => mb_strtolower($username),
            'attempted_at' => time(),
            'success' => $success ? 1 : 0,
        ];

        $old = time() - 86400;
        $attempts = array_values(array_filter(
            $attempts,
            static fn (array $attempt): bool => (int)($attempt['attempted_at'] ?? 0) >= $old
        ));

        $this->writeAttempts($attempts);
    }

    private function readAttempts(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $json = file_get_contents($this->file);
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeAttempts(array $attempts): void
    {
        $fp = fopen($this->file, 'c+');
        if ($fp === false) {
            return;
        }

        try {
            flock($fp, LOCK_EX);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($attempts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
