<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * POP Repository — Database operations for pops table
 */
class PopRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    public function all(): array
    {
        $sql = "SELECT p.*, n.name AS node_name, n.address AS node_address,
                       (SELECT COUNT(*) FROM mikrotiks m WHERE m.pop_id = p.id) AS router_count
                FROM pops p 
                JOIN nodes n ON n.id = p.node_id 
                ORDER BY n.name, p.name";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT p.*, n.name AS node_name FROM pops p JOIN nodes n ON n.id = p.node_id WHERE p.id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByNode(int $nodeId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pops WHERE node_id = ? ORDER BY name ASC");
        $stmt->execute([$nodeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO pops (node_id, name, backup_power, battery_capacity, installation_date)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['node_id'],
            $data['name'],
            $data['backup_power'] ?? 'none',
            $data['battery_capacity'] ?? '',
            !empty($data['installation_date']) ? $data['installation_date'] : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE pops SET 
                    node_id = ?, name = ?, backup_power = ?, 
                    battery_capacity = ?, installation_date = ?
                WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['node_id'],
            $data['name'],
            $data['backup_power'] ?? 'none',
            $data['battery_capacity'] ?? '',
            !empty($data['installation_date']) ? $data['installation_date'] : null,
            $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM pops WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
