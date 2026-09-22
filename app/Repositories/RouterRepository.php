<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Router Repository — Database operations for mikrotiks table
 */
class RouterRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    public function all(): array
    {
        $sql = "SELECT m.*, p.name AS pop_name, n.name AS node_name,
                       (SELECT COUNT(*) FROM customers c JOIN packages pkg ON pkg.id = c.package_id WHERE pkg.mikrotik_id = m.id) AS cust_count
                FROM mikrotiks m
                LEFT JOIN pops p ON p.id = m.pop_id
                LEFT JOIN nodes n ON n.id = p.node_id
                ORDER BY m.name ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT m.*, p.name AS pop_name, n.name AS node_name
                FROM mikrotiks m
                LEFT JOIN pops p ON p.id = m.pop_id
                LEFT JOIN nodes n ON n.id = p.node_id
                WHERE m.id = ? LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO mikrotiks (pop_id, name, host, port, username, password, api_ssl)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['pop_id'] ?? null,
            $data['name'],
            $data['host'] ?? ($data['ip_address'] ?? ''),
            $data['port'] ?? 8728,
            $data['username'],
            $data['password'],
            !empty($data['api_ssl']) ? 1 : 0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $cols = ['pop_id', 'name', 'host', 'port', 'username', 'password', 'api_ssl', 'status', 'last_seen'];
        foreach ($cols as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = ?";
                $params[] = $data[$col];
            }
        }
        if (isset($data['ip_address']) && !isset($data['host'])) {
            $fields[] = "host = ?";
            $params[] = $data['ip_address'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE mikrotiks SET " . implode(', ', $fields) . " WHERE id = ?";
        return $this->pdo->prepare($sql)->execute($params);
    }

    public function updateStatus(int $id, string $status, ?string $traffic = null): bool
    {
        $sql = "UPDATE mikrotiks SET status = ?, last_seen = NOW() WHERE id = ?";
        return $this->pdo->prepare($sql)->execute([$status, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM mikrotiks WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

