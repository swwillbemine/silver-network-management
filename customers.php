<?php
// pages/customers.php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

require_once BASE_PATH . '/mikrotik/connection.php';
require_once BASE_PATH . '/mikrotik/ppp.php';

$msg = $_GET['msg'] ?? '';

// ── MIGRATIONS ─────────────────────────────────────────────────────────
// (semua migrasi sudah dijalankan dan dihapus — lihat schema.sql untuk struktur DB terkini)


// ── POST ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $c = $pdo->prepare("SELECT c.*, mk.host, mk.username, mk.password, mk.port
            FROM customers c JOIN packages p ON p.id=c.package_id
            JOIN mikrotiks mk ON mk.id=p.mikrotik_id WHERE c.id=?");
        $c->execute([$_POST['id']]);
        $cust = $c->fetch(PDO::FETCH_ASSOC);
        // Hapus berurutan sesuai FK: payments → billings → customers
        $pdo->prepare("DELETE py FROM payments py JOIN billings b ON b.id=py.billing_id WHERE b.customer_id=?")
            ->execute([$_POST['id']]);
        $pdo->prepare("DELETE FROM billings WHERE customer_id=?")->execute([$_POST['id']]);
        $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$_POST['id']]);
        if ($cust) {
            $client = get_mikrotik_client($cust['host'], $cust['username'], $cust['password'], $cust['port']);
            if ($client) {
                $_raw = get_ppp_secrets($client, ['name' => $cust['pppoe_username']]);
                $secrets = array_values(array_filter($_raw, fn($s) => ($s['name'] ?? '') === $cust['pppoe_username']));
                if (!empty($secrets[0]['.id'])) delete_ppp_secret($client, $secrets[0]['.id']);
            }
        }
        $msg = 'success:Pelanggan berhasil dihapus.';

    } elseif ($action === 'isolate' || $action === 'activate') {
        $newStatus = $action === 'isolate' ? 'isolated' : 'active';
        $pdo->prepare("UPDATE customers SET status=? WHERE id=?")->execute([$newStatus, $_POST['id']]);
        $c = $pdo->prepare("SELECT c.*, mk.host, mk.username, mk.password, mk.port
            FROM customers c JOIN packages p ON p.id=c.package_id
            JOIN mikrotiks mk ON mk.id=p.mikrotik_id WHERE c.id=?");
        $c->execute([$_POST['id']]);
        $cust = $c->fetch(PDO::FETCH_ASSOC);
        if ($cust) {
            $client = get_mikrotik_client($cust['host'], $cust['username'], $cust['password'], $cust['port']);
            if ($client) {
                $_raw_secrets = get_ppp_secrets($client, ['name' => $cust['pppoe_username']]);

                // Safety: filter client-side in case RouterOS ignores the name filter

                $secrets = array_values(array_filter($_raw_secrets, fn($s) => ($s['name'] ?? '') === $cust['pppoe_username']));
                if (!empty($secrets[0]['.id'])) {
                    update_ppp_secret($client, $secrets[0]['.id'],
                        ['disabled' => $newStatus === 'isolated' ? 'true' : 'false']);
                }
            }
        }
        $msg = 'success:Status pelanggan diperbarui.';
    }
}

// ── PAGINATION & FILTER ────────────────────────────────────────────────────
$per_page      = (int)($_GET['per_page'] ?? 25);
$per_page      = in_array($per_page, [10,25,50,100]) ? $per_page : 25;
$page          = max(1, (int)($_GET['page'] ?? 1));
$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');
$mk_filter     = (int)($_GET['mk'] ?? 0);

$where_parts = [];
$params_count = [];
$params_page  = [];

if ($status_filter) {
    $where_parts[] = "c.status = ?";
    $params_count[] = $params_page[] = $status_filter;
}
$billing_type_filter = $_GET['billing_type'] ?? '';
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
    $params_count = array_merge($params_count, [$like,$like,$like,$like]);
    $params_page  = array_merge($params_page,  [$like,$like,$like,$like]);
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM customers c JOIN packages pk ON pk.id=c.package_id JOIN mikrotiks mk ON mk.id=pk.mikrotik_id $where_sql");
$count_stmt->execute($params_count);
$total_rows = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$params_page[] = $per_page;
$params_page[] = $offset;

