<?php
// pages/import_pppoe.php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

$msg = '';
$routers  = $pdo->query("SELECT * FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$nodes    = $pdo->query("SELECT id, name FROM nodes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$packages = $pdo->query("SELECT p.*,mk.name AS router_name FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id ORDER BY mk.name,p.name")->fetchAll(PDO::FETCH_ASSOC);

function speedToBps(float $val, string $unit): int {
    return match($unit) {
        'G' => (int)($val * 1_000_000_000),
        'M' => (int)($val * 1_000_000),
        'k' => (int)($val * 1_000),
        default => (int)$val,
    };
}

function parseRateLimit(string $rate): array {
    // MikroTik PPP rate-limit format: "rx/tx [burst-rx/burst-tx [thr-rx/thr-tx [time-rx/time-tx]]]"
    // rx = router receives = client uploads → maps to our tx_max_limit (upload)
    // tx = router sends    = client downloads → maps to our rx_max_limit (download)
    // Returns: [tx_bps (upload), rx_bps (download)] to match our DB column naming
    $rate = trim($rate);
    if (!$rate || $rate === '0' || $rate === '-') return [0, 0];

    // Take only the first pair — ignore burst params separated by spaces
    $first_pair = explode(' ', $rate)[0];
    $parts = explode('/', $first_pair);
    $mk_rx = trim($parts[0] ?? '0'); // MikroTik rx = client upload
    $mk_tx = trim($parts[1] ?? $mk_rx); // MikroTik tx = client download

    $toVal = function(string $s): int {
        $s = trim($s);
        if (!$s || $s === '0') return 0;
        if (preg_match('/^([\d.]+)([kKmMgG]?)$/', $s, $m)) {
            $unit = strtolower($m[2]);
            $mul  = match($unit) { 'g' => 1_000_000_000, 'm' => 1_000_000, 'k' => 1_000, default => 1 };
            return (int)round(floatval($m[1]) * $mul);
        }
        return 0;
    };

    // Return [upload_bps, download_bps] = [tx_max_limit, rx_max_limit] in our DB
    return [$toVal($mk_rx), $toVal($mk_tx)];
}

// ── POST: Import PPPoE Secrets ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_secrets') {
    $router_id  = (int)$_POST['router_id'];
    $node_id    = (int)$_POST['node_id'];
    $package_id = (int)$_POST['package_id'];
    $selected   = $_POST['secrets'] ?? [];

    if (!$selected) { $msg = 'warning:Tidak ada secret yang dipilih.'; goto end_action; }

    $rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
    $rq->execute([$router_id]);
    $router = $rq->fetch(PDO::FETCH_ASSOC);
    $client = $router ? get_mikrotik_client($router['host'],$router['username'],$router['password'],(int)$router['port'],(bool)$router['api_ssl']) : null;

    // Fetch all secrets at once for efficiency
    $all_secrets = [];
    if ($client) {
        $raw = mikrotik_query($client, '/ppp/secret', 'print');
        foreach ($raw as $s) {
            if (!empty($s['name'])) $all_secrets[$s['name']] = $s;
        }
    }

    $imported = 0; $skipped = 0;
    foreach ($selected as $sname) {
        $exists = $pdo->prepare("SELECT id FROM customers WHERE pppoe_username=?");
        $exists->execute([$sname]);
        if ($exists->fetch()) { $skipped++; continue; }

        $s   = $all_secrets[$sname] ?? [];
        $pass = $s['password'] ?? '';
        $raw_comment = $s['comment'] ?? '';
        // Strip our own comment prefix
        $cust_name = preg_replace('/^\[SNM-SECRET\]\s*/', '', $raw_comment);
        $cust_name = preg_replace('/\s*\|.*$/', '', $cust_name);
        $cust_name = trim($cust_name) ?: $sname;

        $pdo->prepare("INSERT INTO customers
            (node_id,package_id,name,pppoe_username,pppoe_password,status,installation_date)
            VALUES (?,?,?,?,?,'active',CURDATE())")
            ->execute([$node_id, $package_id, $cust_name, $sname, $pass]);
        $imported++;
    }
    $msg = "success:Import PPPoE selesai. {$imported} pelanggan diimpor, {$skipped} dilewati (sudah ada).";
    goto end_action;
}

// ── POST: Import PPP Profiles ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_profiles') {
    $router_id = (int)$_POST['router_id'];
    $selected  = $_POST['profiles'] ?? [];

    if (!$selected) { $msg = 'warning:Tidak ada profile yang dipilih.'; goto end_action; }

    $rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
    $rq->execute([$router_id]);
    $router = $rq->fetch(PDO::FETCH_ASSOC);
    if (!$router) { $msg = 'danger:Router tidak ditemukan.'; goto end_action; }

    $client = get_mikrotik_client($router['host'],$router['username'],$router['password'],(int)$router['port'],(bool)$router['api_ssl']);
    $all_profiles = [];
    if ($client) {
        $raw = mikrotik_query($client, '/ppp/profile', 'print');
        foreach ($raw as $p) {
            if (!empty($p['name'])) $all_profiles[$p['name']] = $p;
        }
    }

    $imported = 0; $skipped = 0;
    foreach ($selected as $pname) {
        // Check if profile name already in packages for this router
        $exists = $pdo->prepare("SELECT id FROM packages WHERE mikrotik_id=? AND mikrotik_profile_name=?");
        $exists->execute([$router_id, $pname]);
        if ($exists->fetch()) { $skipped++; continue; }

        $p = $all_profiles[$pname] ?? [];
        $rate_limit = $p['rate-limit'] ?? '0/0';
        [$tx_bps, $rx_bps] = parseRateLimit($rate_limit);
        // tx_bps = upload limit, rx_bps = download limit (already corrected in parseRateLimit)

        // Price and name from POST per-profile
        $pkg_name = trim($_POST['pkg_name'][$pname] ?? $pname);
        $price    = (int)($_POST['pkg_price'][$pname] ?? 0);

        $pdo->prepare("INSERT INTO packages
            (mikrotik_id, name, price, tx_max_limit, rx_max_limit,
             tx_burst_limit, rx_burst_limit, tx_burst_threshold, rx_burst_threshold,
             tx_burst_time, rx_burst_time, tx_priority, rx_priority,
             queue_type, mikrotik_profile_name, is_active, description)
            VALUES (?,?,?,?,?,0,0,0,0,0,0,8,8,'default',?,1,?)")
            ->execute([
                $router_id, $pkg_name, $price, $tx_bps, $rx_bps,
                $pname,
                'Diimport dari MikroTik — '.date('Y-m-d')
            ]);
        $imported++;
    }
    $msg = "success:Import Profile selesai. {$imported} paket diimpor, {$skipped} dilewati (sudah ada).";
    goto end_action;
}

end_action:

// ── AJAX: Fetch PPPoE secrets ──────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'secrets') {
    header('Content-Type: application/json');
    $rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
    $rq->execute([(int)($_GET['router_id'] ?? 0)]);
    $router = $rq->fetch(PDO::FETCH_ASSOC);
    if (!$router) { echo json_encode(['error'=>'Router not found']); exit; }

    $client = get_mikrotik_client($router['host'],$router['username'],$router['password'],(int)$router['port'],(bool)$router['api_ssl']);
    if (!$client) { echo json_encode(['error'=>'Tidak bisa terhubung ke router: '.$router['host']]); exit; }

    $raw = mikrotik_query($client, '/ppp/secret', 'print');
    $existing = array_flip($pdo->query("SELECT pppoe_username FROM customers")->fetchAll(PDO::FETCH_COLUMN));

    $secrets = [];
    foreach ($raw as $s) {
        $name = $s['name'] ?? ''; if (!$name) continue;
        // ── Only PPPoE (or "any" which defaults to pppoe) ──
        $service = strtolower(trim($s['service'] ?? 'any'));
        // Skip non-pppoe service types explicitly
        if (in_array($service, ['pptp', 'l2tp', 'sstp', 'ovpn', 'ethernet'])) continue;
        // Only allow pppoe or any (empty = any in MikroTik)
        if ($service !== 'pppoe' && $service !== 'any' && $service !== '') continue;

        $raw_comment = $s['comment'] ?? '';
        $display_name = preg_replace('/^\[SNM-SECRET\]\s*/', '', $raw_comment);
        $display_name = trim(preg_replace('/\s*\|.*$/', '', $display_name));

        $secrets[] = [
            'name'     => $name,
            'profile'  => $s['profile'] ?? 'default',
            'service'  => $service,
            'comment'  => $display_name,
            'disabled' => ($s['disabled'] ?? 'false') === 'true',
            'imported' => isset($existing[$name]),
        ];
    }
    echo json_encode(['secrets' => $secrets, 'total' => count($secrets)]);
    exit;
}

