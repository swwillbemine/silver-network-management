<?php
// packages.php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

require_once BASE_PATH . '/mikrotik/connection.php';
require_once BASE_PATH . '/mikrotik/ppp.php';

$msg = $_GET['msg'] ?? '';

// Migration: tambah kolom only_one jika belum ada
try { $pdo->query("ALTER TABLE packages ADD COLUMN only_one ENUM('default','yes','no') NOT NULL DEFAULT 'default'"); } catch (PDOException $e) {}

// ── Helpers ───────────────────────────────────────────────────────────────
function bitsToHuman(int $bits): string {
    if ($bits >= 1_000_000_000) return round($bits/1_000_000_000, 2).'G';
    if ($bits >= 1_000_000)    return round($bits/1_000_000, 2).'M';
    if ($bits >= 1_000)        return round($bits/1_000, 0).'k';
    return $bits.'';
}

// Produce clean integer MikroTik rate string: prefer k over fractional M
// e.g. 1500000 → "1500k", 12000000 → "12M", 12800000 → "12800k"
function bpsToMkRate(int $bps): string {
    if ($bps <= 0) return '0';
    // Use G only if exactly divisible
    if ($bps >= 1_000_000_000 && $bps % 1_000_000_000 === 0)
        return ($bps / 1_000_000_000).'G';
    // Use M only if exactly divisible (no decimal needed)
    if ($bps >= 1_000_000 && $bps % 1_000_000 === 0)
        return ($bps / 1_000_000).'M';
    // Use k for everything else (includes 1500k, 12800k, etc.)
    if ($bps >= 1_000 && $bps % 1_000 === 0)
        return ($bps / 1_000).'k';
    // Raw bps
    return $bps.'';
}

// Build full PPP profile rate-limit string in MikroTik format:
// "rx/tx [rx-burst/tx-burst [rx-threshold/tx-threshold [rx-time/tx-time]]]"
// NOTE: MikroTik PPP rate-limit uses rx=download, tx=upload (client perspective)
function buildRateLimit(int $rx, int $tx, int $rx_burst=0, int $tx_burst=0,
                        int $rx_thr=0, int $tx_thr=0, int $rx_time=0, int $tx_time=0): string {
    $s = bpsToMkRate($rx).'/'.bpsToMkRate($tx);
    // Only append burst params if at least burst rate is set
    if ($rx_burst > 0 || $tx_burst > 0) {
        $s .= ' '.bpsToMkRate($rx_burst).'/'.bpsToMkRate($tx_burst);
        // Threshold
        $s .= ' '.bpsToMkRate($rx_thr).'/'.bpsToMkRate($tx_thr);
        // Time (in seconds for PPP profile)
        $s .= ' '.$rx_time.'/'.$tx_time;
    }
    return $s;
}

// Convert value + unit string to bps
// unit: 'M' | 'k' | 'b'
function speedToBps(float $val, string $unit): int {
    return match($unit) {
        'G' => (int)($val * 1_000_000_000),
        'M' => (int)($val * 1_000_000),
        'k' => (int)($val * 1_000),
        default => (int)$val,
    };
}

// Given bps, return [value, unit] that best represents it (like MikroTik)
function bpsToDisplay(int $bps): array {
    if ($bps >= 1_000_000_000 && $bps % 1_000_000_000 === 0)
        return [(int)($bps/1_000_000_000), 'G'];
    if ($bps >= 1_000_000 && $bps % 1_000_000 === 0)
        return [(int)($bps/1_000_000), 'M'];
    if ($bps >= 1_000 && $bps % 1_000 === 0)
        return [(int)($bps/1_000), 'k'];
    // Non-round: prefer kbps display
    return [round($bps/1_000, 2), 'k'];
}

function appComment(string $name, string $type = 'PROFILE'): string {
    return '[SNM-'.$type.'] '.$name.' | via Silver Network Management | '.date('Y-m-d');
}



