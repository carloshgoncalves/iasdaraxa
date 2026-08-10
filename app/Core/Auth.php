<?php

declare(strict_types=1);

namespace IasdCentral\Core;

use IasdCentral\Repositories\UserRepository;

final class Auth
{
    public function user(): ?array
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);
        return $id > 0 ? (new UserRepository())->find($id) : null;
    }

    public function id(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public function attempt(string $username, string $password): bool
    {
        if ($this->tooManyAttempts($username)) {
            return false;
        }

        $user = (new UserRepository())->findByUsername($username);
        if (!$user || (string) $user['status'] !== 'Ativo' || !password_verify($password, (string) $user['password'])) {
            $this->recordFailedAttempt($username);
            return false;
        }

        unset($_SESSION['login_attempts'][$this->attemptKey($username)]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = (string) $user['name'];
        $_SESSION['username'] = (string) $user['username'];

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): array
    {
        $user = (new self())->user();
        if (!$user) {
            redirect('/login');
        }

        return $user;
    }

    private function tooManyAttempts(string $username): bool
    {
        $attempt = $_SESSION['login_attempts'][$this->attemptKey($username)] ?? null;
        return is_array($attempt) && (int) ($attempt['count'] ?? 0) >= 5 && (time() - (int) ($attempt['last'] ?? 0)) < 60;
    }

    private function recordFailedAttempt(string $username): void
    {
        $key = $this->attemptKey($username);
        $attempt = $_SESSION['login_attempts'][$key] ?? ['count' => 0, 'last' => time()];
        $_SESSION['login_attempts'][$key] = [
            'count' => ((int) ($attempt['count'] ?? 0)) + 1,
            'last' => time(),
        ];
    }

    private function attemptKey(string $username): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'local');
        return sha1(strtolower(trim($username)) . '|' . $ip);
    }
}
