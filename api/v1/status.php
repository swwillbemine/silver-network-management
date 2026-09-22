<?php
/**
 * api/v1/status.php — SNM Monitoring Status Endpoint
 *
 * Dirancang khusus untuk monitoring tools. Mengembalikan semua pelanggan
 * sekaligus dengan status online/offline dan WAN IP saat ini.
 *
 * Identifier stabil: customer_number — tidak berubah walau IP PPPoE ganti.
 *
 * GET /api/v1/status.php
 *
 * Parameter opsional:
 *   ?node_id=X      → filter per node
 *   ?router_id=X    → filter per MikroTik
 *   ?status=active  → filter status DB (active/isolated/terminated/free)
 *
 * Autentikasi:
 *   Header  : Authorization: Bearer <api_key>
 *   Param   : ?api_key=<api_key>
 *   Butuh permission: read_live
 *
 * Response per pelanggan:
 * {
 *   "customer_number": "2323",        // ← identifier stabil untuk monitoring
 *   "id": 45,                         // ID internal DB
 *   "name": "Budi Santoso",
 *   "pppoe_username": "budi-santoso",
 *   "node": "Node Timur",
 *   "package": "20Mbps Home",
 *   "service_status": "active",       // status di DB app ini
 *   "is_online": true,                // false jika sesi PPPoE tidak ada
 *   "wan_ip": "10.25.0.187",          // null jika offline
 *   "uptime": "1d2h3m",               // null jika offline
 *   "router_host": "192.168.88.1"     // MikroTik yang melayani pelanggan ini
 * }
 */

define('APP_LOADED', true);
define('BASE_PATH', realpath(__DIR__ . '/../../'));

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/vendor/autoload.php';

use App\MikroTik\Connection;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

$t_start = microtime(true);

// ── Helper ────────────────────────────────────────────────────────────────────
function api_err(int $code, string $msg): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ── Autentikasi ───────────────────────────────────────────────────────────────
$raw_key = '';
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/^Bearer\s+(\S+)$/i', $auth_header, $m)) $raw_key = $m[1];
if (!$raw_key) $raw_key = trim($_GET['api_key'] ?? '');

if (!$raw_key)                               api_err(401, 'API key diperlukan.');
if (!preg_match('/^[0-9a-f]{64}$/', $raw_key)) api_err(401, 'Format API key tidak valid.');

$stmt = $pdo->prepare("
    SELECT ak.permissions, ak.id AS key_id
    FROM api_keys ak
    WHERE ak.api_key = ? AND ak.is_active = 1
    LIMIT 1
");
$stmt->execute([$raw_key]);
$key_row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$key_row) api_err(403, 'API key tidak valid atau sudah dinonaktifkan.');

$permissions = explode(',', $key_row['permissions']);
if (!in_array('read_live', $permissions)) {
    api_err(403, 'API key tidak memiliki permission read_live yang diperlukan endpoint ini.');
}

