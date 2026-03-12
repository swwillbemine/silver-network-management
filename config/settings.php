<?php
// config/settings.php

if (!isset($pdo)) {
    $settings_db_path = __DIR__ . '/database.php';
    if (file_exists($settings_db_path)) {
        require_once $settings_db_path;
    }
}

if (!function_exists('_load_settings')) {
    function _load_settings(PDO $pdo): array {
        try {
            $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
            return $rows;
        } catch (Exception $e) {
            return [];
        }
    }
}

$_settings = isset($pdo) ? _load_settings($pdo) : [];

$isp_name   = $_settings['isp_name']   ?? 'SilverNet';
$app_name   = $_settings['app_name']   ?? 'Silver Network Management';
$full_title = $app_name . ' - ' . $isp_name;