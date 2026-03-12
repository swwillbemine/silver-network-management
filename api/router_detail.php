<?php
// api/router_detail.php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
$selected_iface = trim($_GET['iface'] ?? '');

if (!$id) { echo json_encode(['error'=>'No ID']); exit; }

$stmt = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
$stmt->execute([$id]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$router) { echo json_encode(['error'=>'Not found']); exit; }

$client = get_mikrotik_client($router['host'], $router['username'], $router['password'], (int)$router['port'], (bool)$router['api_ssl']);

if (!$client) {
    echo json_encode(['offline' => true]);
    $pdo->prepare("UPDATE mikrotiks SET status='offline' WHERE id=?")->execute([$id]);
    exit;
}

$pdo->prepare("UPDATE mikrotiks SET status='online' WHERE id=?")->execute([$id]);

// ── System info ────────────────────────────────────────────────────────────
$res_id      = mikrotik_query($client, '/system/identity', 'print');
$res_rb      = mikrotik_query($client, '/system/routerboard', 'print');
$res_res     = mikrotik_query($client, '/system/resource', 'print');

$identity = $res_id[0]['name']    ?? $router['name'];
$model    = $res_rb[0]['model']   ?? 'Unknown';
$version  = $res_res[0]['version']    ?? '-';
$uptime   = $res_res[0]['uptime']     ?? '-';
$cpu_load = (int)($res_res[0]['cpu-load']    ?? 0);
$total_mem= (int)($res_res[0]['total-memory'] ?? 1);
$free_mem = (int)($res_res[0]['free-memory']  ?? 0);
$ram_pct  = $total_mem > 0 ? round(($total_mem - $free_mem) / $total_mem * 100) : 0;

// ── Interfaces ─────────────────────────────────────────────────────────────
$raw_ifaces = mikrotik_query($client, '/interface', 'print');
$interfaces = [];
foreach ($raw_ifaces as $iface) {
    // Skip dynamic PPPoE/L2TP sub-interfaces
    $name = $iface['name'] ?? '';
    if (preg_match('/^<.*>$/', $name)) continue;
    $interfaces[] = [
        'name'     => $name,
        'type'     => $iface['type'] ?? '-',
        'running'  => ($iface['running'] ?? '') === 'true',
        'rx_bytes' => (int)($iface['rx-byte'] ?? 0),
        'tx_bytes' => (int)($iface['tx-byte'] ?? 0),
    ];
}

// ── Selected interface stats ────────────────────────────────────────────────
$selected_data = null;
if ($selected_iface) {
    foreach ($interfaces as $iface) {
        if ($iface['name'] === $selected_iface) {
            $selected_data = $iface;
            break;
        }
    }
}
// Default to first interface with traffic
if (!$selected_data && count($interfaces)) {
    usort($interfaces, fn($a,$b) => $b['rx_bytes'] - $a['rx_bytes']);
    $selected_data = $interfaces[0];
}

// ── PPPoE active sessions ──────────────────────────────────────────────────
$raw_ppp = mikrotik_query($client, '/ppp/active', 'print');
$ppp_sessions = [];
foreach ($raw_ppp as $s) {
    if (($s['service'] ?? '') !== 'pppoe') continue;
    $ppp_sessions[] = [
        'name'      => $s['name'] ?? '-',
        'address'   => $s['address'] ?? '-',
        'uptime'    => $s['uptime'] ?? '-',
        'bytes-in'  => $s['bytes-in'] ?? 0,
        'bytes-out' => $s['bytes-out'] ?? 0,
    ];
}

echo json_encode([
    'identity'      => $identity,
    'model'         => $model,
    'version'       => $version,
    'uptime'        => $uptime,
    'cpu'           => $cpu_load,
    'ram'           => $ram_pct,
    'ppp_active'    => count($ppp_sessions),
    'interfaces'    => $interfaces,
    'selected_iface'=> $selected_data,
    'ppp_sessions'  => $ppp_sessions,
    'offline'       => false,
]);