// ── AJAX: Fetch PPP Profiles ───────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'profiles') {
    header('Content-Type: application/json');
    $router_id = (int)($_GET['router_id'] ?? 0);
    $rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
    $rq->execute([$router_id]);
    $router = $rq->fetch(PDO::FETCH_ASSOC);
    if (!$router) { echo json_encode(['error'=>'Router not found']); exit; }

    $client = get_mikrotik_client($router['host'],$router['username'],$router['password'],(int)$router['port'],(bool)$router['api_ssl']);
    if (!$client) { echo json_encode(['error'=>'Tidak bisa terhubung']); exit; }

    $raw = mikrotik_query($client, '/ppp/profile', 'print');

    // Profiles already in DB for this router
    $in_db = array_flip($pdo->prepare("SELECT mikrotik_profile_name FROM packages WHERE mikrotik_id=?")
        ->execute([$router_id]) ? $pdo->prepare("SELECT mikrotik_profile_name FROM packages WHERE mikrotik_id=?")->execute([$router_id]) && true ? [] : [] : []);
    $stmt = $pdo->prepare("SELECT mikrotik_profile_name FROM packages WHERE mikrotik_id=?");
    $stmt->execute([$router_id]);
    $in_db = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    $profiles = [];
    // Skip MikroTik built-in/system profiles
    $skip = ['default','default-encryption','default-dialin'];
    foreach ($raw as $p) {
        $name = $p['name'] ?? ''; if (!$name || in_array($name,$skip)) continue;
        $is_app = str_contains($p['comment'] ?? '', '[SNM-PROFILE]');
        $profiles[] = [
            'name'        => $name,
            'rate_limit'  => $p['rate-limit'] ?? '-',
            'local_addr'  => $p['local-address'] ?? '',
            'remote_pool' => $p['remote-address'] ?? '',
            'comment'     => $p['comment'] ?? '',
            'is_app'      => $is_app,
            'in_db'       => isset($in_db[$name]),
        ];
    }
    echo json_encode(['profiles' => $profiles, 'router_id' => $router_id]);
    exit;
}

