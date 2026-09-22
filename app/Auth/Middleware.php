<?php

namespace App\Auth;

use App\Core\Database;
use Exception;

/**
 * Access Control & Session Validation Middleware
 */
class Middleware
{
    public static function checkSession(): void
    {
        Session::checkTimeout();

        $userId       = Session::get('user_id');
        $sessionToken = Session::get('session_token');

        if (!empty($userId) && !empty($sessionToken)) {
            $pdo = Database::getConnection();
            try {
                $stmt = $pdo->prepare("SELECT session_token FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$userId]);
                $dbToken = $stmt->fetchColumn();
                if ($dbToken !== $sessionToken) {
                    Session::destroy();
                    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
                    header("Location: {$baseUrl}/login?error=session_taken");
                    exit;
                }
            } catch (Exception $e) {
            }
        }
    }

    public static function requireLogin(): void
    {
        self::checkSession();
        if (!Auth::check()) {
            $baseUrl = defined('BASE_URL') ? BASE_URL : '';
            header("Location: {$baseUrl}/login");
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        $userRole = Auth::role() ?? '';
        if (!in_array($userRole, $roles, true)) {
            http_response_code(403);
            $baseUrl = defined('BASE_URL') ? BASE_URL : '';
            ?>
            <!doctype html><html lang="id"><head><meta charset="utf-8"><title>Akses Ditolak</title>
            <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/css/tabler.min.css" rel="stylesheet"/></head>
            <body class="d-flex flex-column"><div class="page page-center">
            <div class="container-tight text-center py-5">
              <div class="mb-3 text-red"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none"><path d="M12 9v4m0 3v.01"/><path d="M5.07 19H19a2 2 0 0 0 1.75-2.75L13.75 4a2 2 0 0 0-3.5 0L3.25 16.25A2 2 0 0 0 5.07 19z"/></svg></div>
              <h1 class="h2">Akses Ditolak</h1>
              <p class="text-muted">Role Anda (<strong><?= htmlspecialchars($userRole ?: '-') ?></strong>) tidak memiliki akses ke halaman ini.</p>
              <a href="<?= $baseUrl ?>/" class="btn btn-primary">Kembali ke Dashboard</a>
            </div></div></body></html>
            <?php
            exit;
        }
    }
}

