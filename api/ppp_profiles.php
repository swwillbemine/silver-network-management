<?php
// api/ppp_profiles.php — Returns PPP profile names for a router
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
    $router['host'], $router['username'], $router['password'], (int)$router['port']
);
if (!$client) { echo json_encode(['_error' => 'Cannot connect']); exit; }

$raw = mikrotik_query($client, '/ppp/profile', 'print', []);
$profiles = [];
foreach ($raw as $p) {
    $name = $p['name'] ?? '';
    if (!$name || $name === 'default') continue; // skip built-in default
    $profiles[] = [
        'id'         => $p['.id']          ?? '',
        'name'       => $name,
        'rate_limit' => $p['rate-limit']   ?? '',
        'queue_type' => $p['queue-type']   ?? '',
    ];
}
echo json_encode($profiles);