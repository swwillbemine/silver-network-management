<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Node Repository — Database operations for nodes table
 */
class NodeRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    public function all(): array
    {
        $sql = "SELECT n.*, (SELECT COUNT(*) FROM customers c WHERE c.node_id = n.id) AS cust_count 
                FROM nodes n 
                ORDER BY n.name ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM nodes WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO nodes (name, address, latitude, longitude, owner_name, owner_contact, description)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['address'] ?? '',
            !empty($data['latitude']) ? $data['latitude'] : null,
            !empty($data['longitude']) ? $data['longitude'] : null,
            $data['owner_name'] ?? '',
            $data['owner_contact'] ?? '',
            $data['description'] ?? '',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE nodes SET 
                    name = ?, address = ?, latitude = ?, longitude = ?,
                    owner_name = ?, owner_contact = ?, description = ?
                WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['address'] ?? '',
            !empty($data['latitude']) ? $data['latitude'] : null,
            !empty($data['longitude']) ? $data['longitude'] : null,
            $data['owner_name'] ?? '',
            $data['owner_contact'] ?? '',
            $data['description'] ?? '',
            $id
        ]);
    }

    public function updateContact(int $id, string $contact): bool
    {
        $stmt = $this->pdo->prepare("UPDATE nodes SET owner_contact = ? WHERE id = ?");
        return $stmt->execute([$contact, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM nodes WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
