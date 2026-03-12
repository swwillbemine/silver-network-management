<?php
// api/dashboard.php
header('Content-Type: application/json');
error_reporting(0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../mikrotik/connection.php';

$global_ppp_active    = 0;
$global_traffic_rx    = 0;
$global_traffic_tx    = 0;
$total_routers_online = 0;
$traffic_sources      = [];
$routers_data         = [];

function formatBytes($bytes) {
    if ($bytes >= 1099511627776) return round($bytes / 1099511627776, 2) . ' TB';
    if ($bytes >= 1073741824)    return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)       return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)          return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

try {
    $cnt_customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $cnt_pops      = $pdo->query("SELECT COUNT(*) FROM pops")->fetchColumn();
    $cnt_hotspots  = $pdo->query("SELECT COUNT(*) FROM hotspots")->fetchColumn();
    $cnt_nodes     = $pdo->query("SELECT COUNT(*) FROM nodes")->fetchColumn();

    $current_month = date('Y-m');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM billings WHERE status='paid' AND period=?");
    $stmt->execute([$current_month]);
    $income_this_month = $stmt->fetchColumn();

    $routers_db = $pdo->query("SELECT * FROM mikrotiks")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($routers_db as $router) {
        $r = [
            'id'         => $router['id'],
            'name'       => $router['name'],
            'host'       => $router['host'],
            'status'     => 'offline',
            'identity'   => '-',
            'model'      => '-',
            'version'    => '-',
            'uptime'     => '-',
            'cpu'        => 0,
            'ram'        => 0,
            'ppp_active' => 0,
            'interfaces' => [],
            'traffic_rx' => 0,
            'traffic_tx' => 0,
            'traffic_rx_fmt' => '0 B',
            'traffic_tx_fmt' => '0 B',
        ];

        $client = get_mikrotik_client(
            $router['host'], $router['username'],
            $router['password'], $router['port']
        );

        if ($client) {
            $r['status'] = 'online';
            $total_routers_online++;

            // System Resource
            $res = mikrotik_query($client, '/system/resource', 'print')[0] ?? [];
            if ($res) {
                $r['version'] = $res['version'] ?? '-';
                $r['model']   = $res['board-name'] ?? '-';
                $r['uptime']  = $res['uptime'] ?? '-';
                $r['cpu']     = (int)($res['cpu-load'] ?? 0);
                $total_mem    = $res['total-memory'] ?? 1;
                $free_mem     = $res['free-memory'] ?? 0;
                $r['ram']     = round((($total_mem - $free_mem) / $total_mem) * 100);
            }

            // System Identity (device name configured on MikroTik)
            $identity_res = mikrotik_query($client, '/system/identity', 'print');
            $r['identity'] = $identity_res[0]['name'] ?? $router['name'];

            // Routerboard info (hardware model detail)
            $rb = mikrotik_query($client, '/system/routerboard', 'print')[0] ?? [];
            if (!empty($rb['model'])) {
                $r['model'] = $rb['model'];
            }

            // PPP Active
            $ppp = mikrotik_query($client, '/ppp/active', 'print', ['service' => 'pppoe']);
            $r['ppp_active'] = count($ppp);
            $global_ppp_active += $r['ppp_active'];

            // === FIX: Traffic from ALL non-dynamic physical interfaces ===
            // We aggregate RX+TX from all ether/sfp interfaces (uplink candidates)
            $all_ifaces = mikrotik_query($client, '/interface', 'print');

            $router_rx = 0;
            $router_tx = 0;
            $uplink_candidates = [];

            foreach ($all_ifaces as $iface) {
                $type    = $iface['type'] ?? '';
                $name    = $iface['name'] ?? '';
                $dynamic = $iface['dynamic'] ?? 'false';
                $running = $iface['running'] ?? 'false';

                // Skip dynamic (PPPoE client sessions), loopback, bridge sub-interfaces
                if ($dynamic === 'true') continue;
                if (in_array($type, ['pppoe-out', 'pppoe-in', 'pptp-out', 'pptp-in', 'l2tp-out', 'l2tp-in'])) continue;

                $rx_byte = (int)($iface['rx-byte'] ?? 0);
                $tx_byte = (int)($iface['tx-byte'] ?? 0);

                // Physical interface for traffic summary: ether, sfp, wlan
                if (in_array($type, ['ether', 'wlan', 'sfp', 'sfp-sfpplus', '']) || preg_match('/^(ether|sfp|wlan)/', $name)) {
                    $uplink_candidates[] = [
                        'name' => $name,
                        'rx'   => $rx_byte,
                        'tx'   => $tx_byte,
                        'type' => $type,
                    ];
                }

                // For display: show all non-dynamic interfaces
                $r['interfaces'][] = [
                    'name'   => $name,
                    'type'   => $type,
                    'rx_fmt' => formatBytes($rx_byte),
                    'tx_fmt' => formatBytes($tx_byte),
                    'running'=> $running === 'true',
                ];
            }

            // Pick the single interface with the highest RX as the "uplink" for global aggregation
            // This avoids double-counting bridge members
            if (!empty($uplink_candidates)) {
                usort($uplink_candidates, fn($a, $b) => $b['rx'] <=> $a['rx']);
                $uplink = $uplink_candidates[0];
                $router_rx = $uplink['rx'];
                $router_tx = $uplink['tx'];
                $global_traffic_rx += $router_rx;
                $global_traffic_tx += $router_tx;
                $traffic_sources[] = $router['name'] . ' (' . $uplink['name'] . ')';
            }

            $r['traffic_rx']     = $router_rx;
            $r['traffic_tx']     = $router_tx;
            $r['traffic_rx_fmt'] = formatBytes($router_rx);
            $r['traffic_tx_fmt'] = formatBytes($router_tx);

            // Limit interface list for card display
            $r['interfaces'] = array_slice($r['interfaces'], 0, 6);
        }

        $routers_data[] = $r;
    }

    echo json_encode([
        'global_stats' => [
            'total_customers'  => (int)$cnt_customers,
            'total_online_ppp' => $global_ppp_active,
            'total_income'     => 'Rp ' . number_format($income_this_month, 0, ',', '.'),
            'traffic_rx'       => formatBytes($global_traffic_rx),
            'traffic_tx'       => formatBytes($global_traffic_tx),
            'traffic_sources'  => $traffic_sources,
        ],
        'infra_stats' => [
            'pops'            => (int)$cnt_pops,
            'mikrotiks'       => count($routers_db),
            'online_mikrotiks'=> $total_routers_online,
            'hotspots'        => (int)$cnt_hotspots,
            'nodes'           => (int)$cnt_nodes,
        ],
        'routers_detail' => $routers_data,
        'updated_at'     => date('H:i:s'),
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}