<?php
// api/dashboard.php
error_reporting(0);
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
    @file_put_contents(
        dirname(__DIR__) . '/storage/logs/error.log',
        date('Y-m-d H:i:s') . ' [api/dashboard] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n",
        FILE_APPEND
    );
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;