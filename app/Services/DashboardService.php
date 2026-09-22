<?php

namespace App\Services;

use App\Repositories\CustomerRepository;
use App\Repositories\RouterRepository;
use App\Repositories\NodeRepository;
use App\Repositories\BillingRepository;
use App\MikroTik\Connection;
use App\Core\Database;
use PDO;

/**
 * Dashboard & Statistics Service
 */
class DashboardService
{
    private CustomerRepository $customerRepo;
    private RouterRepository $routerRepo;
    private NodeRepository $nodeRepo;
    private BillingRepository $billingRepo;
    private PDO $pdo;

    public function __construct(
        ?CustomerRepository $customerRepo = null,
        ?RouterRepository $routerRepo = null,
        ?NodeRepository $nodeRepo = null,
        ?BillingRepository $billingRepo = null,
        ?PDO $pdo = null
    ) {
        $this->customerRepo = $customerRepo ?: new CustomerRepository();
        $this->routerRepo   = $routerRepo ?: new RouterRepository();
        $this->nodeRepo     = $nodeRepo ?: new NodeRepository();
        $this->billingRepo  = $billingRepo ?: new BillingRepository();
        $this->pdo          = $pdo ?: Database::getConnection();
    }

    public function getOverview(): array
    {
        $totalCustomers = $this->customerRepo->count();
        $activeCustomers = $this->customerRepo->count(['status' => 'active']);
        $isolatedCustomers = $this->customerRepo->count(['status' => 'isolated']);

        $routers = $this->routerRepo->all();
        $onlineRouters  = 0;
        $offlineRouters = 0;
        $routerCards    = [];

        foreach ($routers as $r) {
            $conn = Connection::fromRouter($r);
            $isOnline = $conn->isConnected();
            if ($isOnline) {
                $onlineRouters++;
            } else {
                $offlineRouters++;
            }

            $routerCards[] = [
                'id'         => $r['id'],
                'name'       => $r['name'],
                'ip'         => $r['ip_address'],
                'is_online'  => $isOnline,
                'pop_name'   => $r['pop_name'] ?? '-',
                'cust_count' => (int)($r['cust_count'] ?? 0),
            ];
        }

        $currentPeriod = date('Y-m');
        $billingSummary = $this->billingRepo->getSummary($currentPeriod);

        return [
            'customers' => [
                'total'    => $totalCustomers,
                'active'   => $activeCustomers,
                'isolated' => $isolatedCustomers,
            ],
            'routers' => [
                'total'   => count($routers),
                'online'  => $onlineRouters,
                'offline' => $offlineRouters,
                'list'    => $routerCards,
            ],
            'billing' => [
                'period'       => $currentPeriod,
                'total_amount' => (float)($billingSummary['total_amount'] ?? 0),
                'paid_amount'  => (float)($billingSummary['paid_amount'] ?? 0),
                'unpaid_count' => (int)($billingSummary['unpaid_count'] ?? 0),
            ],
        ];
    }

