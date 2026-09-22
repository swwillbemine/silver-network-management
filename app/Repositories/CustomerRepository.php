<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Customer Repository — Database operations for customers table
 */
class CustomerRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    public function all(array $filters = []): array
    {
        $sql = "SELECT c.*, 
                       p.name AS package_name, p.price AS package_price, p.rate_limit,
                       n.name AS node_name, 
                       po.name AS pop_name,
                       m.name AS router_name, m.ip_address AS router_ip
                FROM customers c
                LEFT JOIN packages p ON p.id = c.package_id
                LEFT JOIN nodes n ON n.id = c.node_id
                LEFT JOIN pops po ON po.id = c.pop_id
                LEFT JOIN mikrotiks m ON m.id = c.router_id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['node_id'])) {
            $sql .= " AND c.node_id = ?";
            $params[] = (int)$filters['node_id'];
        }

        if (!empty($filters['router_id'])) {
            $sql .= " AND c.router_id = ?";
            $params[] = (int)$filters['router_id'];
        }

        if (!empty($filters['package_id'])) {
            $sql .= " AND c.package_id = ?";
            $params[] = (int)$filters['package_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE ? OR c.username LIKE ? OR c.customer_number LIKE ? OR c.phone LIKE ?)";
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql .= " ORDER BY c.name ASC";

        if (isset($filters['limit'])) {
            $limit  = (int)$filters['limit'];
            $offset = (int)($filters['offset'] ?? 0);
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM customers c WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['node_id'])) {
            $sql .= " AND c.node_id = ?";
            $params[] = (int)$filters['node_id'];
        }

        if (!empty($filters['router_id'])) {
            $sql .= " AND c.router_id = ?";
            $params[] = (int)$filters['router_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE ? OR c.username LIKE ? OR c.customer_number LIKE ? OR c.phone LIKE ?)";
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT c.*, 
                       p.name AS package_name, p.price AS package_price, p.rate_limit,
                       n.name AS node_name, 
                       po.name AS pop_name,
                       m.name AS router_name, m.ip_address AS router_ip, m.username AS router_user, m.password AS router_pass, m.port AS router_port
                FROM customers c
                LEFT JOIN packages p ON p.id = c.package_id
                LEFT JOIN nodes n ON n.id = c.node_id
                LEFT JOIN pops po ON po.id = c.pop_id
                LEFT JOIN mikrotiks m ON m.id = c.router_id
                WHERE c.id = ? LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByNumber(string $number): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE customer_number = ? LIMIT 1");
        $stmt->execute([$number]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO customers (
                    customer_number, name, username, password, package_id, 
                    router_id, pop_id, node_id, address, phone, 
                    billing_due_date, discount, status, pppoe_profile, 
                    auto_isolated, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['customer_number'],
            $data['name'],
            $data['username'],
            $data['password'] ?? '',
            $data['package_id'] ?? null,
            $data['router_id'] ?? null,
            $data['pop_id'] ?? null,
            $data['node_id'] ?? null,
            $data['address'] ?? '',
            $data['phone'] ?? '',
            $data['billing_due_date'] ?? 20,
            $data['discount'] ?? 0,
            $data['status'] ?? 'active',
            $data['pppoe_profile'] ?? null,
            $data['auto_isolated'] ?? 1,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $updatable = [
            'name', 'username', 'password', 'package_id', 'router_id',
            'pop_id', 'node_id', 'address', 'phone', 'billing_due_date',
            'discount', 'status', 'pppoe_profile', 'auto_isolated'
        ];

        foreach ($updatable as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = ?";
                $params[] = $data[$col];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?";
        return $this->pdo->prepare($sql)->execute($params);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("UPDATE customers SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM customers WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

