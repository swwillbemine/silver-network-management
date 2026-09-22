<?php

namespace App\Services;

use App\Repositories\RouterRepository;
use App\Repositories\CustomerRepository;
use App\MikroTik\Connection;
use App\MikroTik\SystemManager;
use App\MikroTik\InterfaceManager;
use App\MikroTik\IPPoolManager;
use App\Core\Database;
use PDO;

/**
 * Router & Device Management Service
 */
class RouterService
{
    private RouterRepository $routerRepo;
    private CustomerRepository $customerRepo;
    private PDO $pdo;

    public function __construct(
        ?RouterRepository $routerRepo = null,
        ?CustomerRepository $customerRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo          = $pdo ?: Database::getConnection();
        $this->routerRepo   = $routerRepo ?: new RouterRepository($this->pdo);
        $this->customerRepo = $customerRepo ?: new CustomerRepository($this->pdo);
    }

    public function getAll(): array
    {
        return $this->routerRepo->all();
    }

    public function getById(int $id): ?array
    {
        return $this->routerRepo->find($id);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO mikrotiks (pop_id, name, host, port, username, password, api_ssl) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            $data['pop_id'],
            $data['name'],
            $data['host'],
            $data['port'] ?: 8728,
            $data['username'],
            $data['password'],
            !empty($data['api_ssl']) ? 1 : 0
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if (!empty($data['password'])) {
            $stmt = $this->pdo->prepare("UPDATE mikrotiks SET pop_id=?, name=?, host=?, port=?, username=?, password=?, api_ssl=? WHERE id=?");
            return $stmt->execute([
                $data['pop_id'],
                $data['name'],
                $data['host'],
                $data['port'] ?: 8728,
                $data['username'],
                $data['password'],
                !empty($data['api_ssl']) ? 1 : 0,
                $id
            ]);
        }

        $stmt = $this->pdo->prepare("UPDATE mikrotiks SET pop_id=?, name=?, host=?, port=?, username=?, api_ssl=? WHERE id=?");
        return $stmt->execute([
            $data['pop_id'],
            $data['name'],
            $data['host'],
            $data['port'] ?: 8728,
            $data['username'],
            !empty($data['api_ssl']) ? 1 : 0,
            $id
        ]);
    }

    public function delete(int $id): bool
    {
        $custCount = $this->customerRepo->count(['router_id' => $id]);
        if ($custCount > 0) {
            return false;
        }
        return $this->routerRepo->delete($id);
    }

    public function testConnection(int $id): bool
    {
        $router = $this->routerRepo->find($id);
        if (!$router) {
            return false;
        }

        $conn = Connection::fromRouter($router);
        $ok = $conn->isConnected();
        $this->routerRepo->updateStatus($id, $ok ? 'online' : 'offline');
        return $ok;
    }

    public function getConnection(int $routerId): ?Connection
    {
        $router = $this->routerRepo->find($routerId);
        if (!$router) {
            return null;
        }

        $conn = Connection::fromRouter($router);
        return $conn->isConnected() ? $conn : null;
    }

    // ── IP Pool MikroTik ────────────────────────────────────────────────────────

    public function getPools(int $routerId): array
    {
        $conn = $this->getConnection($routerId);
        if (!$conn) {
            return [];
        }

        $poolMgr = new IPPoolManager($conn);
        return $poolMgr->getPools();
    }

    public function createPool(int $routerId, string $name, string $ranges): array
    {
        $conn = $this->getConnection($routerId);
        if (!$conn) {
            return ['!trap' => 'Koneksi ke router gagal'];
        }

        $poolMgr = new IPPoolManager($conn);
        return $poolMgr->addPool($name, $ranges);
    }

    public function updatePool(int $routerId, string $poolId, string $name, string $ranges): array
    {
        $conn = $this->getConnection($routerId);
        if (!$conn) {
            return ['!trap' => 'Koneksi ke router gagal'];
        }

        return $conn->query('/ip/pool', 'set', ['name' => $name, 'ranges' => $ranges], $poolId);
    }

    public function deletePool(int $routerId, string $poolId): array
    {
        $conn = $this->getConnection($routerId);
        if (!$conn) {
            return ['!trap' => 'Koneksi ke router gagal'];
        }

        $poolMgr = new IPPoolManager($conn);
        return $poolMgr->deletePool($poolId);
    }

    // ── PPPoE Server MikroTik ───────────────────────────────────────────────────

