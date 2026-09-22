<?php

namespace App\Services;

use App\Repositories\CustomerRepository;
use App\Repositories\PackageRepository;
use App\Repositories\RouterRepository;
use App\MikroTik\Connection;
use App\MikroTik\PPPManager;
use Exception;

/**
 * Customer Business Logic & MikroTik Synchronization Service
 */
class CustomerService
{
    private CustomerRepository $customerRepo;
    private PackageRepository $packageRepo;
    private RouterRepository $routerRepo;

    public function __construct(
        ?CustomerRepository $customerRepo = null,
        ?PackageRepository $packageRepo = null,
        ?RouterRepository $routerRepo = null
    ) {
        $this->customerRepo = $customerRepo ?: new CustomerRepository();
        $this->packageRepo  = $packageRepo ?: new PackageRepository();
        $this->routerRepo   = $routerRepo ?: new RouterRepository();
    }

    public function getAll(array $filters = []): array
    {
        return $this->customerRepo->all($filters);
    }

    public function count(array $filters = []): int
    {
        return $this->customerRepo->count($filters);
    }

    public function getById(int $id): ?array
    {
        return $this->customerRepo->find($id);
    }

    public function generateCustomerNumber(): string
    {
        $prefix = date('ym');
        $random = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $num = $prefix . $random;

        while ($this->customerRepo->findByNumber($num) !== null) {
            $random = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $num = $prefix . $random;
        }

        return $num;
    }

    public function create(array $data, bool $syncMikrotik = true): array
    {
        if (empty($data['customer_number'])) {
            $data['customer_number'] = $this->generateCustomerNumber();
        }

        $id = $this->customerRepo->create($data);
        $syncResult = false;
        $syncError  = null;

        if ($syncMikrotik && !empty($data['router_id']) && !empty($data['username']) && !empty($data['password'])) {
            try {
                $syncResult = $this->pushSecretToMikroTik($id);
            } catch (Exception $e) {
                $syncError = $e->getMessage();
            }
        }

        return [
            'id'          => $id,
            'sync_status' => $syncResult,
            'sync_error'  => $syncError,
        ];
    }

    public function update(int $id, array $data, bool $syncMikrotik = true): bool
    {
        $updated = $this->customerRepo->update($id, $data);
        if ($updated && $syncMikrotik) {
            try {
                $this->pushSecretToMikroTik($id);
            } catch (Exception $e) {}
        }
        return $updated;
    }

    public function delete(int $id, bool $deleteFromMikrotik = true): bool
    {
        if ($deleteFromMikrotik) {
            try {
                $this->removeSecretFromMikroTik($id);
            } catch (Exception $e) {}
        }
        return $this->customerRepo->delete($id);
    }

    public function pushSecretToMikroTik(int $customerId): bool
    {
        $customer = $this->customerRepo->find($customerId);
        if (!$customer || empty($customer['router_id'])) {
            return false;
        }

        $router = $this->routerRepo->find((int)$customer['router_id']);
        if (!$router) {
            return false;
        }

        $conn = Connection::fromRouter($router);
        if (!$conn->isConnected()) {
            return false;
        }

        $ppp = new PPPManager($conn);

        // Find existing secret by name
        $existing = $ppp->getSecrets(['name' => $customer['username']]);
        $profile = $customer['pppoe_profile'] ?: ($customer['package_name'] ?? 'default');
        $comment = PPPManager::createComment($customer['name'], $customer['username']);

        if (!empty($existing[0]['.id'])) {
            $ppp->updateSecret($existing[0]['.id'], [
                'password' => (string)$customer['password'],
                'profile'  => (string)$profile,
                'comment'  => $comment,
            ]);
        } else {
            $ppp->addSecret(
                (string)$customer['username'],
                (string)$customer['password'],
                (string)$profile,
                'pppoe',
                $comment
            );
        }

        return true;
    }

    public function removeSecretFromMikroTik(int $customerId): bool
    {
        $customer = $this->customerRepo->find($customerId);
        if (!$customer || empty($customer['router_id']) || empty($customer['username'])) {
            return false;
        }

        $router = $this->routerRepo->find((int)$customer['router_id']);
        if (!$router) {
            return false;
        }

        $conn = Connection::fromRouter($router);
        if (!$conn->isConnected()) {
            return false;
        }

        $ppp = new PPPManager($conn);
        $existing = $ppp->getSecrets(['name' => $customer['username']]);
        if (!empty($existing[0]['.id'])) {
            $ppp->deleteSecret($existing[0]['.id']);
            return true;
        }

        return false;
    }

    public function getLiveStatus(int $customerId): array
    {
        $customer = $this->customerRepo->find($customerId);
        if (!$customer || empty($customer['router_id']) || empty($customer['username'])) {
            return ['is_online' => false, 'wan_ip' => null, 'uptime' => null];
        }

        $router = $this->routerRepo->find((int)$customer['router_id']);
        if (!$router) {
            return ['is_online' => false, 'wan_ip' => null, 'uptime' => null];
        }

        $conn = Connection::fromRouter($router);
        if (!$conn->isConnected()) {
            return ['is_online' => false, 'wan_ip' => null, 'uptime' => null, 'router_offline' => true];
        }

        $active = $conn->query('/ppp/active', 'print', ['name' => $customer['username']]);

        if (!empty($active[0])) {
            return [
                'is_online'    => true,
                'wan_ip'       => $active[0]['address'] ?? null,
                'uptime'       => $active[0]['uptime'] ?? null,
                'active_id'    => $active[0]['.id'] ?? null,
                'caller_id'    => $active[0]['caller-id'] ?? null,
                'service'      => $active[0]['service'] ?? 'pppoe',
            ];
        }

        return ['is_online' => false, 'wan_ip' => null, 'uptime' => null];
    }
}
