<?php

/**
 * NOC Multi-Router Monitor & Manager
 * Author: ZILL E ALI
 *  how to monitor and manage
 * multiple MikroTik routers simultaneously - NOC level.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Query;

// ============================================================
// Router List — Add as many as needed
// ============================================================
$routers = [
    [
        'name' => 'Router-01 (HQ)',
        'host' => '192.168.10.1', // Change to your RouterOS IP
        'user' => 'admin', // username with read-only access for better security
        'pass' => '',// Change to your RouterOS credentials
        'port' => 8728, // Default API port, change if needed
    ],
    [
        'name' => 'Router-02 (Branch)',
        'host' => '192.168.10.1',// Change to your RouterOS IP
        'user' => 'admin',// username with read-only access for better security
        'pass' => '',// Change to your RouterOS credentials
        'port' => 8728, // Default API port, change if needed
    ],
    // Add more routers here...
];

// ============================================================
// Helper Functions
// ============================================================
function connectRouter(array $router): ?Client
{
    try {
        return new Client([
            'host'    => $router['host'],
            'user'    => $router['user'],
            'pass'    => $router['pass'],
            'port'    => $router['port'],
            'timeout' => 5,
        ]);
    } catch (\Exception $e) {
        return null;
    }
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i     = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// ============================================================
// Monitor All Routers
// ============================================================
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║           NOC Multi-Router Monitor — NexaLink            ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

foreach ($routers as $router) {
    echo "┌─ {$router['name']} ({$router['host']})\n";

    $client = connectRouter($router);

    if ($client === null) {
        echo "│  ❌ OFFLINE — Cannot connect\n";
        echo "└" . str_repeat('─', 58) . "\n\n";
        continue;
    }

    echo "│  ✅ ONLINE\n";

    // System Resource
    $resource = $client->query(new Query('/system/resource/print'))->read();
    if (!empty($resource)) {
        $r = $resource[0];
        echo "│  CPU      : " . ($r['cpu-load'] ?? 'N/A') . "%\n";
        echo "│  RAM      : " . formatBytes((int)($r['total-memory'] ?? 0) - (int)($r['free-memory'] ?? 0))
            . " / " . formatBytes((int)($r['total-memory'] ?? 0)) . "\n";
        echo "│  Uptime   : " . ($r['uptime'] ?? 'N/A') . "\n";
        echo "│  Version  : " . ($r['version'] ?? 'N/A') . "\n";
        echo "│  Board    : " . ($r['board-name'] ?? 'N/A') . "\n";
    }

    // Interface Status
    $interfaces = $client->query(new Query('/interface/print'))->read();
    $active     = array_filter($interfaces, fn($i) => ($i['disabled'] ?? 'true') === 'false' && ($i['running'] ?? 'false') === 'true');
    echo "│  Interfaces: " . count($active) . " active / " . count($interfaces) . " total\n";

    // Active Connections
    $pppoe = $client->query(new Query('/ppp/active/print'))->read();
    $dhcp  = $client->query(new Query('/ip/dhcp-server/lease/print'))->read();
    echo "│  PPPoE    : " . count($pppoe) . " active sessions\n";
    echo "│  DHCP     : " . count($dhcp) . " leases\n";

    echo "└" . str_repeat('─', 58) . "\n\n";
}