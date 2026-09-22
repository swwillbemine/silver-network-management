<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Billing Repository — Database operations for billings and payments tables
 */
class BillingRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    public function all(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $sql = "SELECT b.*, 
                       c.name AS cust_name, c.customer_number, c.phone, c.address, c.pppoe_username,
                       pk.name AS pkg_name, c.discount AS cust_discount, pk.price AS pkg_price,
                       n.name AS node_name, n.address AS node_address,
                       po.name AS pop_name,
                       py.method, py.amount_paid, py.notes AS pay_notes
                FROM billings b
                JOIN customers c ON c.id = b.customer_id
                JOIN packages pk ON pk.id = c.package_id
                JOIN mikrotiks mk ON mk.id = pk.mikrotik_id
                JOIN pops po ON po.id = mk.pop_id
                JOIN nodes n ON n.id = c.node_id
                LEFT JOIN payments py ON py.billing_id = b.id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['period'])) {
            $sql .= " AND b.period = ?";
            $params[] = $filters['period'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND b.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['customer_id'])) {
            $sql .= " AND b.customer_id = ?";
            $params[] = (int)$filters['customer_id'];
        }

        $sql .= " ORDER BY b.due_date ASC, c.name ASC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) 
                FROM billings b
                JOIN customers c ON c.id = b.customer_id
                JOIN packages pk ON pk.id = c.package_id
                JOIN mikrotiks mk ON mk.id = pk.mikrotik_id
                JOIN pops po ON po.id = mk.pop_id
                JOIN nodes n ON n.id = c.node_id
                LEFT JOIN payments py ON py.billing_id = b.id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['period'])) {
            $sql .= " AND b.period = ?";
            $params[] = $filters['period'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND b.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['customer_id'])) {
            $sql .= " AND b.customer_id = ?";
            $params[] = (int)$filters['customer_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT b.*, 
                       c.name AS cust_name, c.customer_number, c.phone, c.address, c.pppoe_username,
                       pk.name AS pkg_name, c.discount AS cust_discount, pk.price AS pkg_price,
                       n.name AS node_name, n.address AS node_address,
                       po.name AS pop_name,
                       py.method, py.amount_paid, py.notes AS pay_notes
                FROM billings b
                JOIN customers c ON c.id = b.customer_id
                JOIN packages pk ON pk.id = c.package_id
                JOIN mikrotiks mk ON mk.id = pk.mikrotik_id
                JOIN pops po ON po.id = mk.pop_id
                JOIN nodes n ON n.id = c.node_id
                LEFT JOIN payments py ON py.billing_id = b.id
                WHERE b.id = ? LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function exists(int $customerId, string $period): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM billings WHERE customer_id = ? AND period = ?");
        $stmt->execute([$customerId, $period]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO billings (customer_id, period, amount, discount_amount, status, due_date)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['customer_id'],
            $data['period'],
            $data['amount'],
            $data['discount_amount'] ?? 0,
            $data['status'] ?? 'unpaid',
            $data['due_date'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $paidAt = null): bool
    {
        if ($status === 'paid') {
            $sql = "UPDATE billings SET status = 'paid', paid_at = " . ($paidAt ? "?" : "NOW()") . " WHERE id = ?";
            $params = $paidAt ? [$paidAt, $id] : [$id];
        } else {
            $sql = "UPDATE billings SET status = ?, paid_at = NULL WHERE id = ?";
            $params = [$status, $id];
        }

        return $this->pdo->prepare($sql)->execute($params);
    }

    public function recordPayment(array $data): int
    {
        $sql = "INSERT INTO payments (billing_id, processed_by_user_id, amount_paid, method, notes)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['billing_id'],
            $data['processed_by_user_id'] ?? null,
            $data['amount_paid'],
            $data['method'] ?? 'cash',
            $data['notes'] ?? '',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getPayments(int $billingId): array
    {
        $sql = "SELECT p.*, u.name AS processed_by_name 
                FROM payments p
                LEFT JOIN users u ON u.id = p.processed_by_user_id
                WHERE p.billing_id = ?
                ORDER BY p.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$billingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummary(string $period): array
    {
        $summary = $this->pdo->prepare("SELECT status, COUNT(*) AS cnt, SUM(amount) AS total FROM billings WHERE period=? GROUP BY status");
        $summary->execute([$period]);
        $stats = [];
        foreach ($summary->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[$row['status']] = $row;
        }
        return $stats;
    }
}

