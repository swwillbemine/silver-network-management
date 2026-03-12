<?php
// helpers/security.php

if (!defined('APP_LOADED')) {
    die('Akses langsung tidak diizinkan. Load melalui bootstrap.php');
}

define('SESSION_TIMEOUT', 3600);

function checkSession(): void {
    if (isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: '.BASE_URL.'/login.php?timeout=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();

    if (!empty($_SESSION['user_id']) && !empty($_SESSION['session_token'])) {
        global $pdo;
        try {
            $row = $pdo->prepare("SELECT session_token FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
            $row->execute([$_SESSION['user_id']]);
            $db_token = $row->fetchColumn();
            if ($db_token !== $_SESSION['session_token']) {
                session_unset();
                session_destroy();
                header('Location: '.BASE_URL.'/login.php?error=session_taken');
                exit;
            }
        } catch (Exception $e) {
        }
    }
}

function requireLogin(): void {
    checkSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: '.BASE_URL.'/login.php');
        exit;
    }
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', $roles, true)) {
        http_response_code(403);
        ?>
        <!doctype html><html lang="id"><head><meta charset="utf-8"><title>Akses Ditolak</title>
        <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/css/tabler.min.css" rel="stylesheet"/></head>
        <body class="d-flex flex-column"><div class="page page-center">
        <div class="container-tight text-center py-5">
          <div class="mb-3 text-red"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none"><path d="M12 9v4m0 3v.01"/><path d="M5.07 19H19a2 2 0 0 0 1.75-2.75L13.75 4a2 2 0 0 0-3.5 0L3.25 16.25A2 2 0 0 0 5.07 19z"/></svg></div>
          <h1 class="h2">Akses Ditolak</h1>
          <p class="text-muted">Role Anda (<strong><?= htmlspecialchars($_SESSION['user_role'] ?? '-') ?></strong>) tidak memiliki akses ke halaman ini.</p>
          <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary">Kembali ke Dashboard</a>
        </div></div></body></html>
        <?php
        exit;
    }
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

function clean(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}