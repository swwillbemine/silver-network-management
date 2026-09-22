<?php

namespace App\Services;

use App\Repositories\PackageRepository;
use App\Repositories\RouterRepository;
use App\MikroTik\Connection;
use App\MikroTik\PPPManager;
use Exception;

/**
 * Package Business Logic & MikroTik Profile Synchronization Service
 */
class PackageService
{
    private PackageRepository $packageRepo;
    private RouterRepository $routerRepo;

    public function __construct(?PackageRepository $packageRepo = null, ?RouterRepository $routerRepo = null)
    {
        $this->packageRepo = $packageRepo ?: new PackageRepository();
        $this->routerRepo  = $routerRepo ?: new RouterRepository();
    }

    public function getAll(?int $routerId = null): array
    {
        return $this->packageRepo->all($routerId);
    }

    public function getById(int $id): ?array
    {
        return $this->packageRepo->find($id);
    }

    public function create(array $data, bool $pushMikrotik = true): int
    {
        $id = $this->packageRepo->create($data);
        if ($pushMikrotik && !empty($data['mikrotik_id'])) {
            try {
                $this->pushToMikroTik($id);
            } catch (Exception $e) {}
        }
        return $id;
    }

    public function update(int $id, array $data, bool $pushMikrotik = true): bool
    {
        $updated = $this->packageRepo->update($id, $data);
        if ($updated && $pushMikrotik) {
            try {
                $this->pushToMikroTik($id);
            } catch (Exception $e) {}
        }
        return $updated;
    }

    public function delete(int $id): bool
    {
        return $this->packageRepo->delete($id);
    }

    public function pushToMikroTik(int $packageId): bool
    {
        $pkg = $this->packageRepo->find($packageId);
        if (!$pkg || empty($pkg['mikrotik_id'])) {
            return false;
        }

        $router = $this->routerRepo->find((int)$pkg['mikrotik_id']);
        if (!$router) {
            return false;
        }

        $conn = Connection::fromRouter($router);
        if (!$conn->isConnected()) {
            return false;
        }

        $ppp = new PPPManager($conn);

        // Check if profile exists
        $profiles = $conn->query('/ppp/profile', 'print', ['name' => $pkg['name']]);
        $data = [
            'name'           => (string)$pkg['name'],
            'local-address'  => (string)($pkg['local_pool'] ?: ''),
            'remote-address' => (string)($pkg['remote_pool'] ?: ''),
            'rate-limit'     => (string)($pkg['rate_limit'] ?: ''),
        ];

        if (!empty($pkg['only_one']) && $pkg['only_one'] !== 'default') {
            $data['only-one'] = $pkg['only_one'];
        }

        if (!empty($profiles[0]['.id'])) {
            $conn->query('/ppp/profile', 'set', $data, $profiles[0]['.id']);
        } else {
            $conn->query('/ppp/profile', 'add', $data);
        }

        return true;
    }
}

