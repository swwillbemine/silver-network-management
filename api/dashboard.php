<?php
// api/dashboard.php
require_once __DIR__ . '/../config/bootstrap.php';

use App\Services\DashboardService;
use App\Auth\Middleware;

Middleware::requireLogin();

header('Content-Type: application/json');

try {
    $service = new DashboardService();
    $data = $service->getLiveDashboardData();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;