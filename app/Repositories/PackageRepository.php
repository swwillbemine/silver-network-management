<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Package Repository — Database operations for packages table
 */
class PackageRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    public function all(?int $routerId = null): array
    {
        $sql = "SELECT p.*, m.name AS mikrotik_name, m.ip_address AS mikrotik_ip,
                       (SELECT COUNT(*) FROM customers c WHERE c.package_id = p.id) AS cust_count
                FROM packages p
                LEFT JOIN mikrotiks m ON m.id = p.mikrotik_id";

        $params = [];
        if ($routerId !== null) {
            $sql .= " WHERE p.mikrotik_id = ?";
            $params[] = $routerId;
        }

        $sql .= " ORDER BY p.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT p.*, m.name AS mikrotik_name, m.ip_address AS mikrotik_ip,
                       m.username AS mikrotik_user, m.password AS mikrotik_pass, m.port AS mikrotik_port
                FROM packages p
                LEFT JOIN mikrotiks m ON m.id = p.mikrotik_id
                WHERE p.id = ? LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO packages (
                    name, price, mikrotik_id, rate_limit, burst_limit, 
                    burst_threshold, burst_time, priority, queue_type, 
                    only_one, local_pool, remote_pool, parent_queue, 
                    queue_insert_before, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['price'] ?? 0,
            $data['mikrotik_id'] ?? null,
            $data['rate_limit'] ?? null,
            $data['burst_limit'] ?? null,
            $data['burst_threshold'] ?? null,
            $data['burst_time'] ?? null,
            $data['priority'] ?? '8/8',
            $data['queue_type'] ?? 'default',
            $data['only_one'] ?? 'default',
            $data['local_pool'] ?? null,
            $data['remote_pool'] ?? null,
            $data['parent_queue'] ?? null,
            $data['queue_insert_before'] ?? null,
            $data['is_active'] ?? 1,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $cols = [
            'name', 'price', 'mikrotik_id', 'rate_limit', 'burst_limit',
            'burst_threshold', 'burst_time', 'priority', 'queue_type',
            'only_one', 'local_pool', 'remote_pool', 'parent_queue',
            'queue_insert_before', 'is_active'
        ];

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
        $sql = "UPDATE packages SET " . implode(', ', $fields) . " WHERE id = ?";
        return $this->pdo->prepare($sql)->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM packages WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

