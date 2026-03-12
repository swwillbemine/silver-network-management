<?php
// auth/login_process.php
require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: '.BASE_URL.'/login.php');
    exit;
}

// CSRF check
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: '.BASE_URL.'/login.php?error=csrf');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    header('Location: '.BASE_URL.'/login.php?error=1');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $session_token = bin2hex(random_bytes(32));

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_name']     = $user['name'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['session_token'] = $session_token;

        $pdo->prepare("UPDATE users SET last_login = NOW(), session_token = ?, session_started_at = NOW() WHERE id = ?")
            ->execute([$session_token, $user['id']]);

        header('Location: '.BASE_URL.'/index.php');
        exit;
    }

    header('Location: '.BASE_URL.'/login.php?error=1');
    exit;

} catch (Exception $e) {
    header('Location: '.BASE_URL.'/login.php?error=1');
    exit;
}