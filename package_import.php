<?php
// package_import.php — Import Paket antar Router
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

require_once BASE_PATH . '/mikrotik/connection.php';
require_once BASE_PATH . '/mikrotik/ppp.php';

// Migration
try { $pdo->query("ALTER TABLE packages ADD COLUMN only_one ENUM('default','yes','no') NOT NULL DEFAULT 'default'"); } catch (PDOException $e) {}

$msg = $_GET['msg'] ?? '';

// ── Helpers ─────────────────────────────────────────────────────────────────
function impSpeedToBps(float $v, string $u): int {
    return match($u) { 'G'=>(int)($v*1e9), 'M'=>(int)($v*1e6), 'k'=>(int)($v*1e3), default=>(int)$v };
}
function impBpsToMkRate(int $b): string {
    if ($b<=0) return '0';
    if ($b>=1e9&&$b%1e9===0) return ($b/1e9).'G';
    if ($b>=1e6&&$b%1e6===0) return ($b/1e6).'M';
    if ($b>=1e3&&$b%1e3===0) return ($b/1e3).'k';
    return $b.'';
}
function impBuildRate(int $rx,int $tx,int $rb=0,int $tb=0,int $rt=0,int $tt=0,int $rbt=0,int $tbt=0): string {
    $s = impBpsToMkRate($rx).'/'.impBpsToMkRate($tx);
    if ($rb>0||$tb>0) {
        $s .= ' '.impBpsToMkRate($rb).'/'.impBpsToMkRate($tb);
        $s .= ' '.impBpsToMkRate($rt).'/'.impBpsToMkRate($tt);
        $s .= ' '.$rbt.'/'.$tbt;
    }
    return $s;
}
function impBpsDisplay(int $b): array {
    if ($b<=0) return [0,'M'];
    if ($b>=1e9&&$b%1e9===0) return [(int)($b/1e9),'G'];
    if ($b>=1e6&&$b%1e6===0) return [(int)($b/1e6),'M'];
    if ($b>=1e3&&$b%1e3===0) return [(int)($b/1e3),'k'];
    return [round($b/1e3,2),'k'];
}
function impFmtSpeed(int $b): string {
    [$v,$u] = impBpsDisplay($b);
    return $v.$u;
}
function impComment(string $name): string {
    return '[SNM-PROFILE] '.$name.' | via Silver Network Management | '.date('Y-m-d');
}

