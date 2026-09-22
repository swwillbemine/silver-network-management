<?php
/**
 * api/v1/customers.php — SNM External API
 *
 * Autentikasi : Bearer token (API Key) di header Authorization
 *               atau query param ?api_key=...
 *
 * Endpoints   :
 *   GET /api/v1/customers.php              → semua pelanggan
 *   GET /api/v1/customers.php?id=X         → 1 pelanggan
 *   GET /api/v1/customers.php?search=nama  → cari nama/username/nomor
 *   GET /api/v1/customers.php?status=active|isolated|terminated|free
 *   GET /api/v1/customers.php?node_id=X
 *
 * Parameter tambahan:
 *   &live=1   → sertakan WAN IP live dari MikroTik (butuh permission read_live)
 *               default=1 jika key punya permission read_live
 *
 * Response: JSON
 */

// ── Bootstrap minimal tanpa session ──────────────────────────────────────────
define('APP_LOADED', true);
define('BASE_PATH', realpath(__DIR__ . '/../../'));

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/vendor/autoload.php';

use App\MikroTik\Connection;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Helper ────────────────────────────────────────────────────────────────────
function api_error(int $code, string $msg): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg, 'code' => $code]);
    exit;
}

function api_ok(mixed $data, array $meta = []): never {
    echo json_encode(['success' => true, 'data' => $data] + $meta);
    exit;
}

// ── Autentikasi API Key ───────────────────────────────────────────────────────
$raw_key = '';

// 1. Header: Authorization: Bearer <key>
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/^Bearer\s+(\S+)$/i', $auth_header, $m)) {
    $raw_key = $m[1];
}
// 2. Query param fallback
if (!$raw_key && isset($_GET['api_key'])) {
    $raw_key = trim($_GET['api_key']);
}

if (!$raw_key) {
    api_error(401, 'API key diperlukan. Gunakan header Authorization: Bearer <key> atau ?api_key=<key>');
}

// Validasi format: 64 char hex
if (!preg_match('/^[0-9a-f]{64}$/', $raw_key)) {
    api_error(401, 'Format API key tidak valid.');
}

// Lookup di DB
$stmt = $pdo->prepare("
    SELECT ak.*, u.name AS owner_name, u.role AS owner_role
    FROM api_keys ak
    JOIN users u ON u.id = ak.user_id
    WHERE ak.api_key = ? AND ak.is_active = 1
    LIMIT 1
");
$stmt->execute([$raw_key]);
$key_row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$key_row) {
    api_error(403, 'API key tidak valid atau sudah dinonaktifkan.');
}

// Update last_used_at (non-fatal)
try {
    $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?")
        ->execute([$key_row['id']]);
} catch (Exception $e) {}

$permissions = explode(',', $key_row['permissions']);
$can_live     = in_array('read_live', $permissions);

// ── Request hanya GET ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error(405, 'Hanya method GET yang diizinkan.');
}

// ── Parameter ─────────────────────────────────────────────────────────────────
$id       = isset($_GET['id'])      ? (int)$_GET['id']         : null;
$search   = isset($_GET['search'])  ? trim($_GET['search'])     : null;
$status   = isset($_GET['status'])  ? trim($_GET['status'])     : null;
$node_id  = isset($_GET['node_id']) ? (int)$_GET['node_id']    : null;
$want_live = $can_live && (($_GET['live'] ?? '1') !== '0');

$valid_statuses = ['active','isolated','terminated','free'];
if ($status && !in_array($status, $valid_statuses)) {
    api_error(400, 'Status tidak valid. Pilihan: ' . implode(', ', $valid_statuses));
}

