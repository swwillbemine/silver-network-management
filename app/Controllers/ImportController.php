<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\MikroTik\Connection;
use PDO;

class ImportController
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    /**
     * Import PPPoE Secrets and Profiles (import_pppoe.php)
     */
    public function pppoe(): void
    {
        Middleware::requireLogin();

        $msg = '';

        $speedToBps = function (float $val, string $unit): int {
            return match ($unit) {
                'G' => (int)($val * 1_000_000_000),
                'M' => (int)($val * 1_000_000),
                'k' => (int)($val * 1_000),
                default => (int)$val,
            };
        };

        $parseRateLimit = function (string $rate) {
            $rate = trim($rate);
            if (!$rate || $rate === '0' || $rate === '-') return [0, 0];

            $first_pair = explode(' ', $rate)[0];
            $parts = explode('/', $first_pair);
            $mk_rx = trim($parts[0] ?? '0');
            $mk_tx = trim($parts[1] ?? $mk_rx);

            $toVal = function (string $s): int {
                $s = trim($s);
                if (!$s || $s === '0') return 0;
                if (preg_match('/^([\d.]+)([kKmMgG]?)$/', $s, $m)) {
                    $unit = strtolower($m[2]);
                    $mul  = match ($unit) { 'g' => 1_000_000_000, 'm' => 1_000_000, 'k' => 1_000, default => 1 };
                    return (int)round(floatval($m[1]) * $mul);
                }
                return 0;
            };

            return [$toVal($mk_rx), $toVal($mk_tx)];
        };

        // ── POST: Import PPPoE Secrets ────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_secrets') {
            $router_id  = (int)$_POST['router_id'];
            $node_id    = (int)$_POST['node_id'];
            $package_id = (int)$_POST['package_id'];
            $selected   = $_POST['secrets'] ?? [];

            if (!$selected) {
                $msg = 'warning:Tidak ada secret yang dipilih.';
            } else {
                $rq = $this->pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
                $rq->execute([$router_id]);
                $router = $rq->fetch(PDO::FETCH_ASSOC);

                $conn = $router ? Connection::fromRouter($router) : null;

                $all_secrets = [];
                if ($conn && $conn->isConnected()) {
                    $raw = $conn->query('/ppp/secret', 'print');
                    foreach ($raw as $s) {
                        if (!empty($s['name'])) $all_secrets[$s['name']] = $s;
                    }
                }

                $imported = 0;
                $skipped = 0;
                foreach ($selected as $sname) {
                    $exists = $this->pdo->prepare("SELECT id FROM customers WHERE pppoe_username=?");
                    $exists->execute([$sname]);
                    if ($exists->fetch()) {
                        $skipped++;
                        continue;
                    }

                    $s   = $all_secrets[$sname] ?? [];
                    $pass = $s['password'] ?? '';
                    $raw_comment = $s['comment'] ?? '';
                    $cust_name = preg_replace('/^\[SNM-SECRET\]\s*/', '', $raw_comment);
                    $cust_name = preg_replace('/\s*\|.*$/', '', $cust_name);
                    $cust_name = trim($cust_name) ?: $sname;

                    $this->pdo->prepare("INSERT INTO customers
                        (node_id,package_id,name,pppoe_username,pppoe_password,status,installation_date)
                        VALUES (?,?,?,?,?,'active',CURDATE())")
                        ->execute([$node_id, $package_id, $cust_name, $sname, $pass]);
                    $imported++;
                }
                $msg = "success:Import PPPoE selesai. {$imported} pelanggan diimpor, {$skipped} dilewati (sudah ada).";
            }
        }

        // ── POST: Import PPP Profiles ─────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_profiles') {
            $router_id = (int)$_POST['router_id'];
            $selected  = $_POST['profiles'] ?? [];

            if (!$selected) {
                $msg = 'warning:Tidak ada profile yang dipilih.';
            } else {
                $rq = $this->pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
                $rq->execute([$router_id]);
                $router = $rq->fetch(PDO::FETCH_ASSOC);
                if (!$router) {
                    $msg = 'danger:Router tidak ditemukan.';
                } else {
                    $conn = Connection::fromRouter($router);
                    $all_profiles = [];
                    if ($conn && $conn->isConnected()) {
                        $raw = $conn->query('/ppp/profile', 'print');
                        foreach ($raw as $p) {
                            if (!empty($p['name'])) $all_profiles[$p['name']] = $p;
                        }
                    }

                    $imported = 0;
                    $skipped = 0;
                    foreach ($selected as $pname) {
                        $exists = $this->pdo->prepare("SELECT id FROM packages WHERE mikrotik_id=? AND mikrotik_profile_name=?");
                        $exists->execute([$router_id, $pname]);
                        if ($exists->fetch()) {
                            $skipped++;
                            continue;
                        }

                        $p = $all_profiles[$pname] ?? [];
                        $rate_limit = $p['rate-limit'] ?? '0/0';
                        [$tx_bps, $rx_bps] = $parseRateLimit($rate_limit);

                        $pkg_name = trim($_POST['pkg_name'][$pname] ?? $pname);
                        $price    = (int)($_POST['pkg_price'][$pname] ?? 0);

                        $this->pdo->prepare("INSERT INTO packages
                            (mikrotik_id, name, price, tx_max_limit, rx_max_limit,
                             tx_burst_limit, rx_burst_limit, tx_burst_threshold, rx_burst_threshold,
                             tx_burst_time, rx_burst_time, tx_priority, rx_priority,
                             queue_type, mikrotik_profile_name, is_active, description)
                            VALUES (?,?,?,?,?,0,0,0,0,0,0,8,8,'default',?,1,?)")
                            ->execute([
                                $router_id, $pkg_name, $price, $tx_bps, $rx_bps,
                                $pname,
                                'Diimport dari MikroTik — ' . date('Y-m-d')
                            ]);
                        $imported++;
                    }
                    $msg = "success:Import Profile selesai. {$imported} paket diimpor, {$skipped} dilewati (sudah ada).";
                }
            }
        }

        // ── AJAX: Fetch PPPoE secrets ──────────────────────────────────────────────
        if (isset($_GET['ajax']) && $_GET['ajax'] === 'secrets') {
            header('Content-Type: application/json');
            $rq = $this->pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
            $rq->execute([(int)($_GET['router_id'] ?? 0)]);
            $router = $rq->fetch(PDO::FETCH_ASSOC);
            if (!$router) {
                Response::json(['error' => 'Router not found']);
                return;
            }

            $conn = Connection::fromRouter($router);
            if (!$conn->isConnected()) {
                Response::json(['error' => 'Tidak bisa terhubung ke router: ' . $router['host']]);
                return;
            }

            $raw = $conn->query('/ppp/secret', 'print');
            $existing = array_flip($this->pdo->query("SELECT pppoe_username FROM customers")->fetchAll(PDO::FETCH_COLUMN));

            $secrets = [];
            foreach ($raw as $s) {
                $name = $s['name'] ?? '';
                if (!$name) continue;
                $service = strtolower(trim($s['service'] ?? 'any'));
                if (in_array($service, ['pptp', 'l2tp', 'sstp', 'ovpn', 'ethernet'])) continue;
                if ($service !== 'pppoe' && $service !== 'any' && $service !== '') continue;

                $raw_comment = $s['comment'] ?? '';
                $display_name = preg_replace('/^\[SNM-SECRET\]\s*/', '', $raw_comment);
                $display_name = trim(preg_replace('/\s*\|.*$/', '', $display_name));

                $secrets[] = [
                    'name'     => $name,
                    'profile'  => $s['profile'] ?? 'default',
                    'service'  => $service,
                    'comment'  => $display_name,
                    'disabled' => ($s['disabled'] ?? 'false') === 'true',
                    'imported' => isset($existing[$name]),
                ];
            }
            Response::json(['secrets' => $secrets, 'total' => count($secrets)]);
            return;
        }

        // ── AJAX: Fetch PPP Profiles ───────────────────────────────────────────────
        if (isset($_GET['ajax']) && $_GET['ajax'] === 'profiles') {
            header('Content-Type: application/json');
            $router_id = (int)($_GET['router_id'] ?? 0);
            $rq = $this->pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
            $rq->execute([$router_id]);
            $router = $rq->fetch(PDO::FETCH_ASSOC);
            if (!$router) {
                Response::json(['error' => 'Router not found']);
                return;
            }

            $conn = Connection::fromRouter($router);
            if (!$conn->isConnected()) {
                Response::json(['error' => 'Tidak bisa terhubung']);
                return;
            }

            $raw = $conn->query('/ppp/profile', 'print');

            $stmt = $this->pdo->prepare("SELECT mikrotik_profile_name FROM packages WHERE mikrotik_id=?");
            $stmt->execute([$router_id]);
            $in_db = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

            $profiles = [];
            $skip = ['default', 'default-encryption', 'default-dialin'];
            foreach ($raw as $p) {
                $name = $p['name'] ?? '';
                if (!$name || in_array($name, $skip)) continue;
                $is_app = str_contains($p['comment'] ?? '', '[SNM-PROFILE]');
                $profiles[] = [
                    'name'        => $name,
                    'rate_limit'  => $p['rate-limit'] ?? '-',
                    'local_addr'  => $p['local-address'] ?? '',
                    'remote_pool' => $p['remote-address'] ?? '',
                    'comment'     => $p['comment'] ?? '',
                    'is_app'      => $is_app,
                    'in_db'       => isset($in_db[$name]),
                ];
            }
            Response::json(['profiles' => $profiles, 'router_id' => $router_id]);
            return;
        }

        $routers  = $this->pdo->query("SELECT * FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $nodes    = $this->pdo->query("SELECT id, name FROM nodes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $packages = $this->pdo->query("SELECT p.*,mk.name AS router_name FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id ORDER BY mk.name,p.name")->fetchAll(PDO::FETCH_ASSOC);

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        View::render('import/pppoe', [
            'routers' => $routers,
            'nodes' => $nodes,
            'packages' => $packages,
            'msg' => $msg,
            'msg_type' => $msg_type,
            'msg_text' => $msg_text,
        ]);
    }
}