// ── POST ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mk_id  = (int)($_POST['mikrotik_id'] ?? 0);
    $router = null;
    if ($mk_id) {
        $rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
        $rq->execute([$mk_id]);
        $router = $rq->fetch(PDO::FETCH_ASSOC);
    }

    if (in_array($action, ['create','update'])) {
        // Speed fields: submitted as value + unit (e.g. tx_val=10, tx_unit=M → 10 Mbps)
        $tx      = speedToBps((float)$_POST['tx_val'],      $_POST['tx_unit']      ?? 'M');
        $rx      = speedToBps((float)$_POST['rx_val'],      $_POST['rx_unit']      ?? 'M');
        $tx_b    = speedToBps((float)($_POST['tx_burst_val']  ?? 0), $_POST['tx_burst_unit']  ?? 'M');
        $rx_b    = speedToBps((float)($_POST['rx_burst_val']  ?? 0), $_POST['rx_burst_unit']  ?? 'M');
        $tx_thr  = speedToBps((float)($_POST['tx_thr_val']    ?? 0), $_POST['tx_thr_unit']    ?? 'M');
        $rx_thr  = speedToBps((float)($_POST['rx_thr_val']    ?? 0), $_POST['rx_thr_unit']    ?? 'M');

        $profile_name  = trim($_POST['mikrotik_profile_name']);
        $queue_type    = $_POST['queue_type']          ?? 'default';
        $parent_queue  = trim($_POST['parent_queue']   ?? '') ?: null;
        $insert_before = trim($_POST['queue_insert_before'] ?? '') ?: null;
        $tx_bt  = (int)($_POST['tx_burst_time'] ?? 0);
        $rx_bt  = (int)($_POST['rx_burst_time'] ?? 0);
        $tx_pri = (int)($_POST['tx_priority'] ?? 8);
        $rx_pri = (int)($_POST['rx_priority'] ?? 8);

        if ($action === 'create') {
            $local_addr  = trim($_POST['local_address'] ?? '');
            $remote_pool = trim($_POST['remote_pool']   ?? '');
            $dns1        = trim($_POST['dns_server1']   ?? '');
            $dns2        = trim($_POST['dns_server2']   ?? '');
            $pdo->prepare("INSERT INTO packages
                (mikrotik_id,name,price,tx_max_limit,rx_max_limit,
                 tx_burst_limit,rx_burst_limit,tx_burst_threshold,rx_burst_threshold,
                 tx_burst_time,rx_burst_time,tx_priority,rx_priority,
                 queue_type,parent_queue,queue_insert_before,
                 local_address,remote_pool,dns_server1,dns_server2,
                 mikrotik_profile_name,is_active,description)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)")
                ->execute([$mk_id,$_POST['name'],$_POST['price'],$tx,$rx,
                    $tx_b,$rx_b,$tx_thr,$rx_thr,$tx_bt,$rx_bt,$tx_pri,$rx_pri,
                    $queue_type,$parent_queue,$insert_before,
                    $local_addr,$remote_pool,$dns1,$dns2,
                    $profile_name,$_POST['description']??'']);

            if ($router) {
                $client = get_mikrotik_client($router['host'],$router['username'],$router['password'],$router['port']);
                if ($client) {
                    // PPP profile rate-limit format: "rx/tx [burst-rx/burst-tx [thr-rx/thr-tx [time-rx/time-tx]]]"
                    // In MikroTik PPP profile: rx = client upload (router receives), tx = client download (router sends)
                    // Our form: tx_val = upload, rx_val = download → swap for MikroTik
                    $rate = buildRateLimit($tx, $rx, $tx_b, $rx_b, $tx_thr, $rx_thr, $tx_bt, $rx_bt);
                    $params = ['name' => $profile_name, 'rate-limit' => $rate,
                               'comment' => appComment($profile_name, 'PROFILE')];
                    // Only send address fields if not empty (empty string causes MikroTik to reject)
                    if ($local_addr)  $params['local-address']  = $local_addr;
                    if ($remote_pool) $params['remote-address'] = $remote_pool;
                    if ($dns1) $params['dns-server'] = $dns2 ? "$dns1,$dns2" : $dns1;
                    // PPP profile queue-type = single value (not upload/download split like simple queue)
                    if ($queue_type && $queue_type !== 'default')
                        $params['queue-type'] = $queue_type;
                    if ($parent_queue) $params['parent-queue'] = $parent_queue;
                    $params['only-one'] = $_POST['only_one'] ?? 'default';
                    if ($insert_before) {
                        $_all_pf = mikrotik_query($client, '/ppp/profile', 'print', []);
                        $_tgt    = array_values(array_filter($_all_pf, fn($x) => ($x['name']??'') === $insert_before));
                        if (!empty($_tgt[0]['.id'])) $params['place-before'] = $_tgt[0]['.id'];
                    }
                    $raw = mikrotik_query($client, '/ppp/profile', 'add', $params);
                    // Check for !trap (error) in response
                    $trap = array_filter($raw, fn($r) => is_array($r)
                        ? in_array('!trap',$r)||in_array('failure',$r)
                        : (is_string($r)&&(str_contains($r,'!trap')||str_contains($r,'failure'))));
                    if ($trap) $msg = 'warning:Paket tersimpan, tapi MikroTik error: '.json_encode(array_values($trap));
                } else {
                    $msg = 'warning:Paket tersimpan, tapi router tidak bisa dihubungi.';
                }
            }
            if (!$msg) $msg = 'success:Paket ditambahkan dan PPP Profile dibuat di MikroTik.';

        } else { // UPDATE
            // Get old profile name BEFORE updating DB
            $old_pkg = $pdo->prepare("SELECT mikrotik_profile_name FROM packages WHERE id=?");
            $old_pkg->execute([$_POST['id']]);
            $old_profile_name = $old_pkg->fetchColumn();

            $local_addr  = trim($_POST['local_address'] ?? '');
            $remote_pool = trim($_POST['remote_pool']   ?? '');
            $dns1        = trim($_POST['dns_server1']   ?? '');
            $dns2        = trim($_POST['dns_server2']   ?? '');
            $pdo->prepare("UPDATE packages SET
                mikrotik_id=?,name=?,price=?,tx_max_limit=?,rx_max_limit=?,
                tx_burst_limit=?,rx_burst_limit=?,tx_burst_threshold=?,rx_burst_threshold=?,
                tx_burst_time=?,rx_burst_time=?,tx_priority=?,rx_priority=?,
                queue_type=?,parent_queue=?,queue_insert_before=?,
                local_address=?,remote_pool=?,dns_server1=?,dns_server2=?,
                mikrotik_profile_name=?,description=? WHERE id=?")
                ->execute([$mk_id,$_POST['name'],$_POST['price'],$tx,$rx,
                    $tx_b,$rx_b,$tx_thr,$rx_thr,$tx_bt,$rx_bt,$tx_pri,$rx_pri,
                    $queue_type,$parent_queue,$insert_before,
                    $local_addr,$remote_pool,$dns1,$dns2,
                    $profile_name,$_POST['description']??'',$_POST['id']]);

            // Push update to MikroTik
            if ($router) {
                $client = get_mikrotik_client($router['host'],$router['username'],$router['password'],$router['port']);
                if ($client) {
                    // Cari profile lama di MikroTik — ambil semua lalu filter manual
                    // (sama seperti pattern di customers.php, agar filter library tidak jadi masalah)
                    $search_name  = $old_profile_name ?: $profile_name;
                    $_all_profiles = mikrotik_query($client, '/ppp/profile', 'print', []);
                    $existing = array_values(array_filter($_all_profiles, fn($p) => ($p['name'] ?? '') === $search_name));

                    // Same rate-limit swap: our tx=upload, rx=download → MikroTik rx/tx = upload/download
                    $rate = buildRateLimit($tx, $rx, $tx_b, $rx_b, $tx_thr, $rx_thr, $tx_bt, $rx_bt);
                    $set_params = ['name' => $profile_name, 'rate-limit' => $rate,
                                   'comment' => appComment($profile_name, 'PROFILE')];
                    if ($local_addr)  $set_params['local-address']  = $local_addr;
                    if ($remote_pool) $set_params['remote-address'] = $remote_pool;
                    if ($dns1) $set_params['dns-server'] = $dns2 ? "$dns1,$dns2" : $dns1;
                    if ($queue_type && $queue_type !== 'default')
                        $set_params['queue-type'] = $queue_type;
                    if ($parent_queue)   $set_params['parent-queue'] = $parent_queue;
                    else                 $set_params['parent-queue'] = '';
                    $set_params['only-one'] = $_POST['only_one'] ?? 'default';

                    if (!empty($existing[0]['.id'])) {
                        // Profile ditemukan → update (termasuk rename jika nama berubah)
                        mikrotik_query($client, '/ppp/profile', 'set', $set_params, $existing[0]['.id']);
                        if ($old_profile_name && $old_profile_name !== $profile_name)
                            $msg = 'success:Paket diperbarui. PPP Profile berhasil direname di MikroTik: '.$old_profile_name.' → '.$profile_name;
                        else
                            $msg = 'success:Paket diperbarui dan PPP Profile di MikroTik disinkronkan.';
                    } else {
                        // Profile belum ada → buat baru
                        mikrotik_query($client, '/ppp/profile', 'add', $set_params);
                        $msg = 'success:Paket diperbarui. Profile baru dibuat di MikroTik (profile lama tidak ditemukan).';
                    }
                } else {
                    $msg = 'warning:Paket diperbarui di database, tapi router tidak bisa dihubungi.';
                }
            } else {
                $msg = 'success:Paket diperbarui.';
            }
        }

    } elseif ($action === 'delete') {
        // Cek apakah ada pelanggan yang masih pakai paket ini
        $used_stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE package_id=?");
        $used_stmt->execute([$_POST['id']]);
        $used_count = (int)$used_stmt->fetchColumn();
        if ($used_count > 0) {
            $msg = 'danger:Tidak bisa menghapus — paket masih digunakan oleh '.$used_count.' pelanggan.';
        } else {
            // Ambil info paket & router sebelum dihapus dari DB
            $del_stmt = $pdo->prepare("SELECT p.mikrotik_profile_name, mk.host, mk.username, mk.password, mk.port
                FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id WHERE p.id=?");
            $del_stmt->execute([$_POST['id']]);
            $del_pkg = $del_stmt->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("DELETE FROM packages WHERE id=?")->execute([$_POST['id']]);

            // Hapus PPP Profile dari MikroTik
            if ($del_pkg) {
                $client = get_mikrotik_client($del_pkg['host'], $del_pkg['username'], $del_pkg['password'], $del_pkg['port']);
                if ($client) {
                    $_all_prof = mikrotik_query($client, '/ppp/profile', 'print', []);
                    $found = array_values(array_filter($_all_prof,
                        fn($p) => ($p['name'] ?? '') === $del_pkg['mikrotik_profile_name']));
                    if (!empty($found[0]['.id'])) {
                        mikrotik_query($client, '/ppp/profile', 'remove', [], $found[0]['.id']);
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
        header('Location: packages.php?msg=' . urlencode($msg)); exit;
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE packages SET is_active=NOT is_active WHERE id=?")->execute([$_POST['id']]);
        $msg = 'success:Status paket diubah.';
        header('Location: packages.php?msg=' . urlencode($msg)); exit;
    }
}

$packages = $pdo->query("
    SELECT p.*, mk.name AS router_name, mk.id AS mk_id,
           mk.host, mk.username, mk.password, mk.port,
           COALESCE(p.queue_type,'default') AS queue_type,
           COALESCE(p.local_address,'') AS local_address,
           COALESCE(p.remote_pool,'') AS remote_pool,
           COALESCE(p.dns_server1,'') AS dns_server1,
           COALESCE(p.dns_server2,'') AS dns_server2
    FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id
    ORDER BY mk.name, p.name")->fetchAll(PDO::FETCH_ASSOC);

$routers = $pdo->query("SELECT id,name,host FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];

$queue_types = [
    'default'                      => 'default',
    'default-small'                => 'default-small',
    'ethernet-default'             => 'ethernet-default',
    'wireless-default'             => 'wireless-default',
    'hotspot-default'              => 'hotspot-default',
    'only-hardware-queue'          => 'only-hardware-queue',
    'multi-queue-ethernet-default' => 'multi-queue-ethernet-default',
    'pcq-upload-default'           => 'pcq-upload-default',
    'pcq-download-default'         => 'pcq-download-default',
    'sfq'                          => 'sfq',
    'red'                          => 'red',
    'cake'                         => 'cake',
    'synchronous-default'          => 'synchronous-default',
];
?>
<!doctype html>
<html lang="id">
<?php require_once __DIR__ . '/layout/header.php'; ?>
<body>
<div class="page">
  <?php require_once __DIR__ . '/layout/sidebar.php'; ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle">Pelanggan</div>
            <h2 class="page-title">Paket Internet</h2>
          </div>
          <div class="col-auto">
            <a href="package_import.php" class="btn btn-ghost-secondary me-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/><polyline points="7 11 12 16 17 11"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
              Import Paket
            </a>
            <a href="package_form.php" class="btn btn-primary">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14M5 12h14"/></svg>
              Tambah Paket
            </a>
          </div>
        </div>
      </div>
    </div>
    <div class="page-body">
      <div class="container-xl">
        <?php if ($msg_text): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible mb-3">
          <?= htmlspecialchars($msg_text) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php
        // Group packages by router
        $pkg_by_router = [];
        foreach ($packages as $p) {
            $pkg_by_router[$p['mk_id']]['name']  = $p['router_name'];
            $pkg_by_router[$p['mk_id']]['items'][] = $p;
        }
        ?>

        <div class="card">
          <div class="card-header d-flex align-items-center gap-2 flex-wrap">
            <h3 class="card-title me-2">
              Daftar Paket
              <span class="badge bg-secondary-lt ms-1" id="pkg-count"><?= count($packages) ?></span>
            </h3>
            <!-- Router filter dropdown -->
            <select id="filter-router" class="form-select form-select-sm" style="width:auto;min-width:180px;">
              <option value="all">Semua Router (<?= count($packages) ?>)</option>
              <?php foreach ($pkg_by_router as $mk_id => $grp): ?>
              <option value="<?= $mk_id ?>">
                <?= htmlspecialchars($grp['name']) ?> (<?= count($grp['items']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="search-pkg" class="form-control form-control-sm ms-auto" placeholder="Cari nama paket..." style="width:200px;">
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr>
                  <th>Nama Paket</th>
                  <th>Router</th>
                  <th>Harga/bln</th>
                  <th>Rate Limit</th>
                  <th>PPP Profile</th>
                  <th>Status</th>
                  <th class="w-1">Aksi</th>
                </tr>
              </thead>
              <tbody id="pkg-table">
              <?php foreach ($packages as $p):
                [$up_v,$up_u] = bpsToDisplay((int)$p['tx_max_limit']);
                [$dn_v,$dn_u] = bpsToDisplay((int)$p['rx_max_limit']);
              ?>
              <tr data-mk="<?= $p['mk_id'] ?>" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>">
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($p['description']??'') ?></div>
                </td>
                <td>
                  <span class="badge bg-azure-lt"><?= htmlspecialchars($p['router_name']) ?></span>
                </td>
                <td class="fw-semibold">Rp <?= number_format($p['price'],0,',','.') ?></td>
                <td>
                  <code class="text-primary">↓<?= $dn_v.$dn_u ?></code>
                  <span class="text-muted mx-1">/</span>
                  <code class="text-secondary">↑<?= $up_v.$up_u ?></code>
                </td>
                <td>
                  <code class="small text-muted"><?= htmlspecialchars($p['mikrotik_profile_name']) ?></code>
                  <?php if ($p['dns_server1']): ?>
                  <div class="text-muted mt-1" style="font-size:.7rem;">
                    <span title="DNS">⬡</span>
                    <?= htmlspecialchars($p['dns_server1']) ?>
                    <?php if ($p['dns_server2']): ?>
                    / <?= htmlspecialchars($p['dns_server2']) ?>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                </td>
                <td><span class="badge bg-<?= $p['is_active']?'success':'secondary' ?>-lt"><?= $p['is_active']?'Aktif':'Nonaktif' ?></span></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="package_form.php?id=<?= $p['id'] ?>" class="btn btn-ghost-secondary">Edit</a>
                    <button class="btn btn-ghost-danger"    onclick="deletePkg(<?= $p['id'] ?>,'<?= addslashes($p['name']) ?>')">Hapus</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$packages): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Belum ada paket.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer d-flex align-items-center justify-content-between" id="pkg-pagination-footer">
            <div class="text-muted small" id="pkg-pager-info"></div>
            <ul class="pagination m-0" id="pkg-pager"></ul>
          </div>
        </div>
      </div>
    </div>

<!-- DELETE MODAL -->
<div class="modal modal-blur fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="del-id">
        <div class="modal-body"><p>Hapus paket <strong id="del-name"></strong>?</p></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Hapus</button>
        </div>
      </form>
    </div>
  </div>
</div>

    
<script>
function deletePkg(id, name) {
    document.getElementById('del-id').value = id;
    document.getElementById('del-name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Router select + search filter for packages
let activeRouter = 'all';
document.getElementById('search-pkg').addEventListener('input', filterPkgTable);
document.getElementById('filter-router').addEventListener('change', function() {
    activeRouter = this.value;
    filterPkgTable();
});

// ── CLIENT-SIDE PAGINATION ────────────────────────────────────────────────────
const PKG_PER_PAGE = 20;
let pkgCurrentPage = 1;

function getAllVisiblePkgRows() {
    const q  = document.getElementById('search-pkg').value.toLowerCase();
    const mk = (typeof activeRouter !== 'undefined') ? activeRouter : 'all';
    return Array.from(document.querySelectorAll('#pkg-table tr[data-mk]')).filter(tr => {
        const matchRouter = mk === 'all' || tr.dataset.mk === mk;
        const matchText   = !q || tr.dataset.name.includes(q) || tr.textContent.toLowerCase().includes(q);
        return matchRouter && matchText;
    });
}

function renderPkgPagination() {
    const rows       = getAllVisiblePkgRows();
    const total      = rows.length;
    const totalPages = Math.max(1, Math.ceil(total / PKG_PER_PAGE));
    pkgCurrentPage   = Math.min(pkgCurrentPage, totalPages);
    const start = (pkgCurrentPage - 1) * PKG_PER_PAGE;
    const end   = start + PKG_PER_PAGE;

    // Show/hide all rows first
    document.querySelectorAll('#pkg-table tr[data-mk]').forEach(tr => tr.style.display = 'none');
    rows.forEach((tr, i) => { tr.style.display = (i >= start && i < end) ? '' : 'none'; });

    const footer = document.getElementById('pkg-pagination-footer');
    const info   = document.getElementById('pkg-pager-info');
    const pager  = document.getElementById('pkg-pager');
    if (!footer) return;
    footer.style.removeProperty('display');
    info.innerHTML = `Menampilkan <strong>${Math.min(start+1,total)}–${Math.min(end,total)}</strong> dari <strong>${total}</strong> paket`;

    let html = '';
    const pd = pkgCurrentPage <= 1 ? 'disabled' : '';
    const nd = pkgCurrentPage >= totalPages ? 'disabled' : '';
    html += `<li class="page-item ${pd}"><a class="page-link" href="#" onclick="goPkgPage(${pkgCurrentPage-1});return false;"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="15 18 9 12 15 6"/></svg></a></li>`;
    let s = Math.max(1, pkgCurrentPage-2), e2 = Math.min(totalPages, pkgCurrentPage+2);
    if (s > 1) { html += `<li class="page-item"><a class="page-link" href="#" onclick="goPkgPage(1);return false;">1</a></li>`; if (s > 2) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`; }
    for (let i = s; i <= e2; i++) html += `<li class="page-item ${i===pkgCurrentPage?'active':''}"><a class="page-link" href="#" onclick="goPkgPage(${i});return false;">${i}</a></li>`;
    if (e2 < totalPages) { if (e2 < totalPages-1) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`; html += `<li class="page-item"><a class="page-link" href="#" onclick="goPkgPage(${totalPages});return false;">${totalPages}</a></li>`; }
    html += `<li class="page-item ${nd}"><a class="page-link" href="#" onclick="goPkgPage(${pkgCurrentPage+1});return false;"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="9 18 15 12 9 6"/></svg></a></li>`;
    pager.innerHTML = html;
}

function goPkgPage(p) { pkgCurrentPage = p; renderPkgPagination(); }

// Init pagination on page load
renderPkgPagination();
function filterPkgTable() {
    pkgCurrentPage = 1;
    renderPkgPagination();
    // Update pkg-count badge jika ada
    const cnt = document.getElementById('pkg-count');
    if (cnt) cnt.textContent = getAllVisiblePkgRows().length;
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>