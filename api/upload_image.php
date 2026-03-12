<?php
// api/upload_image.php — handles logo & QRIS image uploads
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file    = $_FILES['image'];
$context = trim($_POST['context'] ?? 'misc'); // isp_logo | reseller_logo | qris

// Validate
$allowed_mime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed_mime)) {
    echo json_encode(['error' => 'Tipe file tidak diizinkan. Gunakan PNG, JPG, GIF, WEBP, atau SVG.']);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['error' => 'Ukuran file maksimal 2MB.']);
    exit;
}

// Build destination
$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
$filename = $context . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
$dest     = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['error' => 'Gagal menyimpan file.']);
    exit;
}

// Build public URL relative to web root
// Assumes web root = one directory above /api/
$rel_path = 'uploads/' . $filename;

echo json_encode(['success' => true, 'path' => $rel_path]);