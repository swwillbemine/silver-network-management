<?php

namespace App\Core;

use App\Config\App;
use PDO;
use Exception;

/**
 * Application Bootstrap
 */
class Bootstrap
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $basePath = realpath(dirname(__DIR__, 2));
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $basePath);
        }

        if (!defined('APP_LOADED')) {
            define('APP_LOADED', true);
        }

        // Load .env if present
        App::loadEnv($basePath . '/.env');

        // Determine BASE_URL
        if (!defined('BASE_URL')) {
            $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            $normBasePath = rtrim(str_replace('\\', '/', $basePath), '/');
            $baseUrl = str_replace($docRoot, '', $normBasePath);
            define('BASE_URL', rtrim($baseUrl, '/'));
        }

        // Start session securely
        if (session_status() === PHP_SESSION_NONE) {
            $sessPath = session_save_path();
            if (empty($sessPath) || !is_writable($sessPath)) {
                @session_save_path(sys_get_temp_dir());
            }
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_strict_mode', '1');
            @session_start();
        }

        // Initialize Database
        $pdo = Database::getConnection();
        $GLOBALS['pdo'] = $pdo;

        // Load Settings from DB
        $_settings = [];
        try {
            $_settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $_settings = [];
        }

        $GLOBALS['_settings'] = $_settings;
        $GLOBALS['isp_name']   = $_settings['isp_name']   ?? 'SilverNet';
        $GLOBALS['app_name']   = $_settings['app_name']   ?? 'Silver Network Management';
        $GLOBALS['full_title'] = $GLOBALS['app_name'] . ' - ' . $GLOBALS['isp_name'];

        // Set Timezone
        $timezone = $_settings['timezone'] ?? (getenv('APP_TIMEZONE') ?: 'Asia/Jakarta');
        date_default_timezone_set($timezone);
        $GLOBALS['_tz'] = $timezone;

        self::$initialized = true;
    }
}

