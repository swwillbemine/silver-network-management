<?php
// api/ppp_profiles.php — Returns PPP profile names for a router
require_once __DIR__ . '/../config/bootstrap.php';

use App\Repositories\RouterRepository;
use App\MikroTik\Connection;
use App\MikroTik\PPPManager;
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
    echo json_encode(['_error' => 'Cannot connect']);
    exit;
}

$ppp = new PPPManager($conn);
$raw = $ppp->getProfiles();
$profiles = [];
foreach ($raw as $p) {
    $name = $p['name'] ?? '';
    if (!$name || $name === 'default') continue;
    $profiles[] = [
        'id'         => $p['.id']          ?? '',
        'name'       => $name,
        'rate_limit' => $p['rate-limit']   ?? '',
        'queue_type' => $p['queue-type']   ?? '',
    ];
}

echo json_encode($profiles);
exit;