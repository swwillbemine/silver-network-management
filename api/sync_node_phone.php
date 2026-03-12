<?php
// api/sync_node_phone.php — update owner_contact di node saat phone pelanggan diubah
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');
$input   = json_decode(file_get_contents('php://input'), true);
$node_id = (int)($input['node_id'] ?? 0);
$phone   = trim($input['phone'] ?? '');

if (!$node_id || !$phone) { echo json_encode(['ok'=>false]); exit; }

$pdo->prepare("UPDATE nodes SET owner_contact=? WHERE id=?")->execute([$phone, $node_id]);
echo json_encode(['ok' => true]);