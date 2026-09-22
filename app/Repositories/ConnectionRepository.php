<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Connection Repository — Database operations for connections table (Network Links)
 */
class ConnectionRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    public function all(): array
    {
        $sql = "SELECT c.*, 
                    sn.name AS start_name, sn.latitude AS start_lat, sn.longitude AS start_lng,
                    en.name AS end_name,   en.latitude AS end_lat,   en.longitude AS end_lng
                FROM connections c
                JOIN nodes sn ON sn.id = c.start_node_id
                JOIN nodes en ON en.id = c.end_node_id
                ORDER BY c.name ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT c.*, 
                    sn.name AS start_name, sn.latitude AS start_lat, sn.longitude AS start_lng,
                    en.name AS end_name,   en.latitude AS end_lat,   en.longitude AS end_lng
                FROM connections c
                JOIN nodes sn ON sn.id = c.start_node_id
                JOIN nodes en ON en.id = c.end_node_id
                WHERE c.id = ? LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO connections (
                    name, start_node_id, end_node_id, type, length_estimated, 
                    core_color, loss_db, wireless_frequency, wireless_ssid, 
                    wireless_password, wireless_ip_radio_ap, wireless_ip_radio_station, 
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['start_node_id'],
            $data['end_node_id'],
            $data['type'],
            !empty($data['length_estimated']) ? $data['length_estimated'] : 0,
            $data['core_color'] ?? '',
            !empty($data['loss_db']) ? $data['loss_db'] : null,
            $data['wireless_frequency'] ?? '',
            $data['wireless_ssid'] ?? '',
            $data['wireless_password'] ?? '',
            $data['wireless_ip_radio_ap'] ?? '',
            $data['wireless_ip_radio_station'] ?? '',
            $data['status'] ?? 'up',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE connections SET 
                    name = ?, start_node_id = ?, end_node_id = ?, type = ?, 
                    length_estimated = ?, core_color = ?, loss_db = ?, 
                    wireless_frequency = ?, wireless_ssid = ?, wireless_password = ?, 
                    wireless_ip_radio_ap = ?, wireless_ip_radio_station = ?, status = ?
                WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['start_node_id'],
            $data['end_node_id'],
            $data['type'],
            !empty($data['length_estimated']) ? $data['length_estimated'] : 0,
            $data['core_color'] ?? '',
            !empty($data['loss_db']) ? $data['loss_db'] : null,
            $data['wireless_frequency'] ?? '',
            $data['wireless_ssid'] ?? '',
            $data['wireless_password'] ?? '',
            $data['wireless_ip_radio_ap'] ?? '',
            $data['wireless_ip_radio_station'] ?? '',
            $data['status'] ?? 'up',
            $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM connections WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
