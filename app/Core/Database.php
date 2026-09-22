<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Database Connection Manager (PDO Wrapper)
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO instance
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    /**
     * Alias for getConnection()
     */
    public static function getInstance(): PDO
    {
        return self::getConnection();
    }

    /**
     * Set a custom PDO instance (useful for testing or existing connection)
     */
    public static function setConnection(PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Create a new PDO connection
     */
    private static function createConnection(): PDO
    {
        $configFile = dirname(__DIR__, 2) . '/config/database.php';
        $host    = 'localhost';
        $db      = 'silvernet-management';
        $user    = 'root';
        $pass    = '';
        $port    = 3306;
        $charset = 'utf8mb4';

        // Load credentials from config/database.php if available
        if (file_exists($configFile)) {
            // Read without invoking PDO instantiation from file if it already does
            // Extract variables directly
            $extractVars = function($file) {
                // Include in isolated scope
                $host = 'localhost'; $db = 'silvernet-management'; $user = 'root'; $pass = ''; $port = 3306; $charset = 'utf8mb4';
                @include $file;
                return compact('host', 'db', 'user', 'pass', 'port', 'charset');
            };
            $cfg = $extractVars($configFile);
            $host    = $cfg['host'] ?? $host;
            $db      = $cfg['db'] ?? $db;
            $user    = $cfg['user'] ?? $user;
            $pass    = $cfg['pass'] ?? $pass;
            $port    = $cfg['port'] ?? $port;
            $charset = $cfg['charset'] ?? $charset;
        }

        // Check environment variables override if present
        $host = getenv('DB_HOST') ?: $host;
        $db   = getenv('DB_NAME') ?: $db;
        $user = getenv('DB_USER') ?: $user;
        $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : $pass;
        $port = getenv('DB_PORT') ?: $port;

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
        }
    }
}