    public function getLiveDashboardData(): array
    {
        $cntCustomers = $this->customerRepo->count();
        $cntPops      = (int)$this->pdo->query("SELECT COUNT(*) FROM pops")->fetchColumn();
        $cntHotspots  = (int)$this->pdo->query("SELECT COUNT(*) FROM hotspots")->fetchColumn();
        $cntNodes     = (int)$this->pdo->query("SELECT COUNT(*) FROM nodes")->fetchColumn();

        $currentMonth = date('Y-m');
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM billings WHERE status = 'paid' AND period = ?");
        $stmt->execute([$currentMonth]);
        $incomeThisMonth = (float)$stmt->fetchColumn();

        $routersDb = $this->routerRepo->all();

        $globalPppActive    = 0;
        $globalTrafficRx    = 0;
        $globalTrafficTx    = 0;
        $totalRoutersOnline = 0;
        $trafficSources      = [];
        $routersData         = [];

        foreach ($routersDb as $router) {
            $r = [
                'id'             => $router['id'],
                'name'           => $router['name'],
                'host'           => $router['ip_address'] ?? ($router['host'] ?? ''),
                'status'         => 'offline',
                'identity'       => '-',
                'model'          => '-',
                'version'        => '-',
                'uptime'         => '-',
                'cpu'            => 0,
                'ram'            => 0,
                'ppp_active'     => 0,
                'interfaces'     => [],
                'traffic_rx'     => 0,
                'traffic_tx'     => 0,
                'traffic_rx_fmt' => '0 B',
                'traffic_tx_fmt' => '0 B',
            ];

            try {
                $conn = Connection::fromRouter($router);
                if ($conn->isConnected()) {
                    $totalRoutersOnline++;
                    $r['status'] = 'online';

                    $res = $conn->query('/system/resource', 'print');
                    if (!empty($res[0])) {
                        $sr = $res[0];
                        $r['uptime']  = $sr['uptime'] ?? '-';
                        $r['version'] = $sr['version'] ?? '-';
                        $r['cpu']     = (int)($sr['cpu-load'] ?? 0);
                        $totalMem     = max(1, (int)($sr['total-memory'] ?? 1));
                        $freeMem      = (int)($sr['free-memory'] ?? 0);
                        $r['ram']     = round((($totalMem - $freeMem) / $totalMem) * 100);
                    }

                    $rb = $conn->query('/system/routerboard', 'print');
                    if (!empty($rb[0])) {
                        $r['model'] = $rb[0]['model'] ?? ($rb[0]['board-name'] ?? '-');
                    }

                    $ident = $conn->query('/system/identity', 'print');
                    if (!empty($ident[0])) {
                        $r['identity'] = $ident[0]['name'] ?? '-';
                    }

                    $activePpp = $conn->query('/ppp/active', 'print');
                    $r['ppp_active'] = count($activePpp);
                    $globalPppActive += $r['ppp_active'];

                    $allIfaces = $conn->query('/interface', 'print');
                    $trafficRx = 0; $trafficTx = 0;
                    $uplink = null;

                    foreach ($allIfaces as $iface) {
                        if (($iface['disabled'] ?? 'false') === 'true') continue;
                        $rxBytes = (int)($iface['rx-byte'] ?? 0);
                        $txBytes = (int)($iface['tx-byte'] ?? 0);
                        $ifName  = $iface['name'] ?? '';

                        $isUplink = (!empty($iface['comment']) && stripos($iface['comment'], 'uplink') !== false)
                                 || stripos($ifName, 'ether1') !== false
                                 || stripos($ifName, 'sfp') !== false;

                        if ($isUplink && $uplink === null) {
                            $uplink = ['name' => $ifName, 'rx' => $rxBytes, 'tx' => $txBytes];
                        }

                        $r['interfaces'][] = [
                            'name'    => $ifName,
                            'type'    => $iface['type'] ?? '',
                            'running' => ($iface['running'] ?? 'false') === 'true',
                            'comment' => $iface['comment'] ?? '',
                            'rx'      => \App\Helpers\Utility::formatBytes($rxBytes),
                            'tx'      => \App\Helpers\Utility::formatBytes($txBytes),
                            'rx_fmt'  => \App\Helpers\Utility::formatBytes($rxBytes),
                            'tx_fmt'  => \App\Helpers\Utility::formatBytes($txBytes),
                        ];
                    }

                    if ($uplink) {
                        $trafficRx = $uplink['rx'];
                        $trafficTx = $uplink['tx'];
                        $globalTrafficRx += $trafficRx;
                        $globalTrafficTx += $trafficTx;
                        $trafficSources[] = $router['name'] . ' (' . $uplink['name'] . ')';
                    }

                    $r['traffic_rx']     = $trafficRx;
                    $r['traffic_tx']     = $trafficTx;
                    $r['traffic_rx_fmt'] = \App\Helpers\Utility::formatBytes($trafficRx);
                    $r['traffic_tx_fmt'] = \App\Helpers\Utility::formatBytes($trafficTx);
                    $r['interfaces']     = array_slice($r['interfaces'], 0, 6);
                }
            } catch (\Throwable $re) {
                $r['status'] = 'offline';
            }

            $routersData[] = $r;
        }

        // --- Customer Growth for Chart ---
        $currentYear = (int)date('Y');
        $customerGrowth = [];
        $growthLabels = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthStr = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
            $dateLimit = "{$currentYear}-{$monthStr}-31 23:59:59";
            
            // Count total active customers up to the end of that month
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM customers WHERE status = 'active' AND (installation_date <= ? OR installation_date IS NULL)");
            $stmt->execute([$dateLimit]);
            $count = (int)$stmt->fetchColumn();
            
            $customerGrowth[] = $count;
            $monthName = date('M', mktime(0, 0, 0, $m, 10)); // Jan, Feb, etc
            $growthLabels[] = $monthName;
        }

        return [
            'global_stats' => [
                'total_customers'  => (int)$cntCustomers,
                'total_online_ppp' => $globalPppActive,
                'total_income'     => 'Rp ' . number_format($incomeThisMonth, 0, ',', '.'),
                'traffic_rx'       => \App\Helpers\Utility::formatBytes($globalTrafficRx),
                'traffic_tx'       => \App\Helpers\Utility::formatBytes($globalTrafficTx),
                'traffic_sources'  => $trafficSources,
            ],
            'infra_stats' => [
                'pops'             => (int)$cntPops,
                'mikrotiks'        => count($routersDb),
                'online_mikrotiks' => $totalRoutersOnline,
                'hotspots'         => (int)$cntHotspots,
                'nodes'            => (int)$cntNodes,
            ],
            'chart_data' => [
                'customer_growth' => $customerGrowth,
                'customer_labels' => $growthLabels,
            ],
            'routers_detail' => $routersData,
            'updated_at'     => date('H:i:s'),
        ];
    }
}
