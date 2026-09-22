<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\PackageService;
use App\Services\Import\PackageImportService;
use App\Services\Import\PPPImportService;
use App\Repositories\PackageRepository;
use App\Repositories\RouterRepository;
use App\MikroTik\Connection;
use App\MikroTik\PPPManager;
use PDO;
use Exception;

class PackageController
{
    private PackageService $packageService;
    private PackageRepository $packageRepo;
    private RouterRepository $routerRepo;
    private PDO $pdo;

    public function __construct(
        ?PackageService $packageService = null,
        ?PackageRepository $packageRepo = null,
        ?RouterRepository $routerRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?: Database::getConnection();
        $this->packageRepo = $packageRepo ?: new PackageRepository($this->pdo);
        $this->routerRepo = $routerRepo ?: new RouterRepository($this->pdo);
        $this->packageService = $packageService ?: new PackageService($this->packageRepo, $this->routerRepo);
    }

    /**
     * Package List (packages.php)
     */
    public function index(): void
    {
        Middleware::requireLogin();

        $msg = $_GET['msg'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $used_stmt = $this->pdo->prepare("SELECT COUNT(*) FROM customers WHERE package_id=?");
                $used_stmt->execute([$id]);
                $used_count = (int)$used_stmt->fetchColumn();

                if ($used_count > 0) {
                    $msg = 'danger:Tidak bisa menghapus — paket masih digunakan oleh ' . $used_count . ' pelanggan.';
                } else {
                    $del_stmt = $this->pdo->prepare("SELECT p.mikrotik_profile_name, mk.host, mk.username, mk.password, mk.port, mk.api_ssl
                        FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id WHERE p.id=?");
                    $del_stmt->execute([$id]);
                    $del_pkg = $del_stmt->fetch(PDO::FETCH_ASSOC);

                    $this->pdo->prepare("DELETE FROM packages WHERE id=?")->execute([$id]);

                    if ($del_pkg) {
                        $conn = Connection::fromRouter($del_pkg);
                        if ($conn->isConnected()) {
                            $all_prof = $conn->query('/ppp/profile', 'print');
                            $found = array_values(array_filter($all_prof,
                                fn($p) => ($p['name'] ?? '') === $del_pkg['mikrotik_profile_name']));
                            if (!empty($found[0]['.id'])) {
                                $conn->query('/ppp/profile', 'remove', [], $found[0]['.id']);
                                $msg = 'success:Paket dihapus dan PPP Profile dihapus dari MikroTik.';
                            } else {
                                $msg = 'success:Paket dihapus (PPP Profile tidak ditemukan di MikroTik).';
                            }
                        } else {
                            $msg = 'warning:Paket dihapus dari database, tapi router tidak bisa dihubungi.';
                        }
                    } else {
                        $msg = 'success:Paket berhasil dihapus.';
                    }
                }
                Response::redirect('/packages?msg=' . urlencode($msg));
                return;
            } elseif ($action === 'toggle') {
                $id = (int)($_POST['id'] ?? 0);
                $this->pdo->prepare("UPDATE packages SET is_active=NOT is_active WHERE id=?")->execute([$id]);
                $msg = 'success:Status paket diubah.';
                Response::redirect('/packages?msg=' . urlencode($msg));
                return;
            }
        }

        $packages = $this->pdo->query("
            SELECT p.*, mk.name AS router_name, mk.id AS mk_id,
                   mk.host, mk.username, mk.password, mk.port,
                   COALESCE(p.queue_type,'default') AS queue_type,
                   COALESCE(p.local_address,'') AS local_address,
                   COALESCE(p.remote_pool,'') AS remote_pool,
                   COALESCE(p.dns_server1,'') AS dns_server1,
                   COALESCE(p.dns_server2,'') AS dns_server2
            FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id
            ORDER BY mk.name, p.name")->fetchAll(PDO::FETCH_ASSOC);

        $routers = $this->pdo->query("SELECT id,name,host FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        View::render('packages/index', [
            'packages' => $packages,
            'routers' => $routers,
            'msg' => $msg,
            'msg_type' => $msg_type,
            'msg_text' => $msg_text,
        ]);
    }

