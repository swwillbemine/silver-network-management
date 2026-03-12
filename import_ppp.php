<?php
// pages/import_ppp.php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/mikrotik/connection.php';
require_once __DIR__ . '/mikrotik/ppp.php';
requireLogin();

$msg = '';
$routers    = $pdo->query("SELECT * FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$nodes      = $pdo->query("SELECT id, name FROM nodes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$packages   = $pdo->query("SELECT p.*,mk.name AS router_name FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id ORDER BY mk.name,p.name")->fetchAll(PDO::FETCH_ASSOC);

// ── POST: import selected secrets ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_secrets') {
    $router_id  = (int)$_POST['router_id'];
    $node_id    = (int)$_POST['node_id'];
    $package_id = (int)$_POST['package_id'];
    $selected   = $_POST['secrets'] ?? [];

    if (!$selected) { $msg = 'warning:Tidak ada secret yang dipilih.'; goto end_import; }

    $router = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?")->execute([$router_id]);
    $router = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?")->execute([$router_id]) ? $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?")->execute([$router_id]) : null;
    $rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
    $rq->execute([$router_id]);
    $router = $rq->fetch(PDO::FETCH_ASSOC);

    $pkg = $pdo->prepare("SELECT * FROM packages WHERE id=?");
    $pkg->execute([$package_id]);
    $package = $pkg->fetch(PDO::FETCH_ASSOC);

    $client = $router ? get_mikrotik_client($router['host'],$router['username'],$router['password'],(int)$router['port'],(bool)$router['api_ssl']) : null;

    $imported = 0; $skipped = 0;
    foreach ($selected as $secret_name) {
        // Check if already exists in DB
        $exists = $pdo->prepare("SELECT id FROM customers WHERE pppoe_username=?");
        $exists->execute([$secret_name]);
        if ($exists->fetch()) { $skipped++; continue; }

        // Get secret details from MikroTik
        $secret_detail = [];
        if ($client) {
            $secrets = mikrotik_query($client, '/ppp/secret', 'print');
            foreach ($secrets as $s) {
                if (($s['name'] ?? '') === $secret_name) {
                    $secret_detail = $s;
                    break;
                }
            }
        }

        $ppp_pass     = $secret_detail['password'] ?? '';
        $cust_name    = $secret_detail['comment'] ?? $secret_name;
        // Remove [SNM-...] prefix if present
        $cust_name    = preg_replace('/^\[SNM-SECRET\]\s*/','', $cust_name);
        $cust_name    = preg_replace('/\s*\|.*$/','',$cust_name);

        $pdo->prepare("INSERT INTO customers
            (node_id,package_id,name,pppoe_username,pppoe_password,status,installation_date)
            VALUES (?,?,?,?,?,'active',CURDATE())")
            ->execute([$node_id, $package_id, $cust_name ?: $secret_name, $secret_name, $ppp_pass]);
        $imported++;
    }
    $msg = "success:Import selesai. {$imported} pelanggan diimpor, {$skipped} dilewati (sudah ada).";
}
end_import:

// ── AJAX: fetch secrets from router ───────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'secrets') {
    header('Content-Type: application/json');
    $router_id = (int)($_GET['router_id'] ?? 0);
    $rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
    $rq->execute([$router_id]);
    $router = $rq->fetch(PDO::FETCH_ASSOC);
    if (!$router) { echo json_encode(['error'=>'Router not found']); exit; }

    $client = get_mikrotik_client($router['host'],$router['username'],$router['password'],(int)$router['port'],(bool)$router['api_ssl']);
    if (!$client) { echo json_encode(['error'=>'Cannot connect to router']); exit; }

    $raw = mikrotik_query($client, '/ppp/secret', 'print');

    // Get already-imported usernames
    $existing = $pdo->query("SELECT pppoe_username FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    $existing_set = array_flip($existing);

    $secrets = [];
    foreach ($raw as $s) {
        $name = $s['name'] ?? '';
        if (!$name) continue;
        $secrets[] = [
            'name'     => $name,
            'profile'  => $s['profile'] ?? '-',
            'service'  => $s['service'] ?? '-',
            'comment'  => $s['comment'] ?? '',
            'disabled' => ($s['disabled'] ?? 'false') === 'true',
            'imported' => isset($existing_set[$name]),
        ];
    }
    echo json_encode(['secrets' => $secrets, 'total' => count($secrets)]);
    exit;
}

