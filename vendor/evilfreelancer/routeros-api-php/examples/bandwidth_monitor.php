<?php

/**
 * Bandwidth Monitor
 *
 * how to monitor bandwidth usage
 * across all interfaces of MikroTik RouterOS.
 * Useful for ISPs to track traffic per interface.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Query;

// Connect to RouterOS
$client = new Client([
    'host' => '192.168.10.1', // Change to your RouterOS IP
    'user' => 'test', // Change to your RouterOS username
    'pass' => 'test', // Change to your RouterOS password
    'port' => 8728, // Default API port, change if needed
]);

// ============================================================
// Get all interfaces
// ============================================================
$interfaceQuery = new Query('/interface/print');
$interfaces     = $client->query($interfaceQuery)->read();

echo "Interface Bandwidth Statistics:\n";
echo str_repeat('-', 70) . "\n";
echo sprintf(
    "%-20s %-10s %-15s %-15s %s\n",
    'Interface',
    'Type',
    'TX (bytes)',
    'RX (bytes)',
    'Status'
);
echo str_repeat('-', 70) . "\n";

foreach ($interfaces as $interface) {
    echo sprintf(
        "%-20s %-10s %-15s %-15s %s\n",
        $interface['name']           ?? 'N/A',
        $interface['type']           ?? 'N/A',
        number_format($interface['tx-byte'] ?? 0),
        number_format($interface['rx-byte'] ?? 0),
        isset($interface['disabled']) && $interface['disabled'] === 'true'
            ? 'Disabled'
            : 'Active'
    );
}

// ============================================================
// Monitor specific interface live traffic
// ============================================================
echo "\nLive Traffic Monitor (ether1):\n";
echo str_repeat('-', 70) . "\n";

$monitorQuery = (new Query('/interface/monitor-traffic'))
    ->equal('interface', 'ether1')
    ->equal('once');

$traffic = $client->query($monitorQuery)->read();

if (!empty($traffic)) {
    $t = $traffic[0];
    echo sprintf("TX Bits/s  : %s bps\n", number_format($t['tx-bits-per-second'] ?? 0));
    echo sprintf("RX Bits/s  : %s bps\n", number_format($t['rx-bits-per-second'] ?? 0));
    echo sprintf("TX Packets : %s pps\n", number_format($t['tx-packets-per-second'] ?? 0));
    echo sprintf("RX Packets : %s pps\n", number_format($t['rx-packets-per-second'] ?? 0));
}