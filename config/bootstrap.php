<?php
// config/bootstrap.php

if (defined('APP_LOADED')) return;
define('APP_LOADED', true);

define('BASE_PATH', realpath(__DIR__ . '/..'));

// if (!defined('BASE_URL')) {
//     $doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
//     $base_path = rtrim(str_replace('\\', '/', BASE_PATH), '/');
//     define('BASE_URL', rtrim(str_replace($doc_root, '', $base_path), '/'));
// }

if (!defined('BASE_URL')) {
    $doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $base_path = rtrim(str_replace('\\', '/', BASE_PATH), '/');
    $base_url  = str_replace($doc_root, '', $base_path);
    define('BASE_URL', rtrim($base_url, '/'));
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/settings.php';
require_once BASE_PATH . '/helpers/security.php';
require_once BASE_PATH . '/mikrotik/connection.php';
require_once BASE_PATH . '/mikrotik/ppp.php';
require_once BASE_PATH . '/mikrotik/ip_pool.php';

$_tz = isset($pdo) ? (function($pdo) {
    try {
        $r = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='timezone' LIMIT 1")->fetch();
        return $r ? $r['setting_value'] : 'Asia/Jakarta';
    } catch (Exception $e) { return 'Asia/Jakarta'; }
})($pdo) : 'Asia/Jakarta';
date_default_timezone_set($_tz);