<?php
// api/proxy_token.php — Generate proxy token untuk akses manual port
require_once __DIR__ . '/../config/bootstrap.php';

use App\Auth\Middleware;
use App\MikroTik\Connection;

Middleware::requireLogin();

header('Content-Type: application/json');

$cid  = (int)($_GET['cid']  ?? 0);
$port = (int)($_GET['port'] ?? 0);

if (!$cid || $port < 1 || $port > 65535) {
    echo json_encode(['error' => 'Parameter tidak valid']);
    exit;
}

// Validasi port bukan port berbahaya
$blocked = [22, 23, 25, 110, 143, 3306, 5432, 6379, 27017];
if (in_array($port, $blocked, true)) {
    echo json_encode(['error' => 'Port tidak diizinkan']);
    exit;
}

// Ambil WAN IP live dari MikroTik
$stmt = $pdo->prepare("
    SELECT c.pppoe_username, mk.host, mk.username, mk.password, mk.port AS api_port, mk.api_ssl
    FROM customers c
    LEFT JOIN packages p  ON p.id  = c.package_id
    LEFT JOIN mikrotiks mk ON mk.id = p.mikrotik_id
    WHERE c.id = ?");
$stmt->execute([$cid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || !$row['host']) {
    echo json_encode(['error' => 'Pelanggan tidak ditemukan']);
    exit;
}

$conn = new Connection(
    $row['host'],
    $row['username'],
    $row['password'],
    (int)$row['api_port']
);

$wan_ip = null;
if ($conn->isConnected()) {
    foreach ($conn->query('/ppp/active', 'print') as $s) {
        if (($s['name'] ?? '') === $row['pppoe_username']) {
            $wan_ip = $s['address'] ?? null;
            break;
        }
    }
}

if (!$wan_ip) {
    echo json_encode(['error' => 'Sesi PPPoE tidak aktif']);
    exit;
}

// Generate token
function getProxySecret(): string {
    global $pdo;
    static $s = null;
    if ($s) return $s;
    try {
        $r = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='proxy_secret' LIMIT 1")->fetch();
        if ($r && strlen($r['setting_value'] ?? '') >= 32) {
            $s = $r['setting_value'];
            return $s;
        }
    } catch (\Exception $e) {}
    $g = bin2hex(random_bytes(32));
    try {
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('proxy_secret',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$g]);
    } catch (\Exception $e) {}
    $s = $g;
    return $s;
}

$bucket = floor(time() / 3600);
$token  = hash_hmac('sha256', "{$cid}|{$wan_ip}|{$port}|{$bucket}", getProxySecret());
$url    = BASE_URL . '/router-proxy/' . $cid . '/' . $token . '/';

echo json_encode(['url' => $url, 'ip' => $wan_ip, 'port' => $port]);