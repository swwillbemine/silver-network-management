<?php

namespace App\Auth;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Authentication Manager
 */
class Auth
{
    public static function check(): bool
    {
        return !empty(Session::get('user_id'));
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id !== null ? (int)$id : null;
    }

    public static function name(): ?string
    {
        return Session::get('user_name');
    }

    public static function role(): ?string
    {
        return Session::get('user_role');
    }

    public static function user(): ?array
    {
        $id = self::id();
        if (!$id) {
            return null;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, name, username, email, role, is_active, last_login, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Attempt login with credentials
     */
    public static function attempt(string $username, string $password): bool
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && self::verifyPassword($password, $user['password'])) {
                session_regenerate_id(true);

                $sessionToken = bin2hex(random_bytes(32));

                Session::set('user_id', (int)$user['id']);
                Session::set('user_name', $user['name']);
                Session::set('user_role', $user['role']);
                Session::set('last_activity', time());
                Session::set('session_token', $sessionToken);

                $pdo->prepare("UPDATE users SET last_login = NOW(), session_token = ?, session_started_at = NOW() WHERE id = ?")
                    ->execute([$sessionToken, $user['id']]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Log user out
     */
    public static function logout(): void
    {
        Session::destroy();
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}

