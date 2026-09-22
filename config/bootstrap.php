<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Core\Bootstrap::init();
$pdo = \App\Core\Database::getConnection();

if (!isset($pdo) || !($pdo instanceof \PDO)) {
    require_once BASE_PATH . '/config/database.php';
}
require_once BASE_PATH . '/config/settings.php';

$_tz = isset($pdo) ? (function($pdo) {
    try {
        $r = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='timezone' LIMIT 1")->fetch();
        return $r ? $r['setting_value'] : 'Asia/Jakarta';
    } catch (\Exception $e) { return 'Asia/Jakarta'; }
})($pdo) : 'Asia/Jakarta';
date_default_timezone_set($_tz);