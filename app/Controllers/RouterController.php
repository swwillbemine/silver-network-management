<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Repositories\RouterRepository;
use App\Repositories\PopRepository;
use App\Repositories\NodeRepository;
use App\Services\RouterService;
use PDO;

class RouterController
{
    private PDO $pdo;
    private RouterRepository $routerRepo;
    private PopRepository $popRepo;
    private NodeRepository $nodeRepo;
    private RouterService $routerService;

    public function __construct(
        ?RouterRepository $routerRepo = null,
        ?PopRepository $popRepo = null,
        ?NodeRepository $nodeRepo = null,
        ?RouterService $routerService = null,
        ?PDO $pdo = null
    ) {
        $this->pdo           = $pdo ?: Database::getConnection();
        $this->routerRepo    = $routerRepo ?: new RouterRepository($this->pdo);
        $this->popRepo       = $popRepo ?: new PopRepository($this->pdo);
        $this->nodeRepo      = $nodeRepo ?: new NodeRepository($this->pdo);
        $this->routerService = $routerService ?: new RouterService($this->routerRepo, null, $this->pdo);
    }

    /**
     * Router List & Detail (routers.php)
     */
    public function index(): void
    {
        Middleware::requireLogin();

        $msg = '';

        // ── POST ACTIONS ──────────────────────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'create') {
                $this->routerService->create($_POST);
                $msg = 'success:Router berhasil ditambahkan.';
            } elseif ($action === 'update') {
                $this->routerService->update((int)$_POST['id'], $_POST);
                $msg = 'success:Router berhasil diperbarui.';
            } elseif ($action === 'delete') {
                $this->routerService->delete((int)$_POST['id']);
                Response::redirect('/routers?msg=deleted');
                return;
            }
        }

        // ── DETAIL VIEW ──────────────────────────────────────────────────────────────
        $detail_id = (int)($_GET['id'] ?? 0);
        $detail = null;
        if ($detail_id) {
            $detail = $this->routerRepo->find($detail_id);
            if (!$detail) {
                Response::redirect('/routers');
                return;
            }
        }

        // ── LIST ─────────────────────────────────────────────────────────────────────
        $routers = $this->routerService->getAll();
        $pops = $this->pdo->query("
            SELECT p.id, CONCAT(n.name,' / ',p.name) AS label
            FROM pops p JOIN nodes n ON n.id=p.node_id ORDER BY n.name,p.name")->fetchAll(PDO::FETCH_ASSOC);

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
        if (!$msg_text && isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
            $msg_type = 'success';
            $msg_text = 'Router berhasil dihapus.';
        }

        View::render('routers/index', [
            'detail_id' => $detail_id,
            'detail'    => $detail,
            'routers'   => $routers,
            'pops'      => $pops,
            'msg'       => $msg,
            'msg_type'  => $msg_type,
            'msg_text'  => $msg_text,
        ]);
    }

    /**
     * Hotspot Management (hotspot.php)
     */
    public function hotspot(): void
    {
        Middleware::requireLogin();

        $msg = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'create') {
                $this->routerService->createHotspot($_POST);
                $msg = 'success:Hotspot berhasil ditambahkan.';
            } elseif ($action === 'update') {
                $this->routerService->updateHotspot((int)$_POST['id'], $_POST);
                $msg = 'success:Hotspot berhasil diperbarui.';
            } elseif ($action === 'delete') {
                $this->routerService->deleteHotspot((int)$_POST['id']);
                $msg = 'success:Hotspot berhasil dihapus.';
            }
        }

        $hotspots = $this->routerService->getHotspots();
        $nodes    = $this->pdo->query("SELECT id, name FROM nodes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $routers  = $this->pdo->query("SELECT id, name FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        View::render('routers/hotspot', [
            'hotspots'  => $hotspots,
            'nodes'     => $nodes,
            'routers'   => $routers,
            'msg'       => $msg,
            'msg_type'  => $msg_type,
            'msg_text'  => $msg_text,
        ]);
    }

    /**
     * IP Pool Management (ip_pool.php)
     */
    public function ipPool(): void
    {
        Middleware::requireLogin();

        $routers = $this->routerService->getAll();
        $selected_router_id = $_GET['router_id'] ?? ($routers[0]['id'] ?? null);
        $selected_router    = null;
        if ($selected_router_id) {
            foreach ($routers as $r) {
                if ($r['id'] == $selected_router_id) {
                    $selected_router = $r;
                    break;
                }
            }
        }

        $msg   = '';
        $pools = [];
        $conn  = null;

        if ($selected_router && $selected_router_id) {
            $conn = $this->routerService->getConnection((int)$selected_router_id);

            if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? '';
                if ($action === 'create') {
                    $res = $this->routerService->createPool((int)$selected_router_id, $_POST['name'], $_POST['ranges']);
                    $msg = isset($res['!trap']) ? 'danger:' . $res['!trap'] : 'success:Pool berhasil ditambahkan di MikroTik.';
                } elseif ($action === 'delete') {
                    $this->routerService->deletePool((int)$selected_router_id, $_POST['mikrotik_id']);
                    $msg = 'success:Pool berhasil dihapus.';
                } elseif ($action === 'update') {
                    $this->routerService->updatePool((int)$selected_router_id, $_POST['mikrotik_id'], $_POST['name'], $_POST['ranges']);
                    $msg = 'success:Pool berhasil diperbarui.';
                }
            }

            if ($conn) {
                $pools = $this->routerService->getPools((int)$selected_router_id);
            }
        }

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        View::render('routers/ip_pool', [
            'routers'            => $routers,
            'selected_router_id' => $selected_router_id,
            'selected_router'    => $selected_router,
            'client'             => $conn ? $conn->getClient() : null,
            'pools'              => $pools,
            'msg'                => $msg,
            'msg_type'           => $msg_type,
            'msg_text'           => $msg_text,
        ]);
    }

    /**
     * PPPoE Server Management (pppoe_server.php)
     */
    public function pppoeServer(): void
    {
        Middleware::requireRole(['superadmin', 'admin', 'teknisi']);

        $routers = $this->pdo->query("
            SELECT m.id, m.name, m.host, m.username, m.password, m.port, m.api_ssl,
                   p.name AS pop_name
            FROM mikrotiks m JOIN pops p ON p.id = m.pop_id
            ORDER BY m.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $selected_router_id = $_GET['router_id'] ?? ($routers[0]['id'] ?? null);
        $selected_router    = null;
        foreach ($routers as $r) {
            if ($r['id'] == $selected_router_id) {
                $selected_router = $r;
                break;
            }
        }

        $msg     = '';
        $servers = [];
        $ifaces  = [];
        $conn    = null;

        // ── Koneksi & CRUD ────────────────────────────────────────────────────────────
        if ($selected_router && $selected_router_id) {
            $conn = $this->routerService->getConnection((int)$selected_router_id);

            if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? '';

                $build_auth = function () {
                    $methods = $_POST['authentication'] ?? [];
                    $valid   = ['mschap2', 'mschap1', 'chap', 'pap'];
                    $methods = array_values(array_intersect($methods, $valid));
                    return implode(',', $methods) ?: 'mschap2,mschap1,chap,pap';
                };

                if ($action === 'create') {
                    $data = [
                        'service-name'   => trim($_POST['service_name']),
                        'interface'      => $_POST['interface'],
                        'authentication' => $build_auth(),
                        'disabled'       => 'no',
                    ];
                    if (!empty($_POST['max_mru']))   $data['max-mru']           = (int)$_POST['max_mru'];
                    if (!empty($_POST['max_mtu']))   $data['max-mtu']           = (int)$_POST['max_mtu'];
                    if (!empty($_POST['keepalive'])) $data['keepalive-timeout'] = (int)$_POST['keepalive'];

                    $res = $this->routerService->createPppoeServer((int)$selected_router_id, $data);
                    $msg = isset($res[0]['!trap'])
                        ? 'danger:' . ($res[0]['message'] ?? 'Error dari MikroTik.')
                        : 'success:PPPoE Server berhasil ditambahkan.';

                } elseif ($action === 'update') {
                    $data = [
                        'service-name'   => trim($_POST['service_name']),
                        'interface'      => $_POST['interface'],
                        'authentication' => $build_auth(),
                    ];
                    if (!empty($_POST['max_mru']))   $data['max-mru']           = (int)$_POST['max_mru'];
                    if (!empty($_POST['max_mtu']))   $data['max-mtu']           = (int)$_POST['max_mtu'];
                    if (!empty($_POST['keepalive'])) $data['keepalive-timeout'] = (int)$_POST['keepalive'];

                    $this->routerService->updatePppoeServer((int)$selected_router_id, $_POST['mikrotik_id'], $data);
                    $msg = 'success:PPPoE Server berhasil diperbarui.';

                } elseif ($action === 'delete') {
                    $this->routerService->deletePppoeServer((int)$selected_router_id, $_POST['mikrotik_id']);
                    $msg = 'success:PPPoE Server berhasil dihapus.';

                } elseif ($action === 'toggle') {
                    $current = ($_POST['current_disabled'] ?? '') === 'true' ? 'no' : 'yes';
                    $this->routerService->togglePppoeServer((int)$selected_router_id, $_POST['mikrotik_id'], $current);
                    $msg = 'success:Status PPPoE Server diubah.';
                }
            }

            if ($conn) {
                $servers = $this->routerService->getPppoeServers((int)$selected_router_id);
                $ifaces  = $this->routerService->getRouterInterfaces((int)$selected_router_id);
            }
        }

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
        $AUTH_OPTIONS = ['mschap2' => 'MS-CHAP v2', 'mschap1' => 'MS-CHAP v1', 'chap' => 'CHAP', 'pap' => 'PAP'];

        View::render('routers/pppoe_server', [
            'routers'            => $routers,
            'selected_router_id' => $selected_router_id,
            'selected_router'    => $selected_router,
            'client'             => $conn ? $conn->getClient() : null,
            'servers'            => $servers,
            'ifaces'             => $ifaces,
            'AUTH_OPTIONS'       => $AUTH_OPTIONS,
            'msg'                => $msg,
            'msg_type'           => $msg_type,
            'msg_text'           => $msg_text,
        ]);
    }
}
