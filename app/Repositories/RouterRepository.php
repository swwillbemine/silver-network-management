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
                       (SELECT COUNT(*) FROM customers c WHERE c.router_id = m.id) AS cust_count
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
        $sql = "INSERT INTO mikrotiks (name, ip_address, username, password, port, pop_id, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['ip_address'],
            $data['username'],
            $data['password'],
            $data['port'] ?? 8728,
            $data['pop_id'] ?? null,
            $data['is_active'] ?? 1,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $cols = ['name', 'ip_address', 'username', 'password', 'port', 'pop_id', 'is_active', 'status', 'last_seen'];
        foreach ($cols as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = ?";
                $params[] = $data[$col];
            }
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

