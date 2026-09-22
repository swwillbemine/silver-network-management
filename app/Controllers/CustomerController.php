<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\CustomerService;
use App\Repositories\CustomerRepository;
use App\Repositories\PackageRepository;
use App\Repositories\RouterRepository;
use App\Repositories\NodeRepository;
use App\MikroTik\Connection;
use App\MikroTik\PPPManager;
use PDO;
use Exception;

class CustomerController
{
    private CustomerService $customerService;
    private CustomerRepository $customerRepo;
    private PackageRepository $packageRepo;
    private RouterRepository $routerRepo;
    private NodeRepository $nodeRepo;
    private PDO $pdo;

    public function __construct(
        ?CustomerService $customerService = null,
        ?CustomerRepository $customerRepo = null,
        ?PackageRepository $packageRepo = null,
        ?RouterRepository $routerRepo = null,
        ?NodeRepository $nodeRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?: Database::getConnection();
        $this->customerRepo = $customerRepo ?: new CustomerRepository($this->pdo);
        $this->packageRepo = $packageRepo ?: new PackageRepository($this->pdo);
        $this->routerRepo = $routerRepo ?: new RouterRepository($this->pdo);
        $this->nodeRepo = $nodeRepo ?: new NodeRepository($this->pdo);
        $this->customerService = $customerService ?: new CustomerService($this->customerRepo, $this->packageRepo, $this->routerRepo);
    }

