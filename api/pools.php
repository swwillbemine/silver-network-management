<?php
// api/pools.php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$router_id = (int)($_GET['router_id'] ?? 0);
if (!$router_id) { echo json_encode([]); exit; }

$rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
$rq->execute([$router_id]);
$router = $rq->fetch(PDO::FETCH_ASSOC);
if (!$router) { echo json_encode([]); exit; }

$client = get_mikrotik_client(
    $router['host'], $router['username'], $router['password'],
    (int)$router['port'], (bool)$router['api_ssl']
);
if (!$client) { echo json_encode(['_error' => 'Cannot connect to '.$router['host']]); exit; }

$raw = get_ip_pools($client);
$pools = [];
foreach ($raw as $p) {
    $name = $p['name'] ?? ''; if (!$name) continue;
    $pools[] = [
        'name'   => $name,
        'ranges' => $p['ranges'] ?? '-',
        'next'   => $p['next-pool'] ?? '',
    ];
}
echo json_encode($pools);