$customers = $pdo->prepare("
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
$customers->execute($params_page);
$customers = $customers->fetchAll(PDO::FETCH_ASSOC);

$nodes    = $pdo->query("SELECT id, name, COALESCE(owner_contact,'') AS phone FROM nodes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$packages = $pdo->query("SELECT p.id, p.name AS pkg_name,
    p.tx_max_limit, p.rx_max_limit, p.price,
    p.mikrotik_id, m.name AS router_name
    FROM packages p JOIN mikrotiks m ON m.id=p.mikrotik_id
    WHERE p.is_active=1 ORDER BY m.name, p.name")->fetchAll(PDO::FETCH_ASSOC);

$routers = $pdo->query("SELECT id, name FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Stats
// Stats — always respect mk_filter
$stats_join  = "FROM customers c JOIN packages pk ON pk.id=c.package_id JOIN mikrotiks mk ON mk.id=pk.mikrotik_id";
$stats_where = $mk_filter ? "WHERE mk.id=$mk_filter" : '';
$cnt_all      = (int)$pdo->query("SELECT COUNT(*) $stats_join $stats_where")->fetchColumn();
$cnt_active   = (int)$pdo->query("SELECT COUNT(*) $stats_join " . ($mk_filter ? "WHERE mk.id=$mk_filter AND" : 'WHERE') . " c.status='active'")->fetchColumn();
$cnt_isolated = (int)$pdo->query("SELECT COUNT(*) $stats_join " . ($mk_filter ? "WHERE mk.id=$mk_filter AND" : 'WHERE') . " c.status='isolated'")->fetchColumn();
$cnt_free     = (int)$pdo->query("SELECT COUNT(*) $stats_join " . ($mk_filter ? "WHERE mk.id=$mk_filter AND" : 'WHERE') . " c.billing_type='free'")->fetchColumn();

// Group customers by router for batch status check
$by_router = [];
foreach ($customers as $c) {
    $by_router[$c['mk_id']][] = $c['pppoe_username'];
}

[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];
$status_labels = ['active'=>'Aktif','isolated'=>'Isolir','terminated'=>'Terminasi'];
$status_colors = ['active'=>'success','isolated'=>'warning','terminated'=>'danger'];

// Build base URL for pagination links
function paginateUrl(array $merge = []): string {
    $params = array_merge([
        'status'   => $_GET['status']   ?? '',
        'q'        => $_GET['q']        ?? '',
        'mk'       => $_GET['mk']       ?? '',
        'per_page' => $_GET['per_page'] ?? 25,
        'page'     => $_GET['page']     ?? 1,
    ], $merge);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
    return '?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="id">
<?php require_once __DIR__ . '/layout/header.php'; ?>
<style>
/* Fix dropdown clipping in table-responsive */
.table-responsive { overflow: visible !important; }
/* On mobile keep horizontal scroll but allow dropdown overflow */
@media (max-width: 768px) {
  .table-responsive { overflow-x: auto !important; }
  .table-responsive .dropdown-menu { position: fixed !important; }
}
/* Free customer row */
tr.row-free td { background: rgba(32,107,196,.04) !important; }
tr.row-free td .pppoe-username { color: #aaa; }
/* Online dot animation */
.dot-online { display:inline-block;width:8px;height:8px;border-radius:50%;background:#2fb344;animation:pulse 1.5s infinite; }
.dot-offline { display:inline-block;width:8px;height:8px;border-radius:50%;background:#ccc; }
.dot-loading { display:inline-block;width:8px;height:8px;border-radius:50%;background:#f6c23e;animation:pulse 0.8s infinite; }
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
</style>
<body>
<div class="page">
  <?php require_once __DIR__ . '/layout/sidebar.php'; ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle">Manajemen</div>
            <h2 class="page-title">Data Pelanggan</h2>
          </div>
          <div class="col-auto">
            <a href="customer_form.php" class="btn btn-primary">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14M5 12h14"/></svg>
              Tambah Pelanggan
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

        <!-- STAT CARDS -->
        <div class="row g-2 mb-3">
          <?php
          $cards = [
            [''           , $cnt_all,      'Total Pelanggan', 'primary', 'users'],
            ['active'     , $cnt_active,   'Aktif',           'success', 'user-check'],
            ['isolated'   , $cnt_isolated, 'Isolir',          'warning', 'user-x'],
            ['free'       , $cnt_free,     'Tagihan Gratis',  'info',    'gift'],
          ];
          foreach ($cards as [$sf,$cnt,$lbl,$col,$ico]):
          ?>
          <div class="col-6 col-md-3 col-lg">
            <?php $card_url = $sf==='free' ? paginateUrl(['billing_type'=>'free','status'=>'','page'=>1,'mk'=>$mk_filter]) : paginateUrl(['status'=>$sf,'billing_type'=>'','page'=>1,'mk'=>$mk_filter]); ?>
            <a href="<?= $card_url ?>" class="card card-sm text-decoration-none h-100 <?= ($sf==='free' ? ($_GET['billing_type']??'')==='free' : $status_filter===$sf) ? 'border-'.$col.' border-2' : '' ?>">
              <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2">
                  <div class="text-<?= $col ?> fw-bold" style="font-size:2rem;line-height:1"><?= $cnt ?></div>
                  <div class="text-muted small"><?= $lbl ?></div>
                </div>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
          <!-- PPPoE Online — filled by JS after poll -->
          <div class="col-6 col-md-3 col-lg">
            <div class="card card-sm h-100">
              <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2">
                  <div class="text-green fw-bold" style="font-size:2rem;line-height:1;" id="stat-online">—</div>
                  <div class="text-muted small">PPPoE Online</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- SEARCH + PER PAGE -->
        <div class="card">
          <div class="card-header flex-wrap gap-2">
            <h3 class="card-title">
              Pelanggan
              <span class="badge bg-secondary-lt ms-1"><?= $total_rows ?></span>
              <?php if ($search): ?>
              <span class="badge bg-blue-lt ms-1">Cari: "<?= htmlspecialchars($search) ?>"</span>
              <?php endif; ?>
            </h3>
            <div class="card-options d-flex gap-2 flex-wrap">
              <!-- Search form -->
              <form method="GET" class="d-flex gap-1 align-items-center flex-wrap" id="search-form">
                <?php if ($status_filter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
                <input type="hidden" name="per_page" value="<?= $per_page ?>">
                <!-- Router filter -->
                <select name="mk" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
                  <option value="">Semua Router</option>
                  <?php foreach ($routers as $r): ?>
                  <option value="<?= $r['id'] ?>" <?= $mk_filter===$r['id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                       class="form-control form-control-sm" placeholder="Nama / PPPoE / No. HP..."
                       style="width:200px;" id="search-input">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Cari</button>
                <?php if ($search): ?><a href="<?= paginateUrl(['q'=>'','page'=>1]) ?>" class="btn btn-sm btn-ghost-secondary">✕</a><?php endif; ?>
              </form>
              <!-- Per page -->
              <select class="form-select form-select-sm" style="width:auto;" onchange="window.location='<?= paginateUrl(['page'=>1]) ?>&per_page='+this.value">
                <?php foreach ([10,25,50,100] as $pp): ?>
                <option value="<?=$pp?>" <?=$pp===$per_page?'selected':''?>><?=$pp?> per halaman</option>
                <?php endforeach; ?>
              </select>
              <!-- Refresh status -->
              <button class="btn btn-sm btn-outline-primary" onclick="loadAllStatus()" title="Refresh status PPPoE">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M20 11A8.1 8.1 0 0 0 4.5 9M4 5v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg>
                Refresh Status
              </button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr>
                  <th>Nama</th>
                  <th>PPPoE</th>
                  <th>Node / Paket</th>
                  <th>Telepon</th>
                  <th style="width:110px;">Status</th>
                  <th style="width:130px;">PPPoE Online</th>
                  <th style="width:140px;">Traffic Realtime</th>
                  <th class="w-1">Aksi</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($customers as $c): ?>
              <?php $is_free = ($c['billing_type'] ?? 'normal') === 'free'; ?>
              <tr class="<?= $is_free ? 'row-free' : '' ?>">
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($c['name']) ?></div>
                  <?php if (!empty($c['customer_number'])): ?>
                  <div class="text-muted" style="font-size:.7rem;font-family:monospace;"># <?= $c['customer_number'] ?></div>
                  <?php endif; ?>
                  <div class="text-muted small"><?= htmlspecialchars($c['email'] ?? '') ?></div>
                </td>
                <td>
                  <code class="small pppoe-username <?= $is_free ? 'text-muted' : '' ?>"><?= htmlspecialchars($c['pppoe_username']) ?></code>
                </td>
                <td>
                  <div class="small"><?= htmlspecialchars($c['node_name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($c['package_name']) ?></div>
                </td>
                <td class="text-muted small"><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                <td>
                  <?php if ($is_free): ?>
                  <span class="badge bg-<?= $status_colors[$c['status']] ?? 'secondary' ?>-lt">
                    <?= $status_labels[$c['status']] ?? $c['status'] ?>
                  </span>
                  <span class="badge bg-info-lt d-flex align-items-center gap-1" style="width:fit-content;margin-top:3px;" title="Tipe tagihan: Gratis — tidak ditagih">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" fill="none"><path d="M20 12v10H4V12M22 7H2v5h20V7zM12 22V7M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>
                    Gratis
                  </span>
                  <?php else: ?>
                  <span class="badge bg-<?= $status_colors[$c['status']] ?? 'secondary' ?>-lt">
                    <?= $status_labels[$c['status']] ?? $c['status'] ?>
                  </span>
                  <?php endif; ?>
                </td>
                <!-- Online status dot + IP -->
                <td class="pppoe-status-cell" data-username="<?= htmlspecialchars($c['pppoe_username']) ?>" data-mkid="<?= $c['mk_id'] ?>">
                  <span class="dot-loading"></span> <span class="text-muted small">...</span>
                </td>
                <!-- Realtime traffic dari simple queue -->
                <td class="pppoe-traffic-cell" data-username="<?= htmlspecialchars($c['pppoe_username']) ?>" style="min-width:120px;">
                  <span class="text-muted small">—</span>
                </td>
                <td>
                  <!-- Dropdown dengan strategy:fixed agar tidak terpotong card -->
                  <div class="dropdown">
                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="true"
                            aria-expanded="false">Aksi</button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                      <li><a class="dropdown-item fw-semibold" href="customer_detail.php?id=<?= $c['id'] ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Detail
                      </a></li>
                      <?php if ($c['remote_mgmt_port'] ?? null): ?>
                      <li>
                        <a class="dropdown-item text-success access-router-proxy"
                           href="#"
                           data-cid="<?= $c['id'] ?>"
                           data-port="<?= (int)$c['remote_mgmt_port'] ?>">
                          <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10 14a3.5 3.5 0 0 0 5 0l4-4a3.5 3.5 0 0 0-5-5l-.5.5"/><path d="M14 10a3.5 3.5 0 0 0-5 0l-4 4a3.5 3.5 0 0 0 5 5l.5-.5"/></svg>
                          Akses Router <span class="badge bg-blue-lt ms-1">Proxy</span>
                        </a>
                      </li>
                      <?php endif; ?>
                      <li><hr class="dropdown-divider"></li>
                      <li><a class="dropdown-item" href="customer_form.php?id=<?= $c['id'] ?>">Edit</a></li>
                      <?php if ($c['status'] === 'active'): ?>
                      <li><button class="dropdown-item text-warning" onclick="changeStatus(<?= $c['id'] ?>,'isolate')">Isolir</button></li>
                      <?php elseif ($c['status'] === 'isolated'): ?>
                      <li><button class="dropdown-item text-success" onclick="changeStatus(<?= $c['id'] ?>,'activate')">Aktifkan</button></li>
                      <?php else: ?>
                      <li><button class="dropdown-item text-success" onclick="changeStatus(<?= $c['id'] ?>,'activate')">Aktifkan</button></li>
                      <?php endif; ?>
                      <li><hr class="dropdown-divider"></li>
                      <li><button class="dropdown-item text-danger" onclick="deleteCust(<?= $c['id'] ?>,'<?= addslashes($c['name']) ?>')">Hapus</button></li>
                    </ul>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$customers): ?>
              <tr><td colspan="8" class="text-center text-muted py-5">
                <?= $search ? 'Tidak ada hasil untuk "'.htmlspecialchars($search).'".' : 'Belum ada pelanggan.' ?>
              </td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- PAGINATION -->
          <?php if ($total_pages > 1): ?>
          <div class="card-footer d-flex align-items-center justify-content-between">
            <div class="text-muted small">
              Halaman <?= $page ?> dari <?= $total_pages ?> &mdash; <?= $total_rows ?> pelanggan
            </div>
            <ul class="pagination pagination-sm m-0">
              <li class="page-item <?= $page<=1?'disabled':'' ?>">
                <a class="page-link" href="<?= paginateUrl(['page'=>$page-1]) ?>">‹ Prev</a>
              </li>
              <?php
              $range = 2;
              $start = max(1, $page - $range);
              $end   = min($total_pages, $page + $range);
              if ($start > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
              for ($i = $start; $i <= $end; $i++): ?>
              <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="<?= paginateUrl(['page'=>$i]) ?>"><?= $i ?></a>
              </li>
              <?php endfor;
              if ($end < $total_pages): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
              <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
                <a class="page-link" href="<?= paginateUrl(['page'=>$page+1]) ?>">Next ›</a>
              </li>
            </ul>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php require_once __DIR__ . '/layout/footer.php'; ?>
  </div>
</div>


<form method="POST" id="status-form" class="d-none">
  <input type="hidden" name="action" id="status-action">
  <input type="hidden" name="id" id="status-id">
</form>

<div class="modal modal-blur fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="del-id">
        <div class="modal-body">
          <p>Hapus pelanggan <strong id="del-name"></strong>? PPPoE secret di MikroTik juga akan dihapus.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Hapus</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ── FIX: Dropdown strategy:fixed agar tidak terpotong tabel ──────────────
document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(btn => {
    new bootstrap.Dropdown(btn, {
        popperConfig(defaultConfig) {
            return { ...defaultConfig, strategy: 'fixed' };
        }
    });
});

// ── PPPoE status loader ────────────────────────────────────────────────────
// Group cells by router
const routerGroups = {};
document.querySelectorAll('.pppoe-status-cell').forEach(cell => {
    const mk = cell.dataset.mkid;
    if (!routerGroups[mk]) routerGroups[mk] = [];
    routerGroups[mk].push(cell.dataset.username);
});

function fmtBytes(b) {
    b = parseInt(b) || 0;
    if (b >= 1e9) return (b / 1e9).toFixed(2) + ' GB';
    if (b >= 1e6) return (b / 1e6).toFixed(2) + ' MB';
    if (b >= 1e3) return (b / 1e3).toFixed(1) + ' KB';
    return b + ' B';
}

// Format bits/s → "10.5 Mbps" / "512 Kbps" / "800 bps"
function fmtSpeed(bps) {
    bps = parseInt(bps) || 0;
    if (bps >= 1000000000) return (bps / 1000000000).toFixed(1) + ' Gbps';
    if (bps >= 1000000)    return (bps / 1000000).toFixed(1) + ' Mbps';
    if (bps >= 1000)       return (bps / 1000).toFixed(1) + ' Kbps';
    return bps + ' bps';
}

async function loadRouterStatus(mk_id, usernames) {
    try {
        const res  = await fetch('api/pppoe_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ router_id: parseInt(mk_id), usernames })
        });
        const data = await res.json();

        if (data._error) {
            usernames.forEach(u => {
                const sc = document.querySelector(`.pppoe-status-cell[data-username="${CSS.escape(u)}"]`);
                if (sc) sc.innerHTML = '<span class="dot-offline"></span> <span class="text-muted small">Router offline</span>';
                const tc = document.querySelector(`.pppoe-traffic-cell[data-username="${CSS.escape(u)}"]`);
                if (tc) tc.innerHTML = '<span class="text-muted small">—</span>';
            });
            return;
        }

        let onlineCount = 0;
        Object.entries(data).forEach(([username, info]) => {
            const sc = document.querySelector(`.pppoe-status-cell[data-username="${CSS.escape(username)}"]`);
            const tc = document.querySelector(`.pppoe-traffic-cell[data-username="${CSS.escape(username)}"]`);
            if (!sc) return;
            if (info.online) onlineCount++;

            if (info.online) {
                // Status cell: animated dot + IP + uptime
                sc.innerHTML =
                    `<span class="status-dot status-dot-animated bg-green d-inline-block me-1" style="width:8px;height:8px;border-radius:50%;"></span>` +
                    `<span class="small text-success fw-semibold">${info.ip || ''}</span>` +
                    `<div class="text-muted" style="font-size:.7rem;">${info.uptime || ''}</div>`;

                // Traffic cell: ▲ upload (secondary) + ▼ download (primary bold) — persis contoh
                if (tc) {
                    const rx = parseInt(info.rx_rate) || 0;
                    const tx = parseInt(info.tx_rate) || 0;
                    // rx_rate = download ke client (▼), tx_rate = upload dari client (▲)
                    tc.innerHTML =
                        `<div class="text-secondary" style="font-size:.7rem;line-height:1.4;">▲ ${fmtSpeed(tx)}</div>` +
                        `<div class="text-primary fw-bold" style="font-size:.75rem;line-height:1.4;">▼ ${fmtSpeed(rx)}</div>`;
                }
            } else {
                sc.innerHTML = '<span class="dot-offline"></span> <span class="text-muted small">Offline</span>';
                if (tc) tc.innerHTML = '<span class="text-muted small" style="font-size:.7rem;">—</span>';
            }
        });
        // Accumulate across router groups, swap only when all routers done (no flicker)
        window._pendingOnline = (window._pendingOnline || 0) + onlineCount;
        window._doneRouters   = (window._doneRouters   || 0) + 1;
        if (window._doneRouters >= window._pendingRouters) {
            const statEl = document.getElementById('stat-online');
            if (statEl) statEl.textContent = window._pendingOnline;
        }
    } catch(e) {
        usernames.forEach(u => {
            const sc = document.querySelector(`.pppoe-status-cell[data-username="${CSS.escape(u)}"]`);
            if (sc) sc.innerHTML = '<span class="text-muted small">Gagal</span>';
        });
    }
}

function loadAllStatus(isInitial) {
    // Reset pending online count (accumulate across router groups, then swap at end)
    window._pendingOnline = 0;
    window._pendingRouters = Object.keys(routerGroups).length;
    window._doneRouters = 0;
    // On first load only: show loading indicator for cells that haven't loaded yet
    if (isInitial) {
        document.querySelectorAll('.pppoe-status-cell').forEach(cell => {
            cell.innerHTML = '<span class="dot-loading"></span> <span class="text-muted small">...</span>';
        });
    }
    // Subsequent refreshes: update silently in-place — no blink, no reset
    Object.entries(routerGroups).forEach(([mk_id, usernames]) => {
        loadRouterStatus(mk_id, usernames);
    });
}

// Initial load
loadAllStatus(true);

// Auto-refresh every 5 seconds — silent, no loading flicker
let autoRefreshInterval = setInterval(() => loadAllStatus(false), 5000);

// Pause when tab hidden, resume when visible
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearInterval(autoRefreshInterval);
    } else {
        loadAllStatus(false);
        autoRefreshInterval = setInterval(() => loadAllStatus(false), 5000);
    }
});

function changeStatus(id, action) {
    if (!confirm('Konfirmasi perubahan status?')) return;
    document.getElementById('status-id').value     = id;
    document.getElementById('status-action').value = action;
    document.getElementById('status-form').submit();
}

function deleteCust(id, name) {
    document.getElementById('del-id').value = id;
    document.getElementById('del-name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}


// ── Akses Router via SilverProxy ─────────────────────────────────────────────
document.addEventListener('click', function(e) {
    const link = e.target.closest('.access-router-proxy');
    if (!link) return;
    e.preventDefault();
    const cid  = link.dataset.cid;
    const port = link.dataset.port;
    const orig = link.innerHTML;
    link.style.pointerEvents = 'none';
    link.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Membuka...';
    fetch('<?= BASE_URL ?>/api/proxy_token.php?cid=' + cid + '&port=' + port)
        .then(r => r.json())
        .then(d => {
            if (d.url) {
                window.open(d.url, '_blank');
            } else {
                alert('Gagal membuka proxy: ' + (d.error || 'Pelanggan mungkin tidak online.'));
            }
        })
        .catch(() => alert('Gagal terhubung ke server.'))
        .finally(() => { link.style.pointerEvents = ''; link.innerHTML = orig; });
});

</script>
</body>
</html>