    /**
     * Package Form Add / Edit (/packages/create, /packages/{id}/edit)
     */
    public function form(): void
    {
        Middleware::requireLogin();

        $msg = '';
        $edit_id = (int)($_GET['id'] ?? 0);
        $is_edit = $edit_id > 0;
        $pkg     = null;

        if ($is_edit) {
            $stmt = $this->pdo->prepare("
                SELECT p.*, mk.name AS router_name, mk.id AS mk_id,
                       COALESCE(p.only_one,'default') AS only_one
                FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id
                WHERE p.id = ?");
            $stmt->execute([$edit_id]);
            $pkg = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pkg) {
                Response::redirect('/packages?msg=danger:Paket tidak ditemukan.');
                return;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $mk_id  = (int)($_POST['mikrotik_id'] ?? 0);
            $router = null;
            if ($mk_id) {
                $rq = $this->pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
                $rq->execute([$mk_id]);
                $router = $rq->fetch(PDO::FETCH_ASSOC);
            }

            $speedToBps = function(float $val, string $unit): int {
                return match($unit) {
                    'G' => (int)($val * 1_000_000_000),
                    'M' => (int)($val * 1_000_000),
                    'k' => (int)($val * 1_000),
                    default => (int)$val,
                };
            };

            $bpsToMkRate = function(int $bps): string {
                if ($bps <= 0) return '0';
                if ($bps >= 1_000_000_000 && $bps % 1_000_000_000 === 0) return ($bps / 1_000_000_000) . 'G';
                if ($bps >= 1_000_000 && $bps % 1_000_000 === 0) return ($bps / 1_000_000) . 'M';
                if ($bps >= 1_000 && $bps % 1_000 === 0) return ($bps / 1_000) . 'k';
                return $bps . '';
            };

            $buildRateLimit = function(int $rx, int $tx, int $rx_b=0, int $tx_b=0, int $rx_thr=0, int $tx_thr=0, int $rx_t=0, int $tx_t=0) use ($bpsToMkRate): string {
                $s = $bpsToMkRate($rx) . '/' . $bpsToMkRate($tx);
                if ($rx_b > 0 || $tx_b > 0) {
                    $s .= ' ' . $bpsToMkRate($rx_b) . '/' . $bpsToMkRate($tx_b);
                    $s .= ' ' . $bpsToMkRate($rx_thr) . '/' . $bpsToMkRate($tx_thr);
                    $s .= ' ' . $rx_t . '/' . $tx_t;
                }
                return $s;
            };

            $tx      = $speedToBps((float)$_POST['tx_val'], $_POST['tx_unit'] ?? 'M');
            $rx      = $speedToBps((float)$_POST['rx_val'], $_POST['rx_unit'] ?? 'M');
            $tx_b    = $speedToBps((float)($_POST['tx_burst_val'] ?? 0), $_POST['tx_burst_unit'] ?? 'M');
            $rx_b    = $speedToBps((float)($_POST['rx_burst_val'] ?? 0), $_POST['rx_burst_unit'] ?? 'M');
            $tx_thr  = $speedToBps((float)($_POST['tx_thr_val'] ?? 0), $_POST['tx_thr_unit'] ?? 'M');
            $rx_thr  = $speedToBps((float)($_POST['rx_thr_val'] ?? 0), $_POST['rx_thr_unit'] ?? 'M');

            $profile_name  = trim($_POST['mikrotik_profile_name']);
            $queue_type    = $_POST['queue_type'] ?? 'default';
            $parent_queue  = trim($_POST['parent_queue'] ?? '') ?: null;
            $insert_before = trim($_POST['queue_insert_before'] ?? '') ?: null;
            $tx_bt  = (int)($_POST['tx_burst_time'] ?? 0);
            $rx_bt  = (int)($_POST['rx_burst_time'] ?? 0);
            $tx_pri = (int)($_POST['tx_priority'] ?? 8);
            $rx_pri = (int)($_POST['rx_priority'] ?? 8);
            $only_one = $_POST['only_one'] ?? 'default';

            if ($action === 'create') {
                $local_addr  = trim($_POST['local_address'] ?? '');
                $remote_pool = trim($_POST['remote_pool'] ?? '');
                $dns1        = trim($_POST['dns_server1'] ?? '');
                $dns2        = trim($_POST['dns_server2'] ?? '');

                $this->pdo->prepare("INSERT INTO packages
                    (mikrotik_id,name,price,tx_max_limit,rx_max_limit,
                     tx_burst_limit,rx_burst_limit,tx_burst_threshold,rx_burst_threshold,
                     tx_burst_time,rx_burst_time,tx_priority,rx_priority,
                     queue_type,parent_queue,queue_insert_before,
                     local_address,remote_pool,dns_server1,dns_server2,
                     mikrotik_profile_name,only_one,is_active,description)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)")
                    ->execute([$mk_id, $_POST['name'], $_POST['price'], $tx, $rx,
                        $tx_b, $rx_b, $tx_thr, $rx_thr, $tx_bt, $rx_bt, $tx_pri, $rx_pri,
                        $queue_type, $parent_queue, $insert_before,
                        $local_addr, $remote_pool, $dns1, $dns2,
                        $profile_name, $only_one, $_POST['description'] ?? '']);

                if ($router) {
                    $conn = Connection::fromRouter($router);
                    if ($conn->isConnected()) {
                        $rate = $buildRateLimit($tx, $rx, $tx_b, $rx_b, $tx_thr, $rx_thr, $tx_bt, $rx_bt);
                        $params = [
                            'name'       => $profile_name,
                            'rate-limit' => $rate,
                            'comment'    => '[SNM-PROFILE] ' . $profile_name . ' | via Silver Network Management | ' . date('Y-m-d')
                        ];
                        if ($local_addr)  $params['local-address']  = $local_addr;
                        if ($remote_pool) $params['remote-address'] = $remote_pool;
                        if ($dns1) $params['dns-server'] = $dns2 ? "$dns1,$dns2" : $dns1;
                        if ($queue_type && $queue_type !== 'default') $params['queue-type'] = $queue_type;
                        if ($parent_queue) $params['parent-queue'] = $parent_queue;
                        $params['only-one'] = $only_one;
                        $conn->query('/ppp/profile', 'add', $params);
                    }
                }
                Response::redirect('/packages?msg=' . urlencode('success:Paket ditambahkan dan disinkronkan ke MikroTik.'));
                return;
            } elseif ($action === 'update') {
                $old_pkg = $this->pdo->prepare("SELECT mikrotik_profile_name FROM packages WHERE id=?");
                $old_pkg->execute([$_POST['id']]);
                $old_profile_name = $old_pkg->fetchColumn();

                $local_addr  = trim($_POST['local_address'] ?? '');
                $remote_pool = trim($_POST['remote_pool'] ?? '');
                $dns1        = trim($_POST['dns_server1'] ?? '');
                $dns2        = trim($_POST['dns_server2'] ?? '');

                $this->pdo->prepare("UPDATE packages SET
                    mikrotik_id=?,name=?,price=?,tx_max_limit=?,rx_max_limit=?,
                    tx_burst_limit=?,rx_burst_limit=?,tx_burst_threshold=?,rx_burst_threshold=?,
                    tx_burst_time=?,rx_burst_time=?,tx_priority=?,rx_priority=?,
                    queue_type=?,parent_queue=?,queue_insert_before=?,
                    local_address=?,remote_pool=?,dns_server1=?,dns_server2=?,
                    mikrotik_profile_name=?,only_one=?,description=? WHERE id=?")
                    ->execute([$mk_id, $_POST['name'], $_POST['price'], $tx, $rx,
                        $tx_b, $rx_b, $tx_thr, $rx_thr, $tx_bt, $rx_bt, $tx_pri, $rx_pri,
                        $queue_type, $parent_queue, $insert_before,
                        $local_addr, $remote_pool, $dns1, $dns2,
                        $profile_name, $only_one, $_POST['description'] ?? '', $_POST['id']]);

                if ($router) {
                    $conn = Connection::fromRouter($router);
                    if ($conn->isConnected()) {
                        $search_name = $old_profile_name ?: $profile_name;
                        $all_profiles = $conn->query('/ppp/profile', 'print');
                        $existing = array_values(array_filter($all_profiles, fn($p) => ($p['name'] ?? '') === $search_name));

                        $rate = $buildRateLimit($tx, $rx, $tx_b, $rx_b, $tx_thr, $rx_thr, $tx_bt, $rx_bt);
                        $set_params = [
                            'name'       => $profile_name,
                            'rate-limit' => $rate,
                            'comment'    => '[SNM-PROFILE] ' . $profile_name . ' | via Silver Network Management | ' . date('Y-m-d')
                        ];
                        if ($local_addr)  $set_params['local-address']  = $local_addr;
                        if ($remote_pool) $set_params['remote-address'] = $remote_pool;
                        if ($dns1) $set_params['dns-server'] = $dns2 ? "$dns1,$dns2" : $dns1;
                        if ($queue_type && $queue_type !== 'default') $set_params['queue-type'] = $queue_type;
                        $set_params['parent-queue'] = $parent_queue ?: '';
                        $set_params['only-one'] = $only_one;

                        if (!empty($existing[0]['.id'])) {
                            $conn->query('/ppp/profile', 'set', $set_params, $existing[0]['.id']);
                        } else {
                            $conn->query('/ppp/profile', 'add', $set_params);
                        }
                    }
                }
                Response::redirect('/packages?msg=' . urlencode('success:Paket berhasil diperbarui.'));
                return;
            }
        }

        $routers = $this->pdo->query("SELECT id,name,host FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $page_title = $is_edit ? 'Edit Paket Internet' : 'Tambah Paket Internet';
        $page_sub   = $is_edit ? htmlspecialchars($pkg['name'] ?? '') : 'Konfigurasi paket & PPP profile';

        View::render('packages/form', [
            'is_edit' => $is_edit,
            'pkg' => $pkg,
            'routers' => $routers,
            'page_title' => $page_title,
            'page_sub' => $page_sub,
            'msg' => $msg,
        ]);
    }

    /**
     * Package Import across routers (/packages/import)
     */
    public function import(): void
    {
        Middleware::requireLogin();

        $msg = $_GET['msg'] ?? '';

        $impSpeedToBps = function(float $v, string $u): int {
            return match($u) { 'G'=>(int)($v*1e9), 'M'=>(int)($v*1e6), 'k'=>(int)($v*1e3), default=>(int)$v };
        };
        $impBpsToMkRate = function(int $b): string {
            if ($b<=0) return '0';
            if ($b>=1e9&&$b%1e9===0) return ($b/1e9).'G';
            if ($b>=1e6&&$b%1e6===0) return ($b/1e6).'M';
            if ($b>=1e3&&$b%1e3===0) return ($b/1e3).'k';
            return $b.'';
        };
        $impBuildRate = function(int $rx,int $tx,int $rb=0,int $tb=0,int $rt=0,int $tt=0,int $rbt=0,int $tbt=0) use ($impBpsToMkRate): string {
            $s = $impBpsToMkRate($rx).'/'.$impBpsToMkRate($tx);
            if ($rb>0||$tb>0) {
                $s .= ' '.$impBpsToMkRate($rb).'/'.$impBpsToMkRate($tb);
                $s .= ' '.$impBpsToMkRate($rt).'/'.$impBpsToMkRate($tt);
                $s .= ' '.$rbt.'/'.$tbt;
            }
            return $s;
        };
        $impComment = function(string $name): string {
            return '[SNM-PROFILE] '.$name.' | via Silver Network Management | '.date('Y-m-d');
        };

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
            $target_id = (int)($_POST['target_router_id'] ?? 0);
            $rows      = $_POST['pkg'] ?? [];

            if (!$target_id || empty($rows)) {
                $msg = 'danger:Pilih router tujuan dan minimal 1 paket.';
            } else {
                $rq = $this->pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
                $rq->execute([$target_id]);
                $target_router = $rq->fetch(PDO::FETCH_ASSOC);

                if (!$target_router) {
                    $msg = 'danger:Router tujuan tidak ditemukan.';
                } else {
                    $conn = Connection::fromRouter($target_router);
                    $all_profiles = $conn->isConnected() ? $conn->query('/ppp/profile', 'print') : [];

                    $ok = 0; $skip = 0; $warn = [];

                    foreach ($rows as $idx => $r) {
                        if (empty($r['selected'])) continue;

                        $name         = trim($r['name']         ?? '');
                        $profile_name = trim($r['profile_name'] ?? '');
                        $price        = (int)($r['price'] ?? 0);
                        $description  = trim($r['description']  ?? '');

                        $tx  = $impSpeedToBps((float)($r['tx_val']  ?? 0), $r['tx_unit']  ?? 'M');
                        $rx  = $impSpeedToBps((float)($r['rx_val']  ?? 0), $r['rx_unit']  ?? 'M');
                        $txb = $impSpeedToBps((float)($r['txb_val'] ?? 0), $r['txb_unit'] ?? 'M');
                        $rxb = $impSpeedToBps((float)($r['rxb_val'] ?? 0), $r['rxb_unit'] ?? 'M');
                        $txt = $impSpeedToBps((float)($r['txt_val'] ?? 0), $r['txt_unit'] ?? 'M');
                        $rxt = impSpeedToBps((float)($r['rxt_val'] ?? 0), $r['rxt_unit'] ?? 'M');
                        $txbt = (int)($r['tx_burst_time'] ?? 0);
                        $rxbt = (int)($r['rx_burst_time'] ?? 0);
                        $tx_pri = (int)($r['tx_priority'] ?? 8);
                        $rx_pri = (int)($r['rx_priority'] ?? 8);

                        $queue_type    = $r['queue_type']    ?? 'default';
                        $parent_queue  = trim($r['parent_queue'] ?? '') ?: null;
                        $insert_before = trim($r['insert_before'] ?? '') ?: null;
                        $only_one      = in_array($r['only_one']??'',['yes','no','default']) ? $r['only_one'] : 'default';
                        $local_addr    = trim($r['local_address'] ?? '');
                        $remote_pool   = trim($r['remote_pool']   ?? '');
                        $dns1          = trim($r['dns1'] ?? '');
                        $dns2          = trim($r['dns2'] ?? '');

                        if (!$name || !$profile_name) { $skip++; continue; }

                        $dup = $this->pdo->prepare("SELECT COUNT(*) FROM packages WHERE mikrotik_id=? AND name=?");
                        $dup->execute([$target_id, $name]);
                        if ((int)$dup->fetchColumn() > 0 && empty($r['overwrite'])) {
                            $warn[] = "\"$name\" dilewati (sudah ada, overwrite tidak dipilih)";
                            $skip++;
                            continue;
                        }

                        if ((int)$dup->fetchColumn() > 0 && !empty($r['overwrite'])) {
                            $this->pdo->prepare("UPDATE packages SET
                                price=?,tx_max_limit=?,rx_max_limit=?,
                                tx_burst_limit=?,rx_burst_limit=?,tx_burst_threshold=?,rx_burst_threshold=?,
                                tx_burst_time=?,rx_burst_time=?,tx_priority=?,rx_priority=?,
                                queue_type=?,parent_queue=?,queue_insert_before=?,
                                local_address=?,remote_pool=?,dns_server1=?,dns_server2=?,
                                mikrotik_profile_name=?,only_one=?,description=?
                                WHERE mikrotik_id=? AND name=?")
                                ->execute([$price,$tx,$rx,$txb,$rxb,$txt,$rxt,$txbt,$rxbt,$tx_pri,$rx_pri,
                                    $queue_type,$parent_queue,$insert_before,$local_addr,$remote_pool,$dns1,$dns2,
                                    $profile_name,$only_one,$description,$target_id,$name]);
                        } else {
                            $this->pdo->prepare("INSERT INTO packages
                                (mikrotik_id,name,price,tx_max_limit,rx_max_limit,
                                 tx_burst_limit,rx_burst_limit,tx_burst_threshold,rx_burst_threshold,
                                 tx_burst_time,rx_burst_time,tx_priority,rx_priority,
                                 queue_type,parent_queue,queue_insert_before,
                                 local_address,remote_pool,dns_server1,dns_server2,
                                 mikrotik_profile_name,is_active,only_one,description)
                                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)")
                                ->execute([$target_id,$name,$price,$tx,$rx,$txb,$rxb,$txt,$rxt,$txbt,$rxbt,
                                    $tx_pri,$rx_pri,$queue_type,$parent_queue,$insert_before,
                                    $local_addr,$remote_pool,$dns1,$dns2,$profile_name,$only_one,$description]);
                        }

                        if ($conn->isConnected()) {
                            $rate   = $impBuildRate($tx,$rx,$txb,$rxb,$txt,$rxt,$txbt,$rxbt);
                            $params = ['name'=>$profile_name,'rate-limit'=>$rate,'comment'=>$impComment($profile_name)];
                            if ($local_addr)  $params['local-address']  = $local_addr;
                            if ($remote_pool) $params['remote-address'] = $remote_pool;
                            if ($dns1)        $params['dns-server'] = $dns2 ? "$dns1,$dns2" : $dns1;
                            if ($queue_type && $queue_type !== 'default') $params['queue-type'] = $queue_type;
                            if ($parent_queue) $params['parent-queue'] = $parent_queue;
                            $params['only-one'] = $only_one;

                            $existing = array_values(array_filter($all_profiles, fn($p) => ($p['name']??'') === $profile_name));
                            if ($insert_before) $params['insert-queue-before'] = $insert_before;

                            if (!empty($existing[0]['.id'])) {
                                $conn->query('/ppp/profile', 'set', $params, $existing[0]['.id']);
                            } else {
                                $conn->query('/ppp/profile', 'add', $params);
                                $all_profiles = $conn->query('/ppp/profile', 'print');
                            }
                        }
                        $ok++;
                    }

                    $summary = "Import selesai: $ok paket berhasil" . ($skip ? ", $skip dilewati" : '') . '.';
                    if ($warn) $summary .= ' | '.implode('; ', $warn);
                    $msg = ($ok>0 ? 'success' : 'warning').':'.$summary;
                    Response::redirect('/packages/import?msg=' . urlencode($msg));
                    return;
                }
            }
        }

        $routers  = $this->pdo->query("SELECT id,name,host FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $all_pkgs = $this->pdo->query("
            SELECT p.*, mk.name AS router_name, mk.id AS mk_id,
                   COALESCE(p.queue_type,'default') AS queue_type,
                   COALESCE(p.only_one,'default')   AS only_one,
                   COALESCE(p.local_address,'')     AS local_address,
                   COALESCE(p.remote_pool,'')       AS remote_pool,
                   COALESCE(p.dns_server1,'')       AS dns_server1,
                   COALESCE(p.dns_server2,'')       AS dns_server2,
                   COALESCE(p.parent_queue,'')      AS parent_queue,
                   COALESCE(p.queue_insert_before,'') AS queue_insert_before
            FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id
            ORDER BY mk.name, p.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $pkgs_by_router = [];
        foreach ($all_pkgs as $p) {
            $pkgs_by_router[$p['mk_id']][] = $p;
        }

        $queue_types = ['default','default-small','ethernet-default','wireless-default','hotspot-default',
            'only-hardware-queue','multi-queue-ethernet-default','pcq-upload-default',
            'pcq-download-default','sfq','red','cake','synchronous-default'];

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        View::render('packages/import', [
            'routers' => $routers,
            'all_pkgs' => $all_pkgs,
            'pkgs_by_router' => $pkgs_by_router,
            'queue_types' => $queue_types,
            'msg' => $msg,
            'msg_type' => $msg_type,
            'msg_text' => $msg_text,
        ]);
    }
}
