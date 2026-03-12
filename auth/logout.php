<?php
// auth/logout.php
require_once __DIR__ . '/../config/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo->prepare("UPDATE users SET session_token = NULL WHERE id = ?")
            ->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {}
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();
header('Location: '.BASE_URL.'/login.php');
exit;