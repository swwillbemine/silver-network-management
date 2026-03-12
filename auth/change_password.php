<?php
// auth/change_password.php
require_once __DIR__ . '/../config/bootstrap.php';

$redirect = $_POST['redirect'] ?? '../settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$user_id  = $_SESSION['user_id'];
$old_pass = $_POST['old_password'] ?? '';
$new_pass = $_POST['new_password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

$user = $pdo->prepare("SELECT password FROM users WHERE id=?");
$user->execute([$user_id]);
$u = $user->fetch();

if (!$u || !password_verify($old_pass, $u['password'])) {
    header('Location: ' . $redirect . '?tab=account&msg=wrong_pass');
    exit;
}

if (strlen($new_pass) < 6) {
    header('Location: ' . $redirect . '?tab=account&msg=too_short');
    exit;
}

if ($new_pass !== $confirm) {
    header('Location: ' . $redirect . '?tab=account&msg=mismatch');
    exit;
}

$pdo->prepare("UPDATE users SET password=? WHERE id=?")
    ->execute([password_hash($new_pass, PASSWORD_DEFAULT), $user_id]);

header('Location: ' . $redirect . '?tab=account&msg=ok');
exit;