    public function getPppoeServers(int $routerId): array
    {
        $conn = $this->getConnection($routerId);
        if (!$conn) {
            return [];
        }

        $raw = $conn->query('/interface/pppoe-server/server', 'print') ?? [];
        $servers = [];
        foreach ($raw as $item) {
            if (is_array($item) && isset($item['service-name'])) {
                $servers[] = $item;
            }
        }
        return $servers;
    }

    public function getRouterInterfaces(int $routerId): array
    {
        $conn = $this->getConnection($routerId);
        if (!$conn) {
            return [];
        }

        $raw = $conn->query('/interface', 'print') ?? [];
        $ifaces = [];
        foreach ($raw as $iface) {
            if (isset($iface['name'])) {
                $ifaces[] = $iface['name'];
            }
        }
        sort($ifaces);
        return $ifaces;
    }

    public function createPppoeServer(int $routerId, array $data): array
    {
        $conn = $this->getConnection($routerId);
        if (!$conn) {
            return [['!trap' => true, 'message' => 'Koneksi ke router gagal']];
        }

        return $conn->query('/interface/pppoe-server/server', 'add', $data);
    }

    public function updatePppoeServer(int $routerId, string $id, array $data): array
    {
        $conn = $this->getConnection($routerId);
        if (!$conn) {
            return [['!trap' => true, 'message' => 'Koneksi ke router gagal']];
        }

        return $conn->query('/interface/pppoe-server/server', 'set', $data, $id);
    }

    public function deletePppoeServer(int $routerId, string $id): array
    {
        $conn = $this->getConnection($routerId);
        if (!$conn) {
            return [['!trap' => true, 'message' => 'Koneksi ke router gagal']];
        }

        return $conn->query('/interface/pppoe-server/server', 'remove', [], $id);
    }

    public function togglePppoeServer(int $routerId, string $id, string $disabled): array
    {
        $conn = $this->getConnection($routerId);
        if (!$conn) {
            return [['!trap' => true, 'message' => 'Koneksi ke router gagal']];
        }

        return $conn->query('/interface/pppoe-server/server', 'set', ['disabled' => $disabled], $id);
    }

    // ── Hotspot ─────────────────────────────────────────────────────────────────

    public function getHotspots(): array
    {
        return $this->pdo->query("
            SELECT h.*, n.name AS node_name, n.latitude, n.longitude, n.address AS node_address, mk.name AS router_name
            FROM hotspots h
            JOIN nodes n ON n.id = h.node_id
            JOIN mikrotiks mk ON mk.id = h.mikrotik_id
            ORDER BY h.name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createHotspot(array $data): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO hotspots (node_id, mikrotik_id, name, ssid, is_active) VALUES (?,?,?,?,?)");
        return $stmt->execute([
            $data['node_id'],
            $data['mikrotik_id'],
            $data['name'],
            $data['ssid'],
            !empty($data['is_active']) ? 1 : 0
        ]);
    }

    public function updateHotspot(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("UPDATE hotspots SET node_id=?, mikrotik_id=?, name=?, ssid=?, is_active=? WHERE id=?");
        return $stmt->execute([
            $data['node_id'],
            $data['mikrotik_id'],
            $data['name'],
            $data['ssid'],
            !empty($data['is_active']) ? 1 : 0,
            $id
        ]);
    }

    public function deleteHotspot(int $id): bool
    {
        return $this->pdo->prepare("DELETE FROM hotspots WHERE id=?")->execute([$id]);
    }

    public function getLiveDetails(int $id, ?string $iface = null): array
    {
        $router = $this->routerRepo->find($id);
        if (!$router) {
            return ['status' => 'offline', 'error' => 'Router not found'];
        }

        $conn = Connection::fromRouter($router);
        if (!$conn->isConnected()) {
            $this->routerRepo->updateStatus($id, 'offline');
            return ['status' => 'offline', 'error' => 'Could not connect to router'];
        }

        $this->routerRepo->updateStatus($id, 'online');
        $sysMgr = new SystemManager($conn);
        $ifMgr  = new InterfaceManager($conn);

        $resource    = $sysMgr->getResource();
        $routerboard = $sysMgr->getRouterboard();
        $interfaces  = $ifMgr->getInterfaces();

        $selectedIfaceData = null;
        if ($iface) {
            $matched = $ifMgr->getInterfaceByName($iface);
            $selectedIfaceData = $matched[0] ?? null;
        }

        return [
            'status'         => 'online',
            'resource'       => $resource,
            'routerboard'    => $routerboard,
            'interfaces'     => $interfaces,
            'selected_iface' => $selectedIfaceData,
        ];
    }
}
