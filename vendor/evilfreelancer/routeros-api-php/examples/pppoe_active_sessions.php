<?php

/**
 * Monitor Active Sessions (PPPoE + DHCP)
 *
 * how to fetch active PPPoE sessions,
 * DHCP leases and monitor user login/logout activity from MikroTik RouterOS.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Query;

// Connect to RouterOS
$client = new Client([
    'host' => '192.168.10.1', // router IP address
    'user' => 'test', // mikrotik username
    'pass' => 'test', // mikrotik password
    'port' => 8728, //  API port
]);

// ============================================================
// PPPoE Active Sessions
// ============================================================
$pppoeQuery    = new Query('/ppp/active/print');
$pppoeSessions = $client->query($pppoeQuery)->read();

echo "Active PPPoE Sessions:\n";
echo str_repeat('-', 60) . "\n";

if (empty($pppoeSessions)) {
    echo "No active PPPoE sessions.\n";
} else {
    foreach ($pppoeSessions as $session) {
        echo sprintf(
            "User: %-20s IP: %-15s Uptime: %s\n",
            $session['name']    ?? 'N/A',
            $session['address'] ?? 'N/A',
            $session['uptime']  ?? 'N/A'
        );
    }
}

echo "\nTotal PPPoE sessions: " . count($pppoeSessions) . "\n";

// ============================================================
// DHCP Active Leases
// ============================================================
$dhcpQuery  = new Query('/ip/dhcp-server/lease/print');
$dhcpLeases = $client->query($dhcpQuery)->read();

echo "\nActive DHCP Leases:\n";
echo str_repeat('-', 60) . "\n";

if (empty($dhcpLeases)) {
    echo "No active DHCP leases.\n";
} else {
    foreach ($dhcpLeases as $lease) {
        echo sprintf(
            "Host: %-20s IP: %-15s MAC: %-18s Status: %s\n",
            $lease['host-name']   ?? 'N/A',
            $lease['address']     ?? 'N/A',
            $lease['mac-address'] ?? 'N/A',
            $lease['status']      ?? 'N/A'
        );
    }
}

echo "\nTotal DHCP leases: " . count($dhcpLeases) . "\n";

// ============================================================
// Recent PPP Logs
// ============================================================
$logQuery = (new Query('/log/print'))
    ->where('topics', 'ppp');

$logs = $client->query($logQuery)->read();

echo "\nRecent PPPoE Logs:\n";
echo str_repeat('-', 60) . "\n";

if (empty($logs)) {
    echo "No PPPoE logs found.\n";
} else {
    foreach (array_slice($logs, -10) as $log) {
        echo sprintf(
            "[%s] %s\n",
            $log['time']    ?? 'N/A',
            $log['message'] ?? 'N/A'
        );
    }
}