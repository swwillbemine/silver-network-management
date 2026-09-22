<?php

namespace App\Services\Import;

use App\Core\Database;
use App\MikroTik\Connection;
use App\Repositories\RouterRepository;
use PDO;
use Exception;

/**
 * Service for importing PPPoE secrets from MikroTik into Silver Network Management
 */
class PPPImportService
{
    private PDO $pdo;
    private RouterRepository $routerRepo;

    public function __construct(?PDO $pdo = null, ?RouterRepository $routerRepo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
        $this->routerRepo = $routerRepo ?: new RouterRepository($this->pdo);
    }

    /**
     * Get list of PPPoE secrets from a specific router with import status
     */
    public function getRouterSecrets(int $routerId): array
    {
        $router = $this->routerRepo->find($routerId);
        if (!$router) {
            throw new Exception("Router tidak ditemukan.");
        }

        $conn = Connection::fromRouter($router);
        if (!$conn->isConnected()) {
            throw new Exception("Tidak dapat terhubung ke router MikroTik.");
        }

        $secrets = $conn->query('/ppp/secret', 'print');
        $profiles = $conn->query('/ppp/profile', 'print');

        $profileMap = [];
        foreach ($profiles as $p) {
            $profileMap[$p['name'] ?? ''] = $p;
        }

        // Get existing usernames in DB
        $stmt = $this->pdo->query("SELECT pppoe_username FROM customers");
        $existingUsernames = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $existingMap = array_flip($existingUsernames);

        $result = [];
        foreach ($secrets as $s) {
            $username = $s['name'] ?? '';
            if (!$username) continue;

            $profileName = $s['profile'] ?? 'default';
            $prof = $profileMap[$profileName] ?? [];
            $rateLimit = $prof['rate-limit'] ?? '-';

            $isImported = isset($existingMap[$username]);

            $result[] = [
                'name'         => $username,
                'password'     => $s['password'] ?? '',
                'profile'      => $profileName,
                'service'      => $s['service'] ?? 'any',
                'comment'      => $s['comment'] ?? '',
                'disabled'     => ($s['disabled'] ?? 'false') === 'true',
                'rate_limit'   => $rateLimit,
                'is_imported'  => $isImported,
            ];
        }

        return $result;
    }

    /**
     * Import selected secrets into customers
     */
    public function importSecrets(int $routerId, int $nodeId, int $packageId, array $selectedSecrets): array
    {
        $router = $this->routerRepo->find($routerId);
        if (!$router) {
            throw new Exception("Router tidak ditemukan.");
        }

        $conn = Connection::fromRouter($router);
        if (!$conn->isConnected()) {
            throw new Exception("Tidak dapat terhubung ke router MikroTik.");
        }

        $allSecrets = $conn->query('/ppp/secret', 'print');
        $secretMap = [];
        foreach ($allSecrets as $s) {
            $secretMap[$s['name'] ?? ''] = $s;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($selectedSecrets as $secretName) {
            $chk = $this->pdo->prepare("SELECT id FROM customers WHERE pppoe_username = ?");
            $chk->execute([$secretName]);
            if ($chk->fetch()) {
                $skipped++;
                continue;
            }

            $secret = $secretMap[$secretName] ?? null;
            $password = $secret['password'] ?? '123456';
            $comment = $secret['comment'] ?? $secretName;
            $status = (($secret['disabled'] ?? 'false') === 'true') ? 'isolated' : 'active';

            // Clean name from comment if formatted like Name (username)
            $name = $comment;
            if (preg_match('/^(.*?)\s*\(/', $comment, $m)) {
                $name = trim($m[1]);
            }
            if (!$name) $name = $secretName;

            // Generate customer number
            $cnumStmt = $this->pdo->prepare("SELECT COUNT(*) FROM customers WHERE customer_number = ?");
            do {
                $cnum = (string)random_int(1, 9) . str_pad((string)random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
                $cnumStmt->execute([$cnum]);
            } while ($cnumStmt->fetchColumn());

            $stmt = $this->pdo->prepare("INSERT INTO customers (
                customer_number, node_id, package_id, name, pppoe_username, pppoe_password,
                installation_date, status, billing_type, billing_cycle_date, billing_due_date, isolation_date
            ) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, 'normal', 20, 30, 1)");

            $stmt->execute([
                $cnum, $nodeId, $packageId, $name, $secretName, $password, $status
            ]);

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
        ];
    }
}