// ── Query DB ──────────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($id) {
    $where[]  = 'c.id = ?';
    $params[] = $id;
}
if ($search) {
    $where[]  = '(c.name LIKE ? OR c.pppoe_username LIKE ? OR c.customer_number LIKE ? OR c.phone LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}
if ($status) {
    $where[]  = 'c.status = ?';
    $params[] = $status;
}
if ($node_id) {
    $where[]  = 'c.node_id = ?';
    $params[] = $node_id;
}

$sql = "
    SELECT
        c.id, c.customer_number, c.name, c.phone, c.email,
        c.status, c.billing_type, c.discount,
        c.pppoe_username,
        c.installation_date, c.billing_cycle_date,
        c.router_brand, c.router_type, c.router_mac,
        c.wifi_ssid, c.remote_mgmt_port,
        c.created_at,
        n.name   AS node_name,
        n.id     AS node_id,
        p.name   AS package_name,
        p.price  AS package_price,
        p.tx_max_limit, p.rx_max_limit,
        mk.id    AS router_id,
        mk.name  AS router_name,
        mk.host  AS router_host
    FROM customers c
    LEFT JOIN nodes     n  ON n.id  = c.node_id
    LEFT JOIN packages  p  ON p.id  = c.package_id
    LEFT JOIN mikrotiks mk ON mk.id = p.mikrotik_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.name ASC
";

$q = $pdo->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

if ($id && empty($rows)) {
    api_error(404, 'Pelanggan tidak ditemukan.');
}

// ── Format data & hapus field sensitif ───────────────────────────────────────
function format_speed(int $bps): string {
    if ($bps >= 1_000_000) return round($bps / 1_000_000, 1) . 'M';
    if ($bps >= 1_000)     return round($bps / 1_000, 1) . 'K';
    return $bps . 'bps';
}

$customers = [];
foreach ($rows as $r) {
    $customers[] = [
        'id'               => (int)$r['id'],
        'customer_number'  => $r['customer_number'],
        'name'             => $r['name'],
        'phone'            => $r['phone'],
        'email'            => $r['email'] ?: null,
        'status'           => $r['status'],
        'billing_type'     => $r['billing_type'],
        'discount'         => (int)$r['discount'],
        'pppoe_username'   => $r['pppoe_username'],
        'installation_date'=> $r['installation_date'],
        'billing_cycle_date'=> (int)$r['billing_cycle_date'],
        'router_device'    => [
            'brand' => $r['router_brand'] ?: null,
            'type'  => $r['router_type']  ?: null,
            'mac'   => $r['router_mac']   ?: null,
        ],
        'wifi_ssid'        => $r['wifi_ssid'] ?: null,
        'remote_mgmt_port' => $r['remote_mgmt_port'] ? (int)$r['remote_mgmt_port'] : null,
        'node'             => ['id' => (int)$r['node_id'], 'name' => $r['node_name']],
        'package'          => [
            'name'      => $r['package_name'],
            'price'     => (float)$r['package_price'],
            'speed_up'  => format_speed((int)$r['tx_max_limit']),
            'speed_down'=> format_speed((int)$r['rx_max_limit']),
        ],
        'router'           => [
            'id'   => (int)$r['router_id'],
            'name' => $r['router_name'],
            'host' => $r['router_host'],
        ],
        'created_at'       => $r['created_at'],
        // Live data — diisi di bawah jika $want_live
        'live'             => null,
    ];
}

// ── Live data dari MikroTik ───────────────────────────────────────────────────
if ($want_live && !empty($customers)) {
    // Kelompokkan per router agar hanya 1 koneksi API per router
    $by_router = []; // router_host => [cid => pppoe_username]
    $cid_to_idx = [];
    foreach ($customers as $idx => $c) {
        $host = $c['router']['host'] ?? null;
        if (!$host) continue;
        $cid_to_idx[$c['id']] = $idx;
        $by_router[$host][$c['id']] = $c['pppoe_username'];
    }

    // Ambil credentials per host
    $host_creds = [];
    foreach (array_keys($by_router) as $host) {
        $cr = $pdo->prepare("SELECT username, password, port, api_ssl FROM mikrotiks WHERE host = ? LIMIT 1");
        $cr->execute([$host]);
        $host_creds[$host] = $cr->fetch(PDO::FETCH_ASSOC);
    }

    foreach ($by_router as $host => $cid_map) {
        $creds = $host_creds[$host] ?? null;
        if (!$creds) continue;

        $conn = new Connection(
            $host, $creds['username'], $creds['password'],
            (int)$creds['port']
        );
        if (!$conn->isConnected()) continue;

        // Fetch semua sesi aktif sekaligus
        $sessions = $conn->query('/ppp/active', 'print');
        $session_map = []; // username => session data
        foreach ($sessions as $s) {
            $session_map[$s['name'] ?? ''] = $s;
        }

        // Fetch queue simple untuk traffic data
        $queues = $conn->query('/queue/simple', 'print');
        $queue_map = [];
        foreach ($queues as $q) {
            $qn = strtolower(trim($q['name'] ?? '', '<>'));
            $queue_map[$qn] = $q;
        }

        foreach ($cid_map as $cid => $uname) {
            $idx = $cid_to_idx[$cid] ?? null;
            if ($idx === null) continue;

            $sess = $session_map[$uname] ?? null;
            if (!$sess) {
                $customers[$idx]['live'] = ['online' => false];
                continue;
            }

            $wan_ip  = $sess['address'] ?? null;
            $uptime  = $sess['uptime']  ?? null;

            // Traffic dari queue
            $qkey    = strtolower($uname);
            $qdata   = $queue_map[$qkey] ?? $queue_map['pppoe-' . $qkey] ?? null;
            $parse   = fn($v) => array_map('intval', explode('/', $v ?? '0/0') + ['0','0']);
            [$tx_rate, $rx_rate]   = $parse($qdata['rate']  ?? null);
            [$tx_bytes, $rx_bytes] = $parse($qdata['bytes'] ?? null);

            $customers[$idx]['live'] = [
                'online'    => true,
                'wan_ip'    => $wan_ip,
                'uptime'    => $uptime,
                'traffic'   => [
                    'download_rate'  => format_speed($tx_rate) . '/s',
                    'upload_rate'    => format_speed($rx_rate) . '/s',
                    'download_total' => $tx_bytes,
                    'upload_total'   => $rx_bytes,
                ],
            ];
        }
    }
}

// ── Response ──────────────────────────────────────────────────────────────────
$fetched_at = date('Y-m-d\TH:i:sP');

if ($id) {
    // Single customer
    api_ok($customers[0], ['fetched_at' => $fetched_at]);
} else {
    api_ok($customers, [
        'total'      => count($customers),
        'fetched_at' => $fetched_at,
        'live_data'  => $want_live,
    ]);
}