    /**
     * Customer List page (customers.php)
     */
    public function index(): void
    {
        Middleware::requireLogin();

        $msg = $_GET['msg'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $id = (int)($_POST['id'] ?? 0);

            if ($action === 'delete') {
                $c = $this->pdo->prepare("SELECT c.*, mk.host, mk.username, mk.password, mk.port, mk.api_ssl
                    FROM customers c JOIN packages p ON p.id=c.package_id
                    JOIN mikrotiks mk ON mk.id=p.mikrotik_id WHERE c.id=?");
                $c->execute([$id]);
                $cust = $c->fetch(PDO::FETCH_ASSOC);

                $this->pdo->prepare("DELETE py FROM payments py JOIN billings b ON b.id=py.billing_id WHERE b.customer_id=?")
                    ->execute([$id]);
                $this->pdo->prepare("DELETE FROM billings WHERE customer_id=?")->execute([$id]);
                $this->pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);

                if ($cust && !empty($cust['pppoe_username'])) {
                    $conn = Connection::fromRouter($cust);
                    if ($conn->isConnected()) {
                        $ppp = new PPPManager($conn);
                        $secrets = $ppp->getSecrets(['name' => $cust['pppoe_username']]);
                        $matched = array_values(array_filter($secrets, fn($s) => ($s['name'] ?? '') === $cust['pppoe_username']));
                        if (!empty($matched[0]['.id'])) {
                            $ppp->deleteSecret($matched[0]['.id']);
                        }
                    }
                }
                $msg = 'success:Pelanggan berhasil dihapus.';
            } elseif ($action === 'isolate' || $action === 'activate') {
                $newStatus = $action === 'isolate' ? 'isolated' : 'active';
                $this->pdo->prepare("UPDATE customers SET status=? WHERE id=?")->execute([$newStatus, $id]);

                $c = $this->pdo->prepare("SELECT c.*, mk.host, mk.username, mk.password, mk.port, mk.api_ssl
                    FROM customers c JOIN packages p ON p.id=c.package_id
                    JOIN mikrotiks mk ON mk.id=p.mikrotik_id WHERE c.id=?");
                $c->execute([$id]);
                $cust = $c->fetch(PDO::FETCH_ASSOC);

                if ($cust && !empty($cust['pppoe_username'])) {
                    $conn = Connection::fromRouter($cust);
                    if ($conn->isConnected()) {
                        $ppp = new PPPManager($conn);
                        $secrets = $ppp->getSecrets(['name' => $cust['pppoe_username']]);
                        $matched = array_values(array_filter($secrets, fn($s) => ($s['name'] ?? '') === $cust['pppoe_username']));
                        if (!empty($matched[0]['.id'])) {
                            $ppp->updateSecret($matched[0]['.id'], [
                                'disabled' => $newStatus === 'isolated' ? 'true' : 'false'
                            ]);
                        }
                    }
                }
                $msg = 'success:Status pelanggan diperbarui.';
            }

            Response::redirect('/customers' . ($msg ? '?msg=' . urlencode($msg) : ''));
            return;
        }

        $per_page      = (int)($_GET['per_page'] ?? 25);
        $per_page      = in_array($per_page, [10, 25, 50, 100]) ? $per_page : 25;
        $page          = max(1, (int)($_GET['page'] ?? 1));
        $status_filter = $_GET['status'] ?? '';
        $search        = trim($_GET['q'] ?? '');
        $mk_filter     = (int)($_GET['mk'] ?? 0);
        $billing_type_filter = $_GET['billing_type'] ?? '';

        $where_parts = [];
        $params_count = [];
        $params_page  = [];

        if ($status_filter) {
            $where_parts[] = "c.status = ?";
            $params_count[] = $params_page[] = $status_filter;
        }
        if ($billing_type_filter) {
            $where_parts[] = "c.billing_type = ?";
            $params_count[] = $params_page[] = $billing_type_filter;
        }
        if ($mk_filter) {
            $where_parts[] = "mk.id = ?";
            $params_count[] = $params_page[] = $mk_filter;
        }
        if ($search) {
            $where_parts[] = "(c.name LIKE ? OR c.pppoe_username LIKE ? OR c.phone LIKE ? OR c.customer_number LIKE ?)";
            $like = "%$search%";
            $params_count = array_merge($params_count, [$like, $like, $like, $like]);
            $params_page  = array_merge($params_page,  [$like, $like, $like, $like]);
        }

        $where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

        $count_stmt = $this->pdo->prepare("SELECT COUNT(*) FROM customers c JOIN packages pk ON pk.id=c.package_id JOIN mikrotiks mk ON mk.id=pk.mikrotik_id $where_sql");
        $count_stmt->execute($params_count);
        $total_rows = (int)$count_stmt->fetchColumn();
        $total_pages = max(1, (int)ceil($total_rows / $per_page));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $per_page;

        $params_page[] = $per_page;
        $params_page[] = $offset;

        $cust_stmt = $this->pdo->prepare("
            SELECT c.*, c.customer_number, n.name AS node_name,
                   pk.name AS package_name, pk.price AS package_price,
                   c.discount,
                   mk.id AS mk_id, mk.name AS router_name
            FROM customers c
            JOIN nodes n ON n.id = c.node_id
            JOIN packages pk ON pk.id = c.package_id
            JOIN mikrotiks mk ON mk.id = pk.mikrotik_id
            $where_sql
            ORDER BY c.name
            LIMIT ? OFFSET ?");
        $cust_stmt->execute($params_page);
        $customers = $cust_stmt->fetchAll(PDO::FETCH_ASSOC);

        $nodes = $this->pdo->query("SELECT id, name, COALESCE(owner_contact,'') AS phone FROM nodes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $packages = $this->pdo->query("SELECT p.id, p.name AS pkg_name,
            p.tx_max_limit, p.rx_max_limit, p.price,
            p.mikrotik_id, m.name AS router_name
            FROM packages p JOIN mikrotiks m ON m.id=p.mikrotik_id
            WHERE p.is_active=1 ORDER BY m.name, p.name")->fetchAll(PDO::FETCH_ASSOC);
        $routers = $this->pdo->query("SELECT id, name FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $stats_join  = "FROM customers c JOIN packages pk ON pk.id=c.package_id JOIN mikrotiks mk ON mk.id=pk.mikrotik_id";
        $stats_where = $mk_filter ? "WHERE mk.id=$mk_filter" : '';
        $cnt_all      = (int)$this->pdo->query("SELECT COUNT(*) $stats_join $stats_where")->fetchColumn();
        $cnt_active   = (int)$this->pdo->query("SELECT COUNT(*) $stats_join " . ($mk_filter ? "WHERE mk.id=$mk_filter AND" : 'WHERE') . " c.status='active'")->fetchColumn();
        $cnt_isolated = (int)$this->pdo->query("SELECT COUNT(*) $stats_join " . ($mk_filter ? "WHERE mk.id=$mk_filter AND" : 'WHERE') . " c.status='isolated'")->fetchColumn();
        $cnt_free     = (int)$this->pdo->query("SELECT COUNT(*) $stats_join " . ($mk_filter ? "WHERE mk.id=$mk_filter AND" : 'WHERE') . " c.billing_type='free'")->fetchColumn();

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        View::render('customers/index', [
            'customers' => $customers,
            'nodes' => $nodes,
            'packages' => $packages,
            'routers' => $routers,
            'cnt_all' => $cnt_all,
            'cnt_active' => $cnt_active,
            'cnt_isolated' => $cnt_isolated,
            'cnt_free' => $cnt_free,
            'total_rows' => $total_rows,
            'total_pages' => $total_pages,
            'page' => $page,
            'per_page' => $per_page,
            'search' => $search,
            'status_filter' => $status_filter,
            'billing_type_filter' => $billing_type_filter,
            'mk_filter' => $mk_filter,
            'msg' => $msg,
            'msg_type' => $msg_type,
            'msg_text' => $msg_text,
        ]);
    }

    /**
     * Customer Add / Edit Form (/customers/create, /customers/{id}/edit)
     */
    public function form(): void
    {
        Middleware::requireLogin();

        $edit_id = (int)($_GET['id'] ?? 0);
        $is_edit = $edit_id > 0;
        $cust = null;
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $back_url = $baseUrl . '/customers';

        if ($is_edit) {
            $stmt = $this->pdo->prepare("
                SELECT c.*, n.name AS node_name, pk.name AS package_name, mk.id AS mk_id
                FROM customers c
                JOIN nodes n ON n.id = c.node_id
                JOIN packages pk ON pk.id = c.package_id
                JOIN mikrotiks mk ON mk.id = pk.mikrotik_id
                WHERE c.id = ?");
            $stmt->execute([$edit_id]);
            $cust = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cust) {
                Response::redirect('/customers?msg=danger:Pelanggan tidak ditemukan.');
                return;
            }
            $back_url = $baseUrl . '/customers/' . $edit_id;
        }

        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'create') {
                $pppoe_user = trim($_POST['pppoe_username'] ?? '');
                $pppoe_pass = trim($_POST['pppoe_password'] ?? '');
                if ($pppoe_pass === '') {
                    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
                    $pppoe_pass = '';
                    for ($i = 0; $i < 8; $i++) $pppoe_pass .= $chars[random_int(0, strlen($chars)-1)];
                }

                $pkg = $this->pdo->prepare("SELECT p.*, mk.host, mk.username AS mk_user, mk.password AS mk_pass, mk.port, mk.api_ssl FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id WHERE p.id=?");
                $pkg->execute([$_POST['package_id']]);
                $package = $pkg->fetch(PDO::FETCH_ASSOC);

                $this->pdo->beginTransaction();
                try {
                    $this->pdo->prepare("INSERT INTO customers
                        (node_id,package_id,name,identity_number,phone,email,
                         pppoe_username,pppoe_password,installation_date,
                         billing_cycle_date,billing_due_date,isolation_date,status,billing_type,
                         router_brand,router_type,router_mac,remote_mgmt_port,wifi_ssid,wifi_password)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([
                            $_POST['node_id'], $_POST['package_id'],
                            $_POST['name'], $_POST['identity_number'],
                            $_POST['phone'], $_POST['email'],
                            $pppoe_user, $pppoe_pass,
                            $_POST['installation_date'],
                            $_POST['billing_cycle_date'] ?: 20,
                            $_POST['billing_due_date'] ?: 30,
                            $_POST['isolation_date'] ?: 1,
                            $_POST['status'] ?: 'active',
                            $_POST['billing_type'] ?: 'normal',
                            $_POST['router_brand'], $_POST['router_type'], $_POST['router_mac'],
                            ($_POST['remote_mgmt_port'] !== '' ? (int)$_POST['remote_mgmt_port'] : null),
                            $_POST['wifi_ssid'], $_POST['wifi_password']
                        ]);

                    $new_id = (int)$this->pdo->lastInsertId();

                    $cust_num_stmt = $this->pdo->prepare("SELECT COUNT(*) FROM customers WHERE customer_number=?");
                    do {
                        $cust_number = (string)random_int(1, 9) . str_pad((string)random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
                        $cust_num_stmt->execute([$cust_number]);
                    } while ($cust_num_stmt->fetchColumn());

                    if ($pppoe_user === '') $pppoe_user = $cust_number;
                    $this->pdo->prepare("UPDATE customers SET customer_number=?, pppoe_username=? WHERE id=?")
                        ->execute([$cust_number, $pppoe_user, $new_id]);

                    if ($package) {
                        $conn = Connection::fromRouter($package);
                        if ($conn->isConnected()) {
                            $ppp = new PPPManager($conn);
                            $comment = trim($_POST['name']) . ' (' . trim($pppoe_user) . ')';
                            $ppp->addSecret($pppoe_user, $pppoe_pass, $package['mikrotik_profile_name'] ?: 'default', 'pppoe', $comment);
                        }
                    }

                    $this->pdo->commit();
                    Response::redirect('/customers/' . $new_id . '?msg=' . urlencode('success:Pelanggan berhasil ditambahkan.'));
                    return;
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    $msg = 'danger:Gagal menyimpan: ' . $e->getMessage();
                }
            } elseif ($action === 'update') {
                $old = $this->pdo->prepare("SELECT * FROM customers WHERE id=?");
                $old->execute([$_POST['id']]);
                $old_cust = $old->fetch(PDO::FETCH_ASSOC);

                $upd_pass = trim($_POST['pppoe_password'] ?? '');
                if ($upd_pass === '') {
                    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
                    $upd_pass = '';
                    for ($i = 0; $i < 8; $i++) $upd_pass .= $chars[random_int(0, strlen($chars)-1)];
                }

                $this->pdo->prepare("UPDATE customers SET
                    node_id=?,package_id=?,name=?,identity_number=?,phone=?,email=?,
                    pppoe_username=?,pppoe_password=?,installation_date=?,
                    billing_cycle_date=?,billing_due_date=?,isolation_date=?,status=?,billing_type=?,
                    router_brand=?,router_type=?,router_mac=?,remote_mgmt_port=?,wifi_ssid=?,wifi_password=?,discount=?
                    WHERE id=?")
                    ->execute([
                        $_POST['node_id'], $_POST['package_id'],
                        $_POST['name'], $_POST['identity_number'],
                        $_POST['phone'], $_POST['email'],
                        $_POST['pppoe_username'], $upd_pass,
                        $_POST['installation_date'],
                        $_POST['billing_cycle_date'], $_POST['billing_due_date'],
                        $_POST['isolation_date'],
                        $_POST['status'], $_POST['billing_type'] ?: 'normal',
                        $_POST['router_brand'], $_POST['router_type'], $_POST['router_mac'],
                        ($_POST['remote_mgmt_port'] !== '' ? (int)$_POST['remote_mgmt_port'] : null),
                        $_POST['wifi_ssid'], $_POST['wifi_password'],
                        (int)($_POST['discount'] ?? 0),
                        $_POST['id']
                    ]);

                $pkg2 = $this->pdo->prepare("SELECT p.mikrotik_profile_name, mk.* FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id WHERE p.id=?");
                $pkg2->execute([$_POST['package_id']]);
                $mk = $pkg2->fetch(PDO::FETCH_ASSOC);
                $mk_warnings = [];
                if ($mk) {
                    $conn = Connection::fromRouter($mk);
                    if ($conn->isConnected()) {
                        $ppp = new PPPManager($conn);
                        $_raw = $ppp->getSecrets(['name' => $old_cust['pppoe_username']]);
                        $secrets = array_values(array_filter($_raw, fn($s) => ($s['name'] ?? '') === $old_cust['pppoe_username']));
                        $comment = trim($_POST['name']) . ' (' . trim($_POST['pppoe_username']) . ')';
                        if (!empty($secrets[0]['.id'])) {
                            $set_params = [
                                'name'     => $_POST['pppoe_username'],
                                'password' => $upd_pass,
                                'profile'  => $mk['mikrotik_profile_name'],
                                'comment'  => $comment,
                            ];
                            if ($_POST['status'] === 'isolated') $set_params['disabled'] = 'true';
                            elseif ($_POST['status'] === 'active') $set_params['disabled'] = 'false';
                            $ppp->updateSecret($secrets[0]['.id'], $set_params);
                        } else {
                            $ppp->addSecret($_POST['pppoe_username'], $upd_pass, $mk['mikrotik_profile_name'] ?: 'default', 'pppoe', $comment);
                            $mk_warnings[] = 'Secret tidak ditemukan di router, dibuat ulang otomatis.';
                        }
                    } else {
                        $mk_warnings[] = 'Router tidak dapat dihubungi. Perubahan disimpan di database saja.';
                    }
                }

                $warn = $mk_warnings ? ' (' . implode(' ', $mk_warnings) . ')' : '';
                Response::redirect('/customers/' . (int)$_POST['id'] . '?msg=' . urlencode('success:Data berhasil diperbarui.' . $warn));
                return;
            }
        }

        $nodes = $this->pdo->query("SELECT id, name, COALESCE(owner_contact,'') AS phone FROM nodes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $packages = $this->pdo->query("SELECT p.id, p.name AS pkg_name,
            p.tx_max_limit, p.rx_max_limit, p.price, p.mikrotik_id, m.name AS router_name
            FROM packages p JOIN mikrotiks m ON m.id=p.mikrotik_id
            WHERE p.is_active=1 ORDER BY m.name, p.name")->fetchAll(PDO::FETCH_ASSOC);
        $routers = $this->pdo->query("SELECT id, name FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
        $page_title = $is_edit ? 'Edit Pelanggan' : 'Tambah Pelanggan';
        $page_sub   = $is_edit ? htmlspecialchars($cust['name']) : 'Data baru';

        View::render('customers/form', [
            'is_edit' => $is_edit,
            'cust' => $cust,
            'back_url' => $back_url,
            'nodes' => $nodes,
            'packages' => $packages,
            'routers' => $routers,
            'page_title' => $page_title,
            'page_sub' => $page_sub,
            'msg' => $msg,
            'msg_type' => $msg_type,
            'msg_text' => $msg_text,
        ]);
    }

    /**
     * Customer Detail Page (/customers/{id})
     */
    public function detail(): void
    {
        Middleware::requireLogin();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            Response::redirect('/customers');
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   n.name AS node_name,
                   p.name AS package_name, p.tx_max_limit, p.rx_max_limit, p.price AS package_price,
                   mk.id AS mk_id, mk.name AS router_name, mk.host AS mk_host,
                   mk.username AS mk_user, mk.password AS mk_pass, mk.port AS mk_port, mk.api_ssl
            FROM customers c
            LEFT JOIN nodes n ON n.id = c.node_id
            LEFT JOIN packages p ON p.id = c.package_id
            LEFT JOIN mikrotiks mk ON mk.id = p.mikrotik_id
            WHERE c.id = ?");
        $stmt->execute([$id]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            Response::redirect('/customers');
            return;
        }

        $billings = $this->pdo->prepare("
            SELECT b.*, py.amount_paid, py.method, py.notes AS pay_notes, b.paid_at AS paid_time
            FROM billings b
            LEFT JOIN payments py ON py.billing_id = b.id
            WHERE b.customer_id = ?
            ORDER BY b.period DESC LIMIT 24");
        $billings->execute([$id]);
        $bills = $billings->fetchAll(PDO::FETCH_ASSOC);

        $live_session = null;
        $queue_data   = null;

        if ($c['mk_host'] && $c['pppoe_username']) {
            $conn = Connection::fromRouter([
                'host' => $c['mk_host'],
                'username' => $c['mk_user'],
                'password' => $c['mk_pass'],
                'port' => (int)$c['mk_port'],
                'api_ssl' => (bool)$c['api_ssl']
            ]);

            if ($conn->isConnected()) {
                $actives = $conn->query('/ppp/active', 'print');
                foreach ($actives as $s) {
                    if (($s['name'] ?? '') === $c['pppoe_username']) {
                        $live_session = $s;
                        break;
                    }
                }

                if ($live_session) {
                    $uname = $c['pppoe_username'];
                    $queues = $conn->query('/queue/simple', 'print');
                    foreach ($queues as $q) {
                        $qn = trim($q['name'] ?? '', '<>');
                        if (strtolower($qn) === strtolower($uname) ||
                            strtolower($qn) === strtolower("pppoe-$uname")) {
                            $parse = fn($v) => array_map('intval', explode('/', $v) + ['0','0']);
                            [$tx_rate,  $rx_rate]  = $parse($q['rate']      ?? '0/0');
                            [$tx_bytes, $rx_bytes] = $parse($q['bytes']     ?? '0/0');
                            [$tx_limit, $rx_limit] = $parse($q['max-limit'] ?? '0/0');
                            $queue_data = [
                                'dl_rate'  => $tx_rate,
                                'ul_rate'  => $rx_rate,
                                'dl_bytes' => $tx_bytes,
                                'ul_bytes' => $rx_bytes,
                                'dl_limit' => $tx_limit,
                                'ul_limit' => $rx_limit,
                            ];
                            break;
                        }
                    }
                }
            }
        }

        $status_colors = ['active'=>'green','isolated'=>'yellow','terminated'=>'red'];
        $status_sc = $status_colors[$c['status']] ?? 'secondary';
        $wan_ip    = $live_session['address'] ?? null;
        $mgmt_port = (int)($c['remote_mgmt_port'] ?? 80) ?: 80;

        $proxy_url = null;
        if ($wan_ip && $mgmt_port) {
            $secret = '';
            try {
                $r = $this->pdo->query("SELECT setting_value FROM settings WHERE setting_key='proxy_secret' LIMIT 1")->fetch();
                if ($r && strlen($r['setting_value'] ?? '') >= 32) {
                    $secret = $r['setting_value'];
                }
            } catch (Exception $e) {}

            if (!$secret) {
                $secret = bin2hex(random_bytes(32));
                try {
                    $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('proxy_secret',?)
                                   ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$secret]);
                } catch (Exception $e) {}
            }

            $bucket = floor(time() / 3600);
            $token  = hash_hmac('sha256', "{$c['id']}|{$wan_ip}|{$mgmt_port}|{$bucket}", $secret);
            $proxy_url = BASE_URL . '/router-proxy/' . $c['id'] . '/' . $token . '/';
        }

        $msg = $_GET['msg'] ?? '';
        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        View::render('customers/detail', [
            'c' => $c,
            'bills' => $bills,
            'live_session' => $live_session,
            'queue_data' => $queue_data,
            'status_sc' => $status_sc,
            'wan_ip' => $wan_ip,
            'mgmt_port' => $mgmt_port,
            'proxy_url' => $proxy_url,
            'msg' => $msg,
            'msg_type' => $msg_type,
            'msg_text' => $msg_text,
        ]);
    }
}

