<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Setting Repository — Database operations for settings and api_keys tables
 */
class SettingRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    public function all(): array
    {
        return $this->pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }

    public function getAll(): array
    {
        return $this->all();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    }

    public function set(string $key, string $value): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    }

    public function setMany(array $settings): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");

            foreach ($settings as $key => $value) {
                $stmt->execute([$key, (string)$value, (string)$value]);
            }

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function getApiKeysByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM api_keys WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getApiKeys(): array
    {
        return $this->pdo->query("SELECT * FROM api_keys ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findApiKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM api_keys WHERE api_key = ? LIMIT 1");
        $stmt->execute([$key]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createApiKey(array $data): int
    {
        $sql = "INSERT INTO api_keys (user_id, name, api_key, permissions) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['user_id'],
            $data['name'],
            $data['api_key'],
            $data['permissions'] ?? 'read_customers',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function toggleApiKey(int $keyId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE api_keys SET is_active = NOT is_active WHERE id = ? AND user_id = ?");
        return $stmt->execute([$keyId, $userId]);
    }

    public function deleteApiKey(int $id, ?int $userId = null): bool
    {
        if ($userId !== null) {
            $stmt = $this->pdo->prepare("DELETE FROM api_keys WHERE id = ? AND user_id = ?");
            return $stmt->execute([$id, $userId]);
        }
        $stmt = $this->pdo->prepare("DELETE FROM api_keys WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

