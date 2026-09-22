<?php
// api/queues.php
// Returns queue names (tree + simple) for a given router, for dropdown use
require_once __DIR__ . '/../config/bootstrap.php';

use App\Repositories\RouterRepository;
use App\MikroTik\Connection;
use App\MikroTik\QueueManager;
use App\Auth\Middleware;

Middleware::requireLogin();

header('Content-Type: application/json');

$routerId = (int)($_GET['router_id'] ?? 0);
if (!$routerId) {
    echo json_encode(['error' => 'No router_id']);
    exit;
}

$routerRepo = new RouterRepository();
$router = $routerRepo->find($routerId);
if (!$router) {
    echo json_encode(['error' => 'Router not found']);
    exit;
}

$conn = Connection::fromRouter($router);
if (!$conn->isConnected()) {
    echo json_encode(['error' => 'Cannot connect to router']);
    exit;
}

$queueMgr = new QueueManager($conn);
$queues = $queueMgr->getAllQueuesFormatted();

echo json_encode(['queues' => $queues]);
exit;