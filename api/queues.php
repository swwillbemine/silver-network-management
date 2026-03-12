<?php
// api/queues.php
// Returns queue names (tree + simple) for a given router, for dropdown use
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$router_id = (int)($_GET['router_id'] ?? 0);
if (!$router_id) { echo json_encode(['error' => 'No router_id']); exit; }

$rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
$rq->execute([$router_id]);
$router = $rq->fetch(PDO::FETCH_ASSOC);
if (!$router) { echo json_encode(['error' => 'Router not found']); exit; }

$client = get_mikrotik_client(
    $router['host'], $router['username'], $router['password'],
    (int)$router['port'], (bool)$router['api_ssl']
);
if (!$client) { echo json_encode(['error' => 'Cannot connect to router']); exit; }

$queues = [];

// /queue/tree
$tree = mikrotik_query($client, '/queue/tree', 'print');
foreach ($tree as $q) {
    $name = $q['name'] ?? '';
    if (!$name) continue;
    $queues[] = [
        'name'   => $name,
        'type'   => 'tree',
        'parent' => $q['parent'] ?? '',
        'rate'   => $q['max-limit'] ?? '-',
    ];
}

// /queue/simple
$simple = mikrotik_query($client, '/queue/simple', 'print');
foreach ($simple as $q) {
    $name = $q['name'] ?? '';
    if (!$name) continue;
    $queues[] = [
        'name'   => $name,
        'type'   => 'simple',
        'parent' => $q['parent'] ?? '',
        'rate'   => $q['max-limit'] ?? '-',
    ];
}

echo json_encode(['queues' => $queues]);