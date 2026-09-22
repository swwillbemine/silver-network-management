<?php
// api/sync_node_phone.php — update owner_contact di node saat phone pelanggan diubah
require_once __DIR__ . '/../config/bootstrap.php';

use App\Auth\Middleware;
use App\Services\NodeService;

Middleware::requireLogin();

header('Content-Type: application/json');
$input   = json_decode(file_get_contents('php://input'), true);
$nodeId  = (int)($input['node_id'] ?? 0);
$phone   = trim($input['phone'] ?? '');

if (!$nodeId || !$phone) {
    echo json_encode(['ok' => false]);
    exit;
}

$nodeService = new NodeService();
$ok = $nodeService->syncOwnerContact($nodeId, $phone);

echo json_encode(['ok' => $ok]);
exit;