<?php
// api/upload_image.php — handles logo & QRIS image uploads
require_once __DIR__ . '/../config/bootstrap.php';

use App\Services\UploadService;

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file    = $_FILES['image'];
$context = trim($_POST['context'] ?? 'misc');

$result = UploadService::handleImageUpload($file, $context);
if (!$result['success']) {
    http_response_code(400);
    echo json_encode(['error' => $result['error']]);
    exit;
}

echo json_encode([
    'success'  => true,
    'path'     => $result['path'],
    'filename' => $result['filename'] ?? ''
]);
exit;