// Update last_used_at (non-fatal)
try { $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id=?")->execute([$key_row['key_id']]); }
catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') api_err(405, 'Hanya GET yang diizinkan.');

// ── Filter parameter ──────────────────────────────────────────────────────────
$filter_node   = isset($_GET['node_id'])   ? (int)$_GET['node_id']   : null;
$filter_router = isset($_GET['router_id']) ? (int)$_GET['router_id'] : null;
$filter_status = $_GET['status'] ?? null;

$valid_statuses = ['active', 'isolated', 'terminated', 'free'];
if ($filter_status && !in_array($filter_status, $valid_statuses))
    api_err(400, 'Status tidak valid: ' . implode(', ', $valid_statuses));

// ── Ambil semua pelanggan dari DB ─────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($filter_node)   { $where[] = 'c.node_id = ?';       $params[] = $filter_node; }
if ($filter_router) { $where[] = 'mk.id = ?';            $params[] = $filter_router; }
if ($filter_status) { $where[] = 'c.status = ?';         $params[] = $filter_status; }

$rows = $pdo->prepare("
    SELECT
        c.id,
        c.customer_number,
        c.name,
        c.pppoe_username,
        c.status         AS service_status,
        c.remote_mgmt_port,
        n.name           AS node_name,
        p.name           AS package_name,
        mk.id            AS router_id,
        mk.host          AS router_host,
        mk.username      AS router_user,
        mk.password      AS router_pass,
        mk.port          AS router_api_port,
        mk.api_ssl       AS router_ssl
    FROM customers c
    LEFT JOIN nodes     n  ON n.id  = c.node_id
    LEFT JOIN packages  p  ON p.id  = c.package_id
    LEFT JOIN mikrotiks mk ON mk.id = p.mikrotik_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.customer_number ASC
");
$rows->execute($params);
$customers_db = $rows->fetchAll(PDO::FETCH_ASSOC);

// ── Kelompokkan per router untuk efisiensi koneksi API ────────────────────────
// 1 koneksi MikroTik → ambil semua sesi aktif sekaligus
$by_router = []; // router_host => { creds, cids: [cid => pppoe_username] }
foreach ($customers_db as $c) {
    if (!$c['router_host']) continue;
    $h = $c['router_host'];
    if (!isset($by_router[$h])) {
        $by_router[$h] = [
            'creds' => [
                'host'     => $c['router_host'],
                'username' => $c['router_user'],
                'password' => $c['router_pass'],
                'port'     => (int)$c['router_api_port'],
                'ssl'      => (bool)$c['router_ssl'],
            ],
            'map' => [], // pppoe_username => customer_id
        ];
    }
    $by_router[$h]['map'][$c['pppoe_username']] = $c['id'];
}

// ── Fetch sesi aktif dari setiap router ───────────────────────────────────────
// session_data: customer_id => { wan_ip, uptime }
$session_data = [];

foreach ($by_router as $host => $info) {
    $cr = $info['creds'];
    $conn = new Connection(
        $cr['host'], $cr['username'], $cr['password'],
        $cr['port']
    );
    if (!$conn->isConnected()) continue;

    $sessions = $conn->query('/ppp/active', 'print');
    foreach ($sessions as $s) {
        $uname = $s['name'] ?? '';
        $cid   = $info['map'][$uname] ?? null;
        if (!$cid) continue;
        $session_data[$cid] = [
            'wan_ip' => $s['address'] ?? null,
            'uptime' => $s['uptime']  ?? null,
        ];
    }
}

// ── Susun response ────────────────────────────────────────────────────────────
$result = [];
foreach ($customers_db as $c) {
    $cid      = (int)$c['id'];
    $live     = $session_data[$cid] ?? null;
    $is_online = $live !== null;

    $result[] = [
        // ── Identifier stabil ─────────────────────────────────────────
        'customer_number' => $c['customer_number'],
        'id'              => $cid,

        // ── Info pelanggan ────────────────────────────────────────────
        'name'            => $c['name'],
        'pppoe_username'  => $c['pppoe_username'],
        'node'            => $c['node_name'],
        'package'         => $c['package_name'],
        'service_status'  => $c['service_status'],   // status di DB (active/isolated/dll)
        'remote_mgmt_port'=> $c['remote_mgmt_port'] ? (int)$c['remote_mgmt_port'] : null,
        'router_host'     => $c['router_host'],

        // ── Status live (dari PPPoE session MikroTik) ─────────────────
        'is_online'       => $is_online,
        'wan_ip'          => $is_online ? ($live['wan_ip'] ?? null) : null,  // null jika offline
        'uptime'          => $is_online ? ($live['uptime'] ?? null) : null,
    ];
}

// ── Summary ───────────────────────────────────────────────────────────────────
$total   = count($result);
$online  = count(array_filter($result, fn($r) => $r['is_online']));
$offline = $total - $online;

$elapsed = round((microtime(true) - $t_start) * 1000);

echo json_encode([
    'success'    => true,
    'fetched_at' => date('Y-m-d\TH:i:sP'),
    'elapsed_ms' => $elapsed,
    'summary'    => [
        'total'   => $total,
        'online'  => $online,
        'offline' => $offline,
    ],
    'customers'  => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);