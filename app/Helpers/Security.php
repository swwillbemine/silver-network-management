<?php

namespace App\Helpers;

use App\Auth\Session;

/**
 * Security & Sanitization Helpers
 */
class Security
{
    public static function generateCsrfToken(): string
    {
        $token = Session::get('csrf_token');
        if (empty($token)) {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }
        return $token;
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        $storedToken = Session::get('csrf_token');
        return !empty($storedToken) && !empty($token) && hash_equals($storedToken, $token);
    }

    public static function csrfField(): string
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function clean(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }
}

