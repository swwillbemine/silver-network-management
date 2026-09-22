<?php
// api/pools.php
require_once __DIR__ . '/../config/bootstrap.php';

use App\Repositories\RouterRepository;
use App\MikroTik\Connection;
use App\MikroTik\IPPoolManager;
use App\Auth\Middleware;

Middleware::requireLogin();

header('Content-Type: application/json');

$routerId = (int)($_GET['router_id'] ?? 0);
if (!$routerId) {
    echo json_encode([]);
    exit;
}

$routerRepo = new RouterRepository();
$router = $routerRepo->find($routerId);
if (!$router) {
    echo json_encode([]);
    exit;
}

$conn = Connection::fromRouter($router);
if (!$conn->isConnected()) {
    echo json_encode(['_error' => 'Cannot connect to ' . ($router['ip_address'] ?? ($router['host'] ?? $router['name']))]);
    exit;
}

$poolMgr = new IPPoolManager($conn);
$raw = $poolMgr->getPools();

$pools = [];
foreach ($raw as $p) {
    $name = $p['name'] ?? '';
    if (!$name) continue;
    $pools[] = [
        'name'   => $name,
        'ranges' => $p['ranges'] ?? '-',
        'next'   => $p['next-pool'] ?? '',
    ];
}

echo json_encode($pools);
exit;