<?php
// api/pppoe_status.php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true);
$router_id = (int)($input['router_id'] ?? $_GET['router_id'] ?? 0);
$usernames = $input['usernames'] ?? [];

if (!$router_id || empty($usernames)) { echo json_encode([]); exit; }

$rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
$rq->execute([$router_id]);
$router = $rq->fetch(PDO::FETCH_ASSOC);
if (!$router) { echo json_encode([]); exit; }

$client = @get_mikrotik_client(
    $router['host'], $router['username'], $router['password'],
    (int)$router['port'], (bool)$router['api_ssl']
);
if (!$client) { echo json_encode(['_error' => 'offline']); exit; }

$set = array_flip(array_map('strtolower', $usernames));

// ── 1. /ppp/active → status, IP, uptime ──────────────────────────────────
$result = [];
foreach (mikrotik_query($client, '/ppp/active', 'print') as $s) {
    $name = $s['name'] ?? '';
    if (!isset($set[strtolower($name)])) continue;
    $result[$name] = [
        'online'    => true,
        'ip'        => $s['address'] ?? '',
        'uptime'    => $s['uptime']  ?? '',
        'rx_rate'   => 0,   // bits/s download (router→client)
        'tx_rate'   => 0,   // bits/s upload (client→router)
        'rx_bytes'  => 0,
        'tx_bytes'  => 0,
        'max_limit' => '',
    ];
}

// ── 2. /queue/simple → rate (realtime bps) dan bytes ─────────────────────
// rate field format: "tx-bps/rx-bps"  (MikroTik: tx=upload, rx=download)
// max-limit format:  "tx-bps/rx-bps"
// Nama queue bisa: username, <username>, pppoe-username, <pppoe-username>
foreach (mikrotik_query($client, '/queue/simple', 'print') as $q) {
    $qname = $q['name'] ?? '';
    $clean = trim($qname, '<>');

    // Coba cocokkan ke username
    $matched = null;
    foreach ([$clean, ltrim($clean, 'pppoe-'), $qname] as $candidate) {
        if (isset($set[strtolower($candidate)])) {
            // Cari key asli (case dari /ppp/active)
            foreach (array_keys($result) as $k) {
                if (strtolower($k) === strtolower($candidate)) {
                    $matched = $k; break 2;
                }
            }
        }
    }
    if (!$matched) continue;

    // rate = "tx_bps/rx_bps" — realtime, diperbarui MikroTik tiap ~1 detik
    $rate  = $q['rate']      ?? '0/0';
    $bytes = $q['bytes']     ?? '0/0';
    $limit = $q['max-limit'] ?? '0/0';

    [$tx_rate,  $rx_rate]  = array_map('intval', explode('/', $rate,  2) + [0, 0]);
    [$tx_bytes, $rx_bytes] = array_map('intval', explode('/', $bytes, 2) + [0, 0]);

    // MikroTik queue: tx = arah dari router ke client = download client
    //                 rx = arah dari client ke router = upload client
    $result[$matched]['tx_rate']   = $tx_rate;   // download ke client
    $result[$matched]['rx_rate']   = $rx_rate;   // upload dari client
    $result[$matched]['rx_bytes']  = $rx_bytes;
    $result[$matched]['tx_bytes']  = $tx_bytes;
    $result[$matched]['max_limit'] = $limit;
}

// ── 3. Offline users ──────────────────────────────────────────────────────
foreach ($usernames as $u) {
    if (!isset($result[$u])) $result[$u] = ['online' => false];
}

echo json_encode($result);