// ── AJAX: fetch profiles from router ──────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'profiles') {
    header('Content-Type: application/json');
    $router_id = (int)($_GET['router_id'] ?? 0);
    $rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
    $rq->execute([$router_id]);
    $router = $rq->fetch(PDO::FETCH_ASSOC);
    if (!$router) { echo json_encode(['error'=>'Router not found']); exit; }

    $client = get_mikrotik_client($router['host'],$router['username'],$router['password'],(int)$router['port'],(bool)$router['api_ssl']);
    if (!$client) { echo json_encode(['error'=>'Cannot connect']); exit; }

    $raw = mikrotik_query($client, '/ppp/profile', 'print');
    echo json_encode(['profiles' => $raw]);
    exit;
}

[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];
?>
<!doctype html>
<html lang="id">
<?php require_once __DIR__ . '/layout/header.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<body>
<div class="page">
  <?php require_once __DIR__ . '/layout/sidebar.php'; ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle">Pelanggan</div>
            <h2 class="page-title">Import PPP dari MikroTik</h2>
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

        <div class="row g-3">

          <!-- STEP 1: SELECT ROUTER + LOAD -->
          <div class="col-12">
            <div class="card">
              <div class="card-header"><h3 class="card-title">Langkah 1 — Pilih Router & Muat Data</h3></div>
              <div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-md-5">
                    <label class="form-label">Router MikroTik</label>
                    <select id="pick-router" class="form-select">
                      <option value="">-- Pilih Router --</option>
                      <?php foreach ($routers as $r): ?>
                      <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= $r['host'] ?>)</option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-auto d-flex gap-2">
                    <button class="btn btn-primary" id="btn-load-secrets" disabled>
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M20 11A8.1 8.1 0 0 0 4.5 9M4 5v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg>
                      Muat PPP Secret
                    </button>
                    <button class="btn btn-outline-secondary" id="btn-load-profiles" disabled>Muat PPP Profile</button>
                  </div>
                  <div class="col-auto">
                    <span id="load-status" class="text-muted small"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- PPP PROFILES PANEL -->
          <div class="col-12 d-none" id="profiles-panel">
            <div class="card">
              <div class="card-header"><h3 class="card-title">PPP Profile di Router</h3><span class="badge bg-blue-lt ms-2" id="profile-count">0</span></div>
              <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table">
                  <thead><tr><th>Nama</th><th>Rate Limit</th><th>Local Addr</th><th>Remote Pool</th><th>Comment</th></tr></thead>
                  <tbody id="profile-table"><tr><td colspan="5" class="text-center text-muted">Memuat...</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- PPP SECRETS + IMPORT FORM -->
          <div class="col-12 d-none" id="secrets-panel">
            <form method="POST">
              <input type="hidden" name="action" value="import_secrets">
              <input type="hidden" name="router_id" id="form-router-id">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">PPP Secret Ditemukan <span class="badge bg-blue-lt ms-1" id="secret-count">0</span></h3>
                  <div class="card-options d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="selectAll(true)">Pilih Semua</button>
                    <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="selectAll(false)">Batal Semua</button>
                    <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="selectOnlyNew()">Hanya Baru</button>
                    <input type="text" id="filter-secrets" class="form-control form-control-sm" placeholder="Filter..." style="width:160px;">
                  </div>
                </div>
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                  <table class="table table-sm table-vcenter card-table" id="secrets-table">
                    <thead class="sticky-top bg-white">
                      <tr>
                        <th style="width:36px;"><input type="checkbox" id="check-all" class="form-check-input"></th>
                        <th>Username</th><th>Profile</th><th>Service</th><th>Comment</th><th>Status</th>
                      </tr>
                    </thead>
                    <tbody id="secrets-tbody"></tbody>
                  </table>
                </div>

                <!-- Step 2: assign metadata -->
                <div class="card-body border-top">
                  <div class="fw-bold mb-3">Langkah 2 — Assign ke Node &amp; Paket</div>
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
                      <button type="submit" class="btn btn-success w-100" id="btn-import">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/><polyline points="7 11 12 16 17 11"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
                        Import ke Database
                      </button>
                    </div>
                  </div>
                  <div class="alert alert-info mt-3 mb-0 small">
                    Secret yang sudah ada di database akan dilewati otomatis.
                    Password diambil dari MikroTik. Nama pelanggan diambil dari kolom <em>comment</em> secret (jika ada).
                  </div>
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

