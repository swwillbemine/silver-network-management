<?php

namespace App\Services\Import;

use App\Core\Database;
use App\MikroTik\Connection;
use App\Repositories\RouterRepository;
use PDO;
use Exception;

/**
 * Service for importing/syncing PPP Profiles across routers
 */
class PackageImportService
{
    private PDO $pdo;
    private RouterRepository $routerRepo;

    public function __construct(?PDO $pdo = null, ?RouterRepository $routerRepo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
        $this->routerRepo = $routerRepo ?: new RouterRepository($this->pdo);
    }

    /**
     * Fetch existing packages available to import from another router or database
     */
    public function getAvailablePackages(?int $sourceRouterId = null): array
    {
        $sql = "SELECT p.*, mk.name AS router_name 
                FROM packages p 
                JOIN mikrotiks mk ON mk.id = p.mikrotik_id";
        $params = [];
        if ($sourceRouterId) {
            $sql .= " WHERE p.mikrotik_id = ?";
            $params[] = $sourceRouterId;
        }
        $sql .= " ORDER BY mk.name, p.name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Import a package to a target router and create its PPP Profile on MikroTik
     */
    public function importToRouter(int $targetRouterId, array $pkgData): array
    {
        $targetRouter = $this->routerRepo->find($targetRouterId);
        if (!$targetRouter) {
            throw new Exception("Router tujuan tidak ditemukan.");
        }

        $conn = Connection::fromRouter($targetRouter);

        // Check if package already exists in DB for this router
        $chk = $this->pdo->prepare("SELECT id FROM packages WHERE mikrotik_id = ? AND name = ?");
        $chk->execute([$targetRouterId, $pkgData['name']]);
        if ($chk->fetch()) {
            return ['success' => false, 'error' => 'Paket dengan nama ini sudah ada di router tujuan.'];
        }

        // Insert into database
        $sql = "INSERT INTO packages (
            mikrotik_id, name, price, tx_max_limit, rx_max_limit,
            tx_burst_limit, rx_burst_limit, tx_burst_threshold, rx_burst_threshold,
            tx_burst_time, rx_burst_time, tx_priority, rx_priority,
            queue_type, parent_queue, local_address, remote_pool,
            dns_server1, dns_server2, mikrotik_profile_name, only_one, is_active, description
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $targetRouterId,
            $pkgData['name'],
            $pkgData['price'] ?? 0,
            $pkgData['tx_max_limit'] ?? 0,
            $pkgData['rx_max_limit'] ?? 0,
            $pkgData['tx_burst_limit'] ?? 0,
            $pkgData['rx_burst_limit'] ?? 0,
            $pkgData['tx_burst_threshold'] ?? 0,
            $pkgData['rx_burst_threshold'] ?? 0,
            $pkgData['tx_burst_time'] ?? 0,
            $pkgData['rx_burst_time'] ?? 0,
            $pkgData['tx_priority'] ?? 8,
            $pkgData['rx_priority'] ?? 8,
            $pkgData['queue_type'] ?? 'default',
            $pkgData['parent_queue'] ?? null,
            $pkgData['local_address'] ?? null,
            $pkgData['remote_pool'] ?? null,
            $pkgData['dns_server1'] ?? null,
            $pkgData['dns_server2'] ?? null,
            $pkgData['mikrotik_profile_name'] ?? $pkgData['name'],
            $pkgData['only_one'] ?? 'default',
            $pkgData['description'] ?? '',
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        // Sync to MikroTik router if connected
        $synced = false;
        if ($conn->isConnected()) {
            $profileName = $pkgData['mikrotik_profile_name'] ?? $pkgData['name'];
            $params = [
                'name' => $profileName,
                'rate-limit' => $pkgData['rate_limit'] ?? '',
                'comment' => '[SNM-PROFILE] ' . $profileName . ' | imported | ' . date('Y-m-d'),
            ];
            if (!empty($pkgData['local_address'])) $params['local-address'] = $pkgData['local_address'];
            if (!empty($pkgData['remote_pool'])) $params['remote-address'] = $pkgData['remote_pool'];
            if (!empty($pkgData['only_one']) && $pkgData['only_one'] !== 'default') $params['only-one'] = $pkgData['only_one'];

            $res = $conn->query('/ppp/profile', 'add', $params);
            $synced = true;
        }

        return ['success' => true, 'id' => $newId, 'synced_mikrotik' => $synced];
    }
}