// ── POST — Eksekusi Import ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    $target_id = (int)($_POST['target_router_id'] ?? 0);
    $rows      = $_POST['pkg'] ?? [];

    if (!$target_id || empty($rows)) {
        $msg = 'danger:Pilih router tujuan dan minimal 1 paket.';
    } else {
        $rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
        $rq->execute([$target_id]);
        $target_router = $rq->fetch(PDO::FETCH_ASSOC);

        if (!$target_router) {
            $msg = 'danger:Router tujuan tidak ditemukan.';
        } else {
            $client = get_mikrotik_client(
                $target_router['host'], $target_router['username'],
                $target_router['password'], $target_router['port']
            );

            // Fetch existing profiles once
            $_all_profiles = $client ? mikrotik_query($client, '/ppp/profile', 'print', []) : [];

            $ok = 0; $skip = 0; $warn = [];

            foreach ($rows as $idx => $r) {
                if (empty($r['selected'])) continue;

                $name         = trim($r['name']         ?? '');
                $profile_name = trim($r['profile_name'] ?? '');
                $price        = (int)($r['price'] ?? 0);
                $description  = trim($r['description']  ?? '');

                // Speed
                $tx  = impSpeedToBps((float)($r['tx_val']  ?? 0), $r['tx_unit']  ?? 'M');
                $rx  = impSpeedToBps((float)($r['rx_val']  ?? 0), $r['rx_unit']  ?? 'M');
                $txb = impSpeedToBps((float)($r['txb_val'] ?? 0), $r['txb_unit'] ?? 'M');
                $rxb = impSpeedToBps((float)($r['rxb_val'] ?? 0), $r['rxb_unit'] ?? 'M');
                $txt = impSpeedToBps((float)($r['txt_val'] ?? 0), $r['txt_unit'] ?? 'M');
                $rxt = impSpeedToBps((float)($r['rxt_val'] ?? 0), $r['rxt_unit'] ?? 'M');
                $txbt = (int)($r['tx_burst_time'] ?? 0);
                $rxbt = (int)($r['rx_burst_time'] ?? 0);
                $tx_pri = (int)($r['tx_priority'] ?? 8);
                $rx_pri = (int)($r['rx_priority'] ?? 8);

                // Queue / Routing
                $queue_type    = $r['queue_type']    ?? 'default';
                $parent_queue  = trim($r['parent_queue'] ?? '') ?: null;
                $insert_before = trim($r['insert_before'] ?? '') ?: null;
                $only_one      = in_array($r['only_one']??'',['yes','no','default']) ? $r['only_one'] : 'default';
                $local_addr    = trim($r['local_address'] ?? '');
                $remote_pool   = trim($r['remote_pool']   ?? '');
                $dns1          = trim($r['dns1'] ?? '');
                $dns2          = trim($r['dns2'] ?? '');

                if (!$name || !$profile_name) { $skip++; continue; }

                // Cek duplikat nama paket di router tujuan
                $dup = $pdo->prepare("SELECT COUNT(*) FROM packages WHERE mikrotik_id=? AND name=?");
                $dup->execute([$target_id, $name]);
                if ((int)$dup->fetchColumn() > 0 && empty($r['overwrite'])) {
                    $warn[] = "\"$name\" dilewati (sudah ada, overwrite tidak dipilih)";
                    $skip++;
                    continue;
                }

                // ── Simpan ke DB ───────────────────────────────────────────
                if ((int)$dup->fetchColumn() > 0 && !empty($r['overwrite'])) {
                    $pdo->prepare("UPDATE packages SET
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
                    $pdo->prepare("INSERT INTO packages
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

                // ── Push ke MikroTik ───────────────────────────────────────
                if ($client) {
                    $rate   = impBuildRate($tx,$rx,$txb,$rxb,$txt,$rxt,$txbt,$rxbt);
                    $params = ['name'=>$profile_name,'rate-limit'=>$rate,'comment'=>impComment($profile_name)];
                    if ($local_addr)  $params['local-address']  = $local_addr;
                    if ($remote_pool) $params['remote-address'] = $remote_pool;
                    if ($dns1)        $params['dns-server'] = $dns2 ? "$dns1,$dns2" : $dns1;
                    if ($queue_type && $queue_type !== 'default') $params['queue-type'] = $queue_type;
                    if ($parent_queue) $params['parent-queue'] = $parent_queue;
                    $params['only-one'] = $only_one;

                    // Cek apakah profile sudah ada di MikroTik
                    $existing = array_values(array_filter($_all_profiles,
                        fn($p) => ($p['name']??'') === $profile_name));

                    if ($insert_before) $params['insert-queue-before'] = $insert_before;

                    if (!empty($existing[0]['.id'])) {
                        mikrotik_query($client, '/ppp/profile', 'set', $params, $existing[0]['.id']);
                    } else {
                        mikrotik_query($client, '/ppp/profile', 'add', $params);
                        // Refresh cached profiles list for next iteration
                        $_all_profiles = mikrotik_query($client, '/ppp/profile', 'print', []);
                    }
                }
                $ok++;
            }

            $summary = "Import selesai: $ok paket berhasil" . ($skip ? ", $skip dilewati" : '') . '.';
            if ($warn) $summary .= ' | '.implode('; ', $warn);
            $msg = ($ok>0 ? 'success' : 'warning').':'.$summary;
            header('Location: package_import.php?msg='.urlencode($msg));
            exit;
        }
    }
}

// ── Data ────────────────────────────────────────────────────────────────────
$routers  = $pdo->query("SELECT id,name,host FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$all_pkgs = $pdo->query("
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

// Group by router
$pkgs_by_router = [];
foreach ($all_pkgs as $p) {
    $pkgs_by_router[$p['mk_id']][] = $p;
}

$queue_types = ['default','default-small','ethernet-default','wireless-default','hotspot-default',
    'only-hardware-queue','multi-queue-ethernet-default','pcq-upload-default',
    'pcq-download-default','sfq','red','cake','synchronous-default'];

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['',''];
?>
<!doctype html>
<html lang="id">
<?php require_once __DIR__ . '/layout/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<style>
.import-table th { white-space: nowrap; font-size: .75rem; padding: .4rem .5rem; background: var(--tblr-bg-surface-secondary); }
.import-table td { padding: .3rem .4rem; vertical-align: middle; }
.import-table .form-control, .import-table .form-select { font-size: .78rem; padding: .2rem .4rem; min-height: unset; height: 28px; }
.import-table .input-group .form-control { height: 28px; }
.import-table .input-group .form-select  { height: 28px; max-width: 62px; }
.col-check  { width: 36px; }
.col-name   { min-width: 130px; }
.col-profile{ min-width: 130px; }
.col-price  { width: 90px; }
.col-speed  { width: 80px; }
.col-ip     { min-width: 110px; }
.col-pool   { min-width: 130px; }
.col-dns    { min-width: 100px; }
.col-queue  { min-width: 120px; }
.col-small  { min-width: 90px; }
.col-ow     { width: 70px; text-align:center; }
.row-disabled td { opacity: .4; pointer-events: none; }
.row-disabled td:first-child { opacity: 1; pointer-events: all; }
.sticky-col { position: sticky; left: 0; background: var(--tblr-bg-surface); z-index: 2; }
.speed-badge { font-size:.7rem; color:var(--tblr-muted); }
.apply-row td { background: #f0f4ff; font-size:.75rem; }
.apply-row select, .apply-row input { font-size:.75rem !important; }
.section-divider { font-size:.68rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--tblr-muted); padding:.25rem .5rem; background:var(--tblr-bg-surface-tertiary,#f4f6fa); border-top:1px solid var(--tblr-border-color); border-bottom:1px solid var(--tblr-border-color); }
#router-selector { gap: 1rem; }
.router-card { cursor:pointer; border:2px solid transparent; transition:border-color .15s,box-shadow .15s; }
.router-card.selected { border-color: var(--tblr-primary); box-shadow: 0 0 0 3px rgba(var(--tblr-primary-rgb),.12); }
.router-card:hover:not(.selected) { border-color: var(--tblr-border-color-dark); }
#import-area { display:none; }
</style>
<body>
<div class="page">
  <?php require_once __DIR__ . '/layout/sidebar.php'; ?>
  <div class="page-wrapper">

    <!-- Header -->
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col-auto">
            <a href="packages.php" class="btn btn-ghost-secondary btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M15 6l-6 6 6 6"/></svg>
              Kembali
            </a>
          </div>
          <div class="col">
            <div class="page-pretitle">Paket Internet</div>
            <h2 class="page-title">Import Paket antar Router</h2>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">

        <?php if ($msg_text): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible mb-3">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
            <?php if($msg_type==='success'): ?><path d="M5 12l5 5l10-10"/>
            <?php elseif($msg_type==='warning'): ?><path d="M12 9v4m0 4v.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <?php else: ?><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><?php endif; ?>
          </svg>
          <?= htmlspecialchars($msg_text) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ── Step 1: Pilih Router ──────────────────────────────── -->
        <div class="card mb-3" id="step-router">
          <div class="card-header">
            <h3 class="card-title">
              <span class="badge bg-primary me-2">1</span>
              Pilih Router Sumber &amp; Tujuan
            </h3>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Router Sumber <small class="text-muted">(asal paket)</small></label>
                <select id="src-router" class="form-select">
                  <option value="">-- Pilih Router Sumber --</option>
                  <?php foreach ($routers as $r): ?>
                  <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> <span class="text-muted">(<?= $r['host'] ?>)</span></option>
                  <?php endforeach; ?>
                </select>
                <div class="form-hint" id="src-hint"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Router Tujuan <small class="text-muted">(target import)</small></label>
                <select id="dst-router" class="form-select">
                  <option value="">-- Pilih Router Tujuan --</option>
                  <?php foreach ($routers as $r): ?>
                  <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> <span class="text-muted">(<?= $r['host'] ?>)</span></option>
                  <?php endforeach; ?>
                </select>
                <div class="form-hint" id="dst-hint">
                  <span id="dst-loading" class="d-none text-muted">
                    <span class="spinner-border spinner-border-sm me-1"></span>Memuat data router...
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Step 2: Tabel Import ──────────────────────────────── -->
        <div id="import-area">
          <form method="POST" id="import-form">
            <input type="hidden" name="action" value="import">
            <input type="hidden" name="target_router_id" id="f-target-id">

            <div class="card mb-3">
              <div class="card-header d-flex align-items-center gap-2 flex-wrap">
                <h3 class="card-title me-auto">
                  <span class="badge bg-primary me-2">2</span>
                  Pilih &amp; Konfigurasi Paket
                </h3>
                <div class="d-flex gap-2 align-items-center">
                  <button type="button" class="btn btn-ghost-secondary btn-sm" id="btn-check-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M5 12l5 5l10 -10"/></svg>
                    Pilih Semua
                  </button>
                  <button type="button" class="btn btn-ghost-secondary btn-sm" id="btn-uncheck-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M18 6l-12 12M6 6l12 12"/></svg>
                    Kosongkan
                  </button>
                  <span class="text-muted small"><span id="selected-count">0</span> dipilih</span>
                </div>
              </div>

              <!-- Apply to all — card below header -->
              <div class="border-bottom" id="apply-panel">
                <div class="px-3 pt-3 pb-2">
                  <div class="row g-2 align-items-end">
                    <div class="col-12">
                      <span class="text-muted fw-semibold small text-uppercase">Terapkan ke semua yang dipilih</span>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label form-label-sm mb-1">Local Address</label>
                      <input type="text" id="apply-local" class="form-control" placeholder="10.0.0.1">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label form-label-sm mb-1">Remote Pool</label>
                      <select id="apply-pool" class="form-select">
                        <option value="">— tidak diubah —</option>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label form-label-sm mb-1">DNS 1</label>
                      <input type="text" id="apply-dns1" class="form-control" placeholder="10.10.0.1">
                    </div>
                    <div class="col-md-2">
                      <label class="form-label form-label-sm mb-1">DNS 2</label>
                      <input type="text" id="apply-dns2" class="form-control" placeholder="8.8.8.8">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label form-label-sm mb-1">Parent Queue</label>
                      <select id="apply-parent" class="form-select">
                        <option value="">— tidak diubah —</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label form-label-sm mb-1">Insert Before (PPP Profile)</label>
                      <select id="apply-before" class="form-select">
                        <option value="">— tidak diubah —</option>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label form-label-sm mb-1">Only One</label>
                      <select id="apply-onlyone" class="form-select">
                        <option value="">— tidak diubah —</option>
                        <option value="default">default</option>
                        <option value="yes">yes</option>
                        <option value="no">no</option>
                      </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                      <button type="button" class="btn btn-primary w-100" id="btn-apply-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M19 13l-7 7-7-7m14-8l-7 7-7-7"/></svg>
                        Terapkan
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-bordered table-hover import-table mb-0" id="import-table">
                  <thead>
                    <tr>
                      <th class="col-check sticky-col">
                        <input type="checkbox" class="form-check-input" id="chk-master">
                      </th>
                      <th class="col-name">Nama Paket</th>
                      <th class="col-profile">PPP Profile</th>
                      <th class="col-price">Harga (Rp)</th>
                      <th class="col-speed">Speed</th>
                      <th class="col-ip">Local Address</th>
                      <th class="col-pool">Remote Pool</th>
                      <th class="col-dns">DNS 1</th>
                      <th class="col-dns">DNS 2</th>
                      <th class="col-queue">Queue Type</th>
                      <th class="col-queue">Parent Queue</th>
                      <th class="col-queue">Insert Before</th>
                      <th class="col-small">Only One</th>
                      <th class="col-ow" title="Timpa jika nama sudah ada">Overwrite</th>
                    </tr>
                  </thead>
                  <tbody id="import-tbody">
                    <tr><td colspan="14" class="text-center text-muted py-4">Pilih router sumber untuk memuat paket.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Submit -->
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="text-muted small" id="import-summary"></div>
              <button type="submit" class="btn btn-primary" id="btn-import" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1-2-2v-14a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path d="M12 11v6"/><path d="M9.5 13.5l2.5-2.5l2.5 2.5"/></svg>
                Import &amp; Push ke MikroTik
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
    <?php require_once __DIR__ . '/layout/footer.php'; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
// ─────────────────────────────────────────────────────────────────────────────
// DATA FROM PHP
// ─────────────────────────────────────────────────────────────────────────────
const PKGS_BY_ROUTER = <?= json_encode($pkgs_by_router) ?>;
const QUEUE_TYPES    = <?= json_encode($queue_types) ?>;

// ─────────────────────────────────────────────────────────────────────────────
// STATE
// ─────────────────────────────────────────────────────────────────────────────
let dstPools    = [];
let dstQueues   = [];
let dstId       = null;
// TomSelect instances per row — keyed by row idx
const tsParentMap = {};
const tsBeforeMap = {};

// ─────────────────────────────────────────────────────────────────────────────
// ROUTER SELECTION
// ─────────────────────────────────────────────────────────────────────────────
// TomSelect for apply-panel (pool/parent/before) — init after DOM
const tsApplyPool   = new TomSelect('#apply-pool',   {allowEmptyOption:true, maxOptions:200});
const tsApplyParent = new TomSelect('#apply-parent', {allowEmptyOption:true, maxOptions:200});
const tsApplyBefore = new TomSelect('#apply-before', {allowEmptyOption:true, maxOptions:200});

document.getElementById('src-router').addEventListener('change', function() {
    const srcId = this.value;
    const dstId = document.getElementById('dst-router').value;
    if (srcId) updateTable(srcId, dstId);
    updateSrcHint(srcId);
    checkReady();
});

document.getElementById('dst-router').addEventListener('change', async function() {
    dstId = this.value;
    document.getElementById('f-target-id').value = dstId;
    const srcId = document.getElementById('src-router').value;
    if (dstId) {
        setDstHint('<span class="spinner-border spinner-border-sm me-1"></span>Memuat pools &amp; queues...');
        await loadDstData(dstId);
        setDstHint('✓ Data router dimuat');
        if (srcId) updateTable(srcId, dstId);
    }
    checkReady();
});

function setDstHint(html) {
    document.getElementById('dst-hint').innerHTML = `<span class="text-muted">${html}</span>`;
}

function updateSrcHint(srcId) {
    const pkgs = PKGS_BY_ROUTER[srcId] || [];
    document.getElementById('src-hint').textContent = pkgs.length ? `${pkgs.length} paket tersedia` : 'Tidak ada paket di router ini.';
}

function checkReady() {
    const src = document.getElementById('src-router').value;
    const dst = document.getElementById('dst-router').value;
    const ready = src && dst && src !== dst;
    document.getElementById('import-area').style.display = ready ? 'block' : 'none';
}

// ─────────────────────────────────────────────────────────────────────────────
// LOAD DESTINATION ROUTER DATA (pools + queues)
// ─────────────────────────────────────────────────────────────────────────────
async function loadDstData(rid) {
    try {
        const [poolsResp, queuesResp] = await Promise.all([
            fetch(`api/pools.php?router_id=${rid}`).then(r => r.json()),
            fetch(`api/queues.php?router_id=${rid}`).then(r => r.json()),
        ]);
        dstPools  = Array.isArray(poolsResp) ? poolsResp : [];
        dstQueues = (queuesResp.queues || []);
    } catch(e) {
        dstPools  = [];
        dstQueues = [];
        console.error('loadDstData error', e);
    }
    refreshApplyDropdowns();
}

function refreshApplyDropdowns() {
    // Pool — via TomSelect
    tsApplyPool.clearOptions();
    tsApplyPool.addOption({value:'', text:'— tidak diubah —'});
    dstPools.forEach(p => tsApplyPool.addOption({value:p.name, text:`${p.name} (${p.ranges})`}));
    tsApplyPool.refreshOptions(false);

    // Parent queue (from queues) — via TomSelect
    tsApplyParent.clearOptions();
    tsApplyParent.addOption({value:'', text:'— tidak diubah —'});
    dstQueues.forEach(q => tsApplyParent.addOption({value:q.name, text:`${q.name} [${q.type}]`}));
    tsApplyParent.refreshOptions(false);

    // Insert before — same as parent queue (queue list)
    tsApplyBefore.clearOptions();
    tsApplyBefore.addOption({value:'', text:'— tidak diubah —'});
    dstQueues.forEach(q => tsApplyBefore.addOption({value:q.name, text:`${q.name} [${q.type}]`}));
    tsApplyBefore.refreshOptions(false);

    // Refresh TomSelect instances in table rows
    Object.values(tsParentMap).forEach(ts => {
        const prev = ts.getValue();
        ts.clearOptions();
        ts.addOption({value:'', text:'— none —'});
        dstQueues.forEach(q => ts.addOption({value:q.name, text:`${q.name} [${q.type}]`}));
        ts.refreshOptions(false);
        if (prev) ts.setValue(prev);
    });
    Object.values(tsBeforeMap).forEach(ts => {
        const prev = ts.getValue();
        ts.clearOptions();
        ts.addOption({value:'', text:'— append (paling akhir) —'});
        dstQueues.forEach(q => ts.addOption({value:q.name, text:`${q.name} [${q.type}]`}));
        ts.refreshOptions(false);
        if (prev) ts.setValue(prev);
    });

    // Refresh plain pool selects in rows
    document.querySelectorAll('select.dst-pool').forEach(s =>
        refreshSelect(s, dstPools, p => [p.name, `${p.name} (${p.ranges})`])
    );
    // dst-queue plain selects removed — handled via tsParentMap/tsBeforeMap
}

function refreshSelect(sel, data, mapper) {
    const prev = sel.value;
    while (sel.options.length > 1) sel.remove(1);
    data.forEach(d => { const [v,t] = mapper(d); sel.add(new Option(t, v)); });
    sel.value = prev;
}

// ─────────────────────────────────────────────────────────────────────────────
// BUILD TABLE
// ─────────────────────────────────────────────────────────────────────────────
function updateTable(srcId, dstId) {
    const pkgs  = PKGS_BY_ROUTER[srcId] || [];
    const tbody = document.getElementById('import-tbody');

    if (!pkgs.length) {
        tbody.innerHTML = '<tr><td colspan="14" class="text-center text-muted py-4">Tidak ada paket di router sumber ini.</td></tr>';
        updateSelectedCount();
        return;
    }

    // Destroy old TomSelect instances before rebuild
    Object.keys(tsParentMap).forEach(k => { tsParentMap[k].destroy(); delete tsParentMap[k]; });
    Object.keys(tsBeforeMap).forEach(k => { tsBeforeMap[k].destroy(); delete tsBeforeMap[k]; });
    tbody.innerHTML = pkgs.map((p, i) => buildRow(p, i)).join('');

    // Init TomSelect for parent_queue and insert_before per row
    pkgs.forEach((p, i) => {
        // Parent queue — from dstQueues
        const elParent = document.getElementById(`ts-parent-${i}`);
        if (elParent && !tsParentMap[i]) {
            elParent.innerHTML = '<option value="">— none —</option>';
            dstQueues.forEach(q => {
                const o = new Option(`${q.name} [${q.type}]`, q.name);
                if (q.name === (p.parent_queue||'')) o.selected = true;
                elParent.add(o);
            });
            tsParentMap[i] = new TomSelect(elParent, {
                allowEmptyOption: true,
                maxOptions: 200,
                placeholder: '— none —',
                onChange: v => {
                    const h = document.getElementById(`h-parent-${i}`);
                    if (h) h.value = v || '';
                },
            });
            if (p.parent_queue) { tsParentMap[i].setValue(p.parent_queue); }
        }
        // Insert before — same source as parent_queue (queue list)
        const elBefore = document.getElementById(`ts-before-${i}`);
        if (elBefore && !tsBeforeMap[i]) {
            elBefore.innerHTML = '<option value="">— append (paling akhir) —</option>';
            dstQueues.forEach(q => {
                const lbl = `${q.name} [${q.type}]${q.rate && q.rate!=='-' ? ' — '+q.rate : ''}`;
                const o = new Option(lbl, q.name);
                if (q.name === (p.queue_insert_before||'')) o.selected = true;
                elBefore.add(o);
            });
            tsBeforeMap[i] = new TomSelect(elBefore, {
                allowEmptyOption: true,
                maxOptions: 200,
                placeholder: '— append (paling akhir) —',
                onChange: v => {
                    const h = document.getElementById(`h-before-${i}`);
                    if (h) h.value = v || '';
                },
            });
            if (p.queue_insert_before) { tsBeforeMap[i].setValue(p.queue_insert_before); }
        }
    });

    // Attach events
    document.querySelectorAll('.row-chk').forEach(chk => {
        chk.addEventListener('change', function() {
            toggleRowDisabled(this.closest('tr'), !this.checked);
            updateSelectedCount();
        });
    });
    document.getElementById('chk-master').addEventListener('change', function() {
        document.querySelectorAll('.row-chk').forEach(c => {
            c.checked = this.checked;
            toggleRowDisabled(c.closest('tr'), !this.checked);
        });
        updateSelectedCount();
    });

    // Re-populate dst dropdowns in rows
    if (dstPools.length || dstQueues.length) refreshApplyDropdowns();
    updateSelectedCount();
}

function speedLabel(p) {
    return `↓${fmt(p.rx_max_limit)} / ↑${fmt(p.tx_max_limit)}`;
}
function fmt(b) {
    b = parseInt(b) || 0;
    if (b>=1e9&&b%1e9===0) return (b/1e9)+'G';
    if (b>=1e6&&b%1e6===0) return (b/1e6)+'M';
    if (b>=1e3&&b%1e3===0) return (b/1e3)+'k';
    return b;
}
function qTypeOpts(sel) {
    return QUEUE_TYPES.map(t => `<option value="${t}"${sel===t?' selected':''}>${t}</option>`).join('');
}
function poolOpts(val) {
    const def = `<option value="">— tanpa pool —</option>`;
    return def + dstPools.map(p => `<option value="${p.name}"${val===p.name?' selected':''}>${p.name} (${p.ranges})</option>`).join('');
}
function queueOpts(val, placeholder) {
    return `<option value="">${placeholder}</option>` +
        dstQueues.map(q => `<option value="${q.name}"${val===q.name?' selected':''}>${q.name} [${q.type}]</option>`).join('');
}
function onlyOneOpts(val) {
    return ['default','yes','no'].map(v => `<option value="${v}"${val===v?' selected':''}>${v}</option>`).join('');
}

function buildRow(p, i) {
    const n = `pkg[${i}]`;
    return `
    <tr class="row-disabled" data-idx="${i}">
      <td class="sticky-col col-check">
        <input type="checkbox" class="form-check-input row-chk" name="${n}[selected]" value="1">
      </td>
      <td class="col-name">
        <input type="text" name="${n}[name]" class="form-control" value="${esc(p.name)}" required>
        <input type="hidden" name="${n}[description]" value="${esc(p.description||'')}">
        <input type="hidden" name="${n}[tx_priority]"    value="${p.tx_priority||8}">
        <input type="hidden" name="${n}[rx_priority]"    value="${p.rx_priority||8}">
        <input type="hidden" name="${n}[tx_burst_time]"  value="${p.tx_burst_time||0}">
        <input type="hidden" name="${n}[rx_burst_time]"  value="${p.rx_burst_time||0}">
        ${speedHiddens(n, p)}
      </td>
      <td class="col-profile">
        <input type="text" name="${n}[profile_name]" class="form-control" value="${esc(p.mikrotik_profile_name)}">
      </td>
      <td class="col-price">
        <input type="number" name="${n}[price]" class="form-control" value="${p.price||0}" min="0">
      </td>
      <td class="col-speed">
        <div class="speed-badge">${speedLabel(p)}</div>
      </td>
      <td class="col-ip">
        <input type="text" name="${n}[local_address]" class="form-control" value="${esc(p.local_address||'')}" placeholder="10.0.0.1">
      </td>
      <td class="col-pool">
        <select name="${n}[remote_pool]" class="form-select dst-pool">
          ${poolOpts(p.remote_pool||'')}
        </select>
      </td>
      <td class="col-dns">
        <input type="text" name="${n}[dns1]" class="form-control" value="${esc(p.dns_server1||'')}" placeholder="DNS 1">
      </td>
      <td class="col-dns">
        <input type="text" name="${n}[dns2]" class="form-control" value="${esc(p.dns_server2||'')}" placeholder="DNS 2">
      </td>
      <td class="col-queue">
        <select name="${n}[queue_type]" class="form-select">
          ${qTypeOpts(p.queue_type||'default')}
        </select>
      </td>
      <td class="col-queue">
        <input type="hidden" name="${n}[parent_queue]" id="h-parent-${i}" value="${esc(p.parent_queue||'')}">
        <select id="ts-parent-${i}" class="form-select">
          <option value="">${p.parent_queue||''}</option>
        </select>
      </td>
      <td class="col-queue">
        <input type="hidden" name="${n}[insert_before]" id="h-before-${i}" value="${esc(p.queue_insert_before||'')}">
        <select id="ts-before-${i}" class="form-select">
          <option value="">${p.queue_insert_before||''}</option>
        </select>
      </td>
      <td class="col-small">
        <select name="${n}[only_one]" class="form-select">
          ${onlyOneOpts(p.only_one||'default')}
        </select>
      </td>
      <td class="col-ow">
        <input type="checkbox" class="form-check-input" name="${n}[overwrite]" value="1" title="Timpa jika sudah ada">
      </td>
    </tr>`;
}

function speedHiddens(n, p) {
    // Store speed as raw bps in hidden — the PHP handler reads val+unit
    // Use bps values split into val+unit format
    const fields = [
        ['tx_val','tx_unit',p.tx_max_limit],
        ['rx_val','rx_unit',p.rx_max_limit],
        ['txb_val','txb_unit',p.tx_burst_limit||0],
        ['rxb_val','rxb_unit',p.rx_burst_limit||0],
        ['txt_val','txt_unit',p.tx_burst_threshold||0],
        ['rxt_val','rxt_unit',p.rx_burst_threshold||0],
    ];
    return fields.map(([vk,uk,bps]) => {
        const [v,u] = bpsToDisplay(parseInt(bps)||0);
        return `<input type="hidden" name="${n}[${vk}]" value="${v}">
                <input type="hidden" name="${n}[${uk}]" value="${u}">`;
    }).join('');
}

function bpsToDisplay(b) {
    if (b<=0) return [0,'M'];
    if (b>=1e9&&b%1e9===0) return [b/1e9,'G'];
    if (b>=1e6&&b%1e6===0) return [b/1e6,'M'];
    if (b>=1e3&&b%1e3===0) return [b/1e3,'k'];
    return [Math.round(b/1e3),'k'];
}

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function toggleRowDisabled(tr, disabled) {
    tr.classList.toggle('row-disabled', disabled);
}

// ─────────────────────────────────────────────────────────────────────────────
// SELECT ALL / COUNT
// ─────────────────────────────────────────────────────────────────────────────
function updateSelectedCount() {
    const total    = document.querySelectorAll('.row-chk').length;
    const selected = document.querySelectorAll('.row-chk:checked').length;
    document.getElementById('selected-count').textContent = selected;
    document.getElementById('btn-import').disabled = selected === 0;
    document.getElementById('import-summary').textContent = selected
        ? `${selected} dari ${total} paket akan diimport ke router tujuan.`
        : 'Pilih minimal 1 paket untuk melanjutkan.';

    const master = document.getElementById('chk-master');
    if (master) {
        master.indeterminate = selected > 0 && selected < total;
        master.checked = selected === total && total > 0;
    }
}

document.getElementById('btn-check-all').addEventListener('click', () => {
    document.querySelectorAll('.row-chk').forEach(c => { c.checked = true; toggleRowDisabled(c.closest('tr'), false); });
    const m = document.getElementById('chk-master'); if(m) m.checked = true;
    updateSelectedCount();
});
document.getElementById('btn-uncheck-all').addEventListener('click', () => {
    document.querySelectorAll('.row-chk').forEach(c => { c.checked = false; toggleRowDisabled(c.closest('tr'), true); });
    const m = document.getElementById('chk-master'); if(m) m.checked = false;
    updateSelectedCount();
});

// ─────────────────────────────────────────────────────────────────────────────
// APPLY TO ALL
// ─────────────────────────────────────────────────────────────────────────────
document.getElementById('btn-apply-all').addEventListener('click', () => {
    const local    = document.getElementById('apply-local').value.trim();
    const pool     = tsApplyPool.getValue();
    const dns1     = document.getElementById('apply-dns1').value.trim();
    const dns2     = document.getElementById('apply-dns2').value.trim();
    const parent   = tsApplyParent.getValue();
    const before   = tsApplyBefore.getValue();
    const onlyone  = document.getElementById('apply-onlyone').value;

    document.querySelectorAll('.row-chk:checked').forEach(chk => {
        const tr  = chk.closest('tr');
        const idx = parseInt(tr.dataset.idx);
        if (local)   setInput(tr, '[local_address]', local);
        if (pool)    setSelect(tr, '[remote_pool]', pool);
        if (dns1)    setInput(tr, '[dns1]', dns1);
        if (dns2)    setInput(tr, '[dns2]', dns2);
        // TomSelect setValue also triggers onChange → updates hidden input
        if (parent) {
            if (tsParentMap[idx]) tsParentMap[idx].setValue(parent);
            else { const h = tr.querySelector(`input[name$="[parent_queue]"]`); if(h) h.value = parent; }
        }
        if (before) {
            if (tsBeforeMap[idx]) tsBeforeMap[idx].setValue(before);
            else { const h = tr.querySelector(`input[name$="[insert_before]"]`); if(h) h.value = before; }
        }
        if (onlyone) setSelect(tr, '[only_one]', onlyone);
    });
});

function setInput(tr, nameSuffix, val) {
    const el = tr.querySelector(`input[name$="${nameSuffix}"]`);
    if (el) el.value = val;
}
function setSelect(tr, nameSuffix, val) {
    const el = tr.querySelector(`select[name$="${nameSuffix}"]`);
    if (el) el.value = val;
}

// ─────────────────────────────────────────────────────────────────────────────
// FORM SUBMIT GUARD
// ─────────────────────────────────────────────────────────────────────────────
document.getElementById('import-form').addEventListener('submit', function(e) {
    const selected = document.querySelectorAll('.row-chk:checked').length;
    if (!selected) {
        e.preventDefault();
        alert('Pilih minimal 1 paket untuk diimport.');
        return;
    }
    if (!document.getElementById('f-target-id').value) {
        e.preventDefault();
        alert('Pilih router tujuan terlebih dahulu.');
        return;
    }
    document.getElementById('btn-import').disabled = true;
    document.getElementById('btn-import').innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span>Mengimport...';
});
</script>
</body>
</html>