let allSecrets = [];

document.getElementById('pick-router').addEventListener('change', function() {
  const en = !!this.value;
  document.getElementById('btn-load-secrets').disabled = !en;
  document.getElementById('btn-load-profiles').disabled = !en;
  document.getElementById('secrets-panel').classList.add('d-none');
  document.getElementById('profiles-panel').classList.add('d-none');
});

async function loadData(type) {
  const rid = document.getElementById('pick-router').value;
  if (!rid) return;
  const statusEl = document.getElementById('load-status');
  statusEl.textContent = 'Menghubungi router...';

  try {
    const res = await fetch(`import_ppp.php?ajax=${type}&router_id=${rid}`);
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(e) {
      statusEl.textContent = 'Error: ' + text.substring(0,100);
      return;
    }
    if (data.error) { statusEl.textContent = 'Error: ' + data.error; return; }

    if (type === 'secrets') {
      allSecrets = data.secrets || [];
      renderSecrets(allSecrets);
      document.getElementById('secret-count').textContent = allSecrets.length;
      document.getElementById('form-router-id').value = rid;
      document.getElementById('secrets-panel').classList.remove('d-none');
      statusEl.textContent = `${allSecrets.length} secret ditemukan.`;
    } else {
      const profiles = data.profiles || [];
      const tbody = document.getElementById('profile-table');
      tbody.innerHTML = '';
      profiles.forEach(p => {
        const imported = p.comment && p.comment.includes('[SNM-PROFILE]');
        tbody.innerHTML += `<tr class="${imported?'table-success':''}">
          <td class="fw-semibold">${p.name||'-'}</td>
          <td><code>${p['rate-limit']||'-'}</code></td>
          <td class="text-muted small">${p['local-address']||'-'}</td>
          <td class="text-muted small">${p['remote-address']||'-'}</td>
          <td class="text-muted small">${(p.comment||'').replace(/\[SNM-PROFILE\]/g,'<span class="badge bg-green-lt me-1">APP</span>')}</td>
        </tr>`;
      });
      document.getElementById('profile-count').textContent = profiles.length;
      document.getElementById('profiles-panel').classList.remove('d-none');
      statusEl.textContent = `${profiles.length} profile ditemukan.`;
    }
  } catch(e) {
    document.getElementById('load-status').textContent = 'Gagal: ' + e.message;
  }
}

function renderSecrets(secrets) {
  const tbody = document.getElementById('secrets-tbody');
  tbody.innerHTML = '';
  secrets.forEach(s => {
    const imported = s.imported;
    const disabled = s.disabled;
    const comment_clean = (s.comment||'').replace(/\[SNM-SECRET\]/,'').replace(/\|.*$/,'').trim();
    tbody.innerHTML += `<tr class="${imported?'table-secondary':''}">
      <td><input type="checkbox" name="secrets[]" value="${s.name}" class="form-check-input secret-check"
          ${imported?'disabled':''}
          ${!imported && !disabled ? 'checked' : ''}></td>
      <td class="fw-semibold">${s.name}</td>
      <td><code class="small">${s.profile}</code></td>
      <td class="text-muted small">${s.service}</td>
      <td class="text-muted small">${comment_clean||'-'}</td>
      <td>
        ${imported?'<span class="badge bg-secondary-lt">Sudah ada</span>':''}
        ${disabled&&!imported?'<span class="badge bg-secondary-lt">Disabled</span>':''}
        ${!imported&&!disabled?'<span class="badge bg-green-lt">Baru</span>':''}
      </td>
    </tr>`;
  });
}

document.getElementById('btn-load-secrets').addEventListener('click', () => loadData('secrets'));
document.getElementById('btn-load-profiles').addEventListener('click', () => loadData('profiles'));

document.getElementById('check-all').addEventListener('change', function() {
  document.querySelectorAll('.secret-check:not(:disabled)').forEach(cb => cb.checked = this.checked);
});

function selectAll(checked) { document.querySelectorAll('.secret-check:not(:disabled)').forEach(cb=>cb.checked=checked); }
function selectOnlyNew() {
  document.querySelectorAll('.secret-check').forEach(cb => {
    if (!cb.disabled) cb.checked = true;
  });
}

document.getElementById('filter-secrets').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#secrets-tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>