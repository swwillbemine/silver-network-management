<?php

namespace App\Auth;

/**
 * Session Manager
 */
class Session
{
    public const DEFAULT_TIMEOUT = 3600;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_strict_mode', '1');
            session_start();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        self::set('flash_msg', "{$type}:{$message}");
    }

    public static function getFlash(): ?array
    {
        $raw = self::get('flash_msg');
        if (!$raw) {
            return null;
        }

        self::remove('flash_msg');
        [$type, $message] = explode(':', $raw, 2) + ['', ''];
        return ['type' => $type, 'message' => $message];
    }

    public static function checkTimeout(int $timeout = self::DEFAULT_TIMEOUT): void
    {
        self::start();
        if (isset($_SESSION['last_activity'])) {
            if ((time() - $_SESSION['last_activity']) > $timeout) {
                self::destroy();
                $baseUrl = defined('BASE_URL') ? BASE_URL : '';
                header("Location: {$baseUrl}/login?timeout=1");
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}

