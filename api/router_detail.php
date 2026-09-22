<?php
// api/router_detail.php
require_once __DIR__ . '/../config/bootstrap.php';

use App\Repositories\RouterRepository;
use App\MikroTik\Connection;
use App\Auth\Middleware;

Middleware::requireLogin();

header('Content-Type: application/json');

$id            = (int)($_GET['id'] ?? 0);
$selectedIface = trim($_GET['iface'] ?? '');

if (!$id) {
    echo json_encode(['error' => 'No ID']);
    exit;
}

$routerRepo = new RouterRepository();
$router = $routerRepo->find($id);
if (!$router) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

$conn = Connection::fromRouter($router);
if (!$conn->isConnected()) {
    $routerRepo->updateStatus($id, 'offline');
    echo json_encode(['offline' => true]);
    exit;
}

$routerRepo->updateStatus($id, 'online');

// ── System info ────────────────────────────────────────────────────────────
$resId  = $conn->query('/system/identity', 'print');
$resRb  = $conn->query('/system/routerboard', 'print');
$resRes = $conn->query('/system/resource', 'print');

$identity = $resId[0]['name']     ?? $router['name'];
$model    = $resRb[0]['model']    ?? 'Unknown';
$version  = $resRes[0]['version'] ?? '-';
$uptime   = $resRes[0]['uptime']  ?? '-';
$cpuLoad  = (int)($resRes[0]['cpu-load']     ?? 0);
$totalMem = (int)($resRes[0]['total-memory'] ?? 1);
$freeMem  = (int)($resRes[0]['free-memory']  ?? 0);
$ramPct   = $totalMem > 0 ? round(($totalMem - $freeMem) / $totalMem * 100) : 0;

// ── Interfaces ─────────────────────────────────────────────────────────────
$rawIfaces  = $conn->query('/interface', 'print');
$interfaces = [];
foreach ($rawIfaces as $iface) {
    // Skip dynamic PPPoE/L2TP sub-interfaces
    $name = $iface['name'] ?? '';
    if (preg_match('/^<.*>$/', $name)) {
        continue;
    }
    $interfaces[] = [
        'name'     => $name,
        'type'     => $iface['type'] ?? '-',
        'running'  => ($iface['running'] ?? '') === 'true',
        'rx_bytes' => (int)($iface['rx-byte'] ?? 0),
        'tx_bytes' => (int)($iface['tx-byte'] ?? 0),
    ];
}

// ── Selected interface stats ────────────────────────────────────────────────
$selectedData = null;
if ($selectedIface) {
    foreach ($interfaces as $iface) {
        if ($iface['name'] === $selectedIface) {
            $selectedData = $iface;
            break;
        }
    }
}
// Default to first interface with traffic
if (!$selectedData && count($interfaces)) {
    usort($interfaces, fn($a, $b) => $b['rx_bytes'] - $a['rx_bytes']);
    $selectedData = $interfaces[0];
}

// ── PPPoE active sessions ──────────────────────────────────────────────────
$rawPpp = $conn->query('/ppp/active', 'print');
$pppSessions = [];
foreach ($rawPpp as $s) {
    if (($s['service'] ?? '') !== 'pppoe') {
        continue;
    }
    $pppSessions[] = [
        'name'      => $s['name'] ?? '-',
        'address'   => $s['address'] ?? '-',
        'uptime'    => $s['uptime'] ?? '-',
        'bytes-in'  => $s['bytes-in'] ?? 0,
        'bytes-out' => $s['bytes-out'] ?? 0,
    ];
}

echo json_encode([
    'identity'       => $identity,
    'model'          => $model,
    'version'        => $version,
    'uptime'         => $uptime,
    'cpu'            => $cpuLoad,
    'ram'            => $ramPct,
    'ppp_active'     => count($pppSessions),
    'interfaces'     => $interfaces,
    'selected_iface' => $selectedData,
    'ppp_sessions'   => $pppSessions,
    'offline'        => false,
]);