[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];
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
            <h2 class="page-title">Import PPPoE dari MikroTik</h2>
          </div>
          <div class="col-auto">
            <!-- Tab toggle -->
            <div class="btn-group">
              <button class="btn btn-primary active" id="tab-secrets-btn" onclick="switchTab('secrets')">PPPoE Secret</button>
              <button class="btn btn-outline-primary" id="tab-profiles-btn" onclick="switchTab('profiles')">PPP Profile</button>
            </div>
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

        <!-- STEP 1: ROUTER SELECT -->
        <div class="card mb-3">
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-5">
                <label class="form-label fw-semibold">Router MikroTik</label>
                <select id="pick-router" class="form-select">
                  <option value="">-- Pilih Router --</option>
                  <?php foreach ($routers as $r): ?>
                  <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> — <?= $r['host'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-auto">
                <button class="btn btn-primary" id="btn-load" disabled>
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M20 11A8.1 8.1 0 0 0 4.5 9M4 5v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg>
                  Muat Data
                </button>
              </div>
              <div class="col-auto">
                <span id="load-status" class="text-muted small"></span>
              </div>
            </div>
          </div>
        </div>

        <!-- ══ TAB: PPPoE SECRETS ══════════════════════════════════════════ -->
        <div id="tab-secrets">
          <div class="d-none" id="secrets-panel">
            <form method="POST" id="form-secrets">
              <input type="hidden" name="action" value="import_secrets">
              <input type="hidden" name="router_id" id="sec-router-id">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">
                    PPPoE Secret
                    <span class="badge bg-blue-lt ms-1" id="secret-count">0</span>
                    <span class="badge bg-green-lt ms-1" id="secret-new-count"></span>
                  </h3>
                  <div class="card-options d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="selectSec(true)">Pilih Semua</button>
                    <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="selectSec(false)">Batal Semua</button>
                    <button type="button" class="btn btn-sm btn-outline-green" onclick="selectOnlyNew()">Hanya Baru</button>
                    <input type="text" id="filter-sec" class="form-control form-control-sm" placeholder="Filter..." style="width:140px;">
                  </div>
                </div>
                <div class="table-responsive" style="max-height:380px;overflow-y:auto;">
                  <table class="table table-sm table-vcenter card-table">
                    <thead class="sticky-top bg-white">
                      <tr>
                        <th style="width:36px;"><input type="checkbox" id="check-all-sec" class="form-check-input"></th>
                        <th>Username (PPPoE)</th>
                        <th>Profile</th>
                        <th>Nama dari Comment</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody id="secrets-tbody"></tbody>
                  </table>
                </div>

                <div class="card-body border-top">
                  <div class="fw-semibold mb-3">Assign ke Node &amp; Paket</div>
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label class="form-label required">Node</label>
                      <select name="node_id" id="f-node" class="form-select" required>
                        <option value="">-- Pilih Node --</option>
                        <?php foreach ($nodes as $n): ?>
                        <option value="<?= $n['id'] ?>"><?= htmlspecialchars($n['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label required">Paket Internet</label>
                      <select name="package_id" id="f-pkg" class="form-select" required>
                        <option value="">-- Pilih Paket --</option>
                        <?php foreach ($packages as $p): ?>
                        <option value="<?= $p['id'] ?>">[<?= htmlspecialchars($p['router_name']) ?>] <?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                      <button type="submit" class="btn btn-success w-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/><polyline points="7 11 12 16 17 11"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
                        Import ke Database
                      </button>
                    </div>
                  </div>
                  <div class="alert alert-info mt-3 mb-0 small">
                    Hanya PPPoE secret yang ditampilkan. Password &amp; nama pelanggan diambil otomatis dari MikroTik.
                    Secret yang sudah ada di database dilewati.
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- ══ TAB: PPP PROFILES ═══════════════════════════════════════════ -->
        <div id="tab-profiles" class="d-none">
          <div class="d-none" id="profiles-panel">
            <form method="POST" id="form-profiles">
              <input type="hidden" name="action" value="import_profiles">
              <input type="hidden" name="router_id" id="pro-router-id">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">
                    PPP Profile
                    <span class="badge bg-blue-lt ms-1" id="profile-count">0</span>
                  </h3>
                  <div class="card-options d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="selectPro(true)">Pilih Semua</button>
                    <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="selectPro(false)">Batal Semua</button>
                    <input type="text" id="filter-pro" class="form-control form-control-sm" placeholder="Filter..." style="width:140px;">
                  </div>
                </div>
                <div class="table-responsive" style="max-height:460px;overflow-y:auto;">
                  <table class="table table-sm table-vcenter card-table">
                    <thead class="sticky-top bg-white">
                      <tr>
                        <th style="width:36px;"><input type="checkbox" id="check-all-pro" class="form-check-input"></th>
                        <th>Profile Name</th>
                        <th>Rate Limit</th>
                        <th>Local / Pool</th>
                        <th style="min-width:180px;">Nama Paket <small class="text-muted">(di app)</small></th>
                        <th style="min-width:130px;">Harga/bln (Rp)</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody id="profiles-tbody"></tbody>
                  </table>
                </div>
                <div class="card-footer">
                  <button type="submit" class="btn btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/><polyline points="7 11 12 16 17 11"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
                    Import Profile yang Dipilih ke Database
                  </button>
                  <span class="text-muted small ms-3">Profile yang sudah ada di database akan dilewati otomatis.</span>
                </div>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
    <?php require_once __DIR__ . '/layout/footer.php'; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
new TomSelect('#pick-router', {allowEmptyOption:true});
new TomSelect('#f-node',      {allowEmptyOption:true});
new TomSelect('#f-pkg',       {allowEmptyOption:true});

let activeTab = 'secrets';

function switchTab(tab) {
    activeTab = tab;
    document.getElementById('tab-secrets').classList.toggle('d-none', tab !== 'secrets');
    document.getElementById('tab-profiles').classList.toggle('d-none', tab !== 'profiles');
    document.getElementById('tab-secrets-btn').classList.toggle('active', tab === 'secrets');
    document.getElementById('tab-profiles-btn').classList.toggle('active', tab === 'profiles');
    document.getElementById('tab-secrets-btn').classList.toggle('btn-primary', tab === 'secrets');
    document.getElementById('tab-secrets-btn').classList.toggle('btn-outline-primary', tab !== 'secrets');
    document.getElementById('tab-profiles-btn').classList.toggle('btn-primary', tab === 'profiles');
    document.getElementById('tab-profiles-btn').classList.toggle('btn-outline-primary', tab !== 'profiles');
}

document.getElementById('pick-router').addEventListener('change', function() {
    document.getElementById('btn-load').disabled = !this.value;
    document.getElementById('secrets-panel').classList.add('d-none');
    document.getElementById('profiles-panel').classList.add('d-none');
});

document.getElementById('btn-load').addEventListener('click', () => {
    if (activeTab === 'secrets') loadSecrets();
    else loadProfiles();
});

// ── PPPoE Secrets ──────────────────────────────────────────────────────────
async function loadSecrets() {
    const rid = document.getElementById('pick-router').value;
    const statusEl = document.getElementById('load-status');
    statusEl.textContent = 'Menghubungi router...';

    try {
        const res  = await fetch(`import_pppoe.php?ajax=secrets&router_id=${rid}`);
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { statusEl.textContent = 'Error JSON: '+text.substring(0,120); return; }
        if (data.error) { statusEl.textContent = '❌ '+data.error; return; }

        const secrets = data.secrets || [];
        const newCount = secrets.filter(s => !s.imported).length;
        document.getElementById('secret-count').textContent = secrets.length;
        document.getElementById('secret-new-count').textContent = newCount + ' baru';
        document.getElementById('sec-router-id').value = rid;
        renderSecrets(secrets);
        document.getElementById('secrets-panel').classList.remove('d-none');
        statusEl.textContent = `${secrets.length} PPPoE secret ditemukan, ${newCount} belum diimport.`;
    } catch(e) { document.getElementById('load-status').textContent = 'Gagal: '+e.message; }
}

function renderSecrets(secrets) {
    const tbody = document.getElementById('secrets-tbody');
    tbody.innerHTML = '';
    secrets.forEach(s => {
        const appMade = s.comment && s.comment.includes('[SNM-SECRET]');
        tbody.innerHTML += `<tr class="${s.imported?'table-secondary':''}">
            <td><input type="checkbox" name="secrets[]" value="${escH(s.name)}" class="form-check-input sec-check"
                ${s.imported?'disabled':(!s.disabled?'checked':'')}></td>
            <td class="fw-semibold">${escH(s.name)}</td>
            <td><code class="small">${escH(s.profile)}</code></td>
            <td class="text-muted small">${escH(s.comment||'-')}</td>
            <td>
                ${s.imported?'<span class="badge bg-secondary-lt">Sudah ada</span>':''}
                ${s.disabled&&!s.imported?'<span class="badge bg-warning-lt">Disabled</span>':''}
                ${!s.imported&&!s.disabled?'<span class="badge bg-green-lt">Baru</span>':''}
                ${appMade?'<span class="badge bg-blue-lt ms-1">APP</span>':''}
            </td>
        </tr>`;
    });
}

// ── PPP Profiles ───────────────────────────────────────────────────────────
async function loadProfiles() {
    const rid = document.getElementById('pick-router').value;
    const statusEl = document.getElementById('load-status');
    statusEl.textContent = 'Memuat profile...';

    try {
        const res  = await fetch(`import_pppoe.php?ajax=profiles&router_id=${rid}`);
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { statusEl.textContent = 'Error JSON: '+text.substring(0,120); return; }
        if (data.error) { statusEl.textContent = '❌ '+data.error; return; }

        const profiles = data.profiles || [];
        document.getElementById('profile-count').textContent = profiles.length;
        document.getElementById('pro-router-id').value = rid;
        renderProfiles(profiles);
        document.getElementById('profiles-panel').classList.remove('d-none');
        const newCount = profiles.filter(p => !p.in_db).length;
        statusEl.textContent = `${profiles.length} profile ditemukan, ${newCount} belum di database.`;
    } catch(e) { document.getElementById('load-status').textContent = 'Gagal: '+e.message; }
}

function renderProfiles(profiles) {
    const tbody = document.getElementById('profiles-tbody');
    tbody.innerHTML = '';
    profiles.forEach(p => {
        tbody.innerHTML += `<tr class="${p.in_db?'table-secondary':''}">
            <td><input type="checkbox" name="profiles[]" value="${escH(p.name)}" class="form-check-input pro-check"
                ${p.in_db?'disabled':'checked'}></td>
            <td class="fw-semibold">
                ${p.is_app?'<span class="badge bg-blue-lt me-1" title="Dibuat oleh app">APP</span>':''}
                ${escH(p.name)}
            </td>
            <td><code>${escH(p.rate_limit)}</code></td>
            <td class="text-muted small">${escH(p.local_addr||'-')} / ${escH(p.remote_pool||'-')}</td>
            <td>
                ${p.in_db
                    ? `<span class="text-muted small">Sudah ada</span>`
                    : `<input type="text" name="pkg_name[${escH(p.name)}]" class="form-control form-control-sm"
                        value="${escH(p.name)}" placeholder="Nama paket">`
                }
            </td>
            <td>
                ${p.in_db
                    ? `<span class="text-muted small">—</span>`
                    : `<input type="number" name="pkg_price[${escH(p.name)}]" class="form-control form-control-sm"
                        value="0" min="0" placeholder="0">`
                }
            </td>
            <td>
                ${p.in_db?'<span class="badge bg-secondary-lt">Sudah ada</span>':'<span class="badge bg-green-lt">Baru</span>'}
            </td>
        </tr>`;
    });
}

// ── Helpers ────────────────────────────────────────────────────────────────
function escH(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function selectSec(v) { document.querySelectorAll('.sec-check:not(:disabled)').forEach(c=>c.checked=v); }
function selectPro(v) { document.querySelectorAll('.pro-check:not(:disabled)').forEach(c=>c.checked=v); }
function selectOnlyNew() { document.querySelectorAll('.sec-check:not(:disabled)').forEach(c=>c.checked=true); }

document.getElementById('check-all-sec').addEventListener('change', function() {
    document.querySelectorAll('.sec-check:not(:disabled)').forEach(c=>c.checked=this.checked);
});
document.getElementById('check-all-pro').addEventListener('change', function() {
    document.querySelectorAll('.pro-check:not(:disabled)').forEach(c=>c.checked=this.checked);
});

document.getElementById('filter-sec').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#secrets-tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
document.getElementById('filter-pro').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#profiles-tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
</body>
</html>