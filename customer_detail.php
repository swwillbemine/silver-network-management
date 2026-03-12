<?php
// customer_detail.php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: customers.php'); exit; }

$stmt = $pdo->prepare("
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
if (!$c) { header('Location: customers.php'); exit; }

// Billing history
$billings = $pdo->prepare("
    SELECT b.*, py.amount_paid, py.method, py.notes AS pay_notes, b.paid_at AS paid_time
    FROM billings b
    LEFT JOIN payments py ON py.billing_id = b.id
    WHERE b.customer_id = ?
    ORDER BY b.period DESC LIMIT 24");
$billings->execute([$id]);
$bills = $billings->fetchAll(PDO::FETCH_ASSOC);

// Live PPPoE session + queue traffic from MikroTik
$live_session = null;
$queue_data   = null;

if ($c['mk_host'] && $c['pppoe_username']) {
    $client = @get_mikrotik_client($c['mk_host'], $c['mk_user'], $c['mk_pass'], (int)$c['mk_port'], (bool)$c['api_ssl']);
    if ($client) {
        // 1. /ppp/active — status, IP, uptime
        foreach (mikrotik_query($client, '/ppp/active', 'print') as $s) {
            if (($s['name'] ?? '') === $c['pppoe_username']) {
                $live_session = $s;
                break;
            }
        }

        // 2. /queue/simple — rate (realtime bps) + bytes (total session)
        // rate format:  "tx_bps/rx_bps"   tx=router→client=download, rx=client→router=upload
        // bytes format: "tx_bytes/rx_bytes"
        if ($live_session) {
            $uname = $c['pppoe_username'];
            foreach (mikrotik_query($client, '/queue/simple', 'print') as $q) {
                $qn = trim($q['name'] ?? '', '<>');
                if (strtolower($qn) === strtolower($uname) ||
                    strtolower($qn) === strtolower("pppoe-$uname")) {
                    $parse = fn($v) => array_map('intval', explode('/', $v) + ['0','0']);
                    [$tx_rate,  $rx_rate]  = $parse($q['rate']      ?? '0/0');
                    [$tx_bytes, $rx_bytes] = $parse($q['bytes']      ?? '0/0');
                    [$tx_limit, $rx_limit] = $parse($q['max-limit']  ?? '0/0');
                    $queue_data = [
                        'dl_rate'  => $tx_rate,   // download to client (bps)
                        'ul_rate'  => $rx_rate,   // upload from client (bps)
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

function bitsToHuman($bits) {
    $bits = (int)$bits;
    if ($bits >= 1e9) return round($bits/1e9,1).' Gbps';
    if ($bits >= 1e6) return round($bits/1e6,1).' Mbps';
    if ($bits >= 1e3) return round($bits/1e3,1).' Kbps';
    return $bits.' bps';
}
function fmtBytes($b) {
    $b = (int)$b;
    if ($b >= 1e9) return round($b/1e9,2).' GB';
    if ($b >= 1e6) return round($b/1e6,2).' MB';
    if ($b >= 1e3) return round($b/1e3,1).' KB';
    return $b.' B';
}

$status_colors = ['active'=>'green','isolated'=>'yellow','terminated'=>'red'];
$status_sc = $status_colors[$c['status']] ?? 'secondary';
$wan_ip    = $live_session['address'] ?? null;
$mgmt_port = (int)($c['remote_mgmt_port'] ?? 80) ?: 80;

// ── Generate proxy token (HMAC, valid 1 jam) ──────────────────────────────────
function getProxySecret(): string {
    global $pdo;
    static $s = null;
    if ($s) return $s;
    try {
        $r = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='proxy_secret' LIMIT 1")->fetch();
        if ($r && strlen($r['setting_value'] ?? '') >= 32) { $s = $r['setting_value']; return $s; }
    } catch (Exception $e) {}
    $generated = bin2hex(random_bytes(32));
    try {
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('proxy_secret',?)
                       ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$generated]);
    } catch (Exception $e) {}
    $s = $generated;
    return $s;
}

function makeProxyUrl(int $cid, string $ip, int $port, string $path = '/'): string {
    $bucket = floor(time() / 3600);
    $token  = hash_hmac('sha256', "{$cid}|{$ip}|{$port}|{$bucket}", getProxySecret());
    $base   = BASE_URL . '/router_proxy.php/' . $cid . '/' . $token;
    return $base . (str_starts_with($path, '/') ? $path : '/' . $path);
}

$proxy_url = ($wan_ip && $mgmt_port) ? makeProxyUrl((int)$c['id'], $wan_ip, $mgmt_port) : null;
?>
<!doctype html>
<html lang="id">
<?php require_once __DIR__ . '/layout/header.php'; ?>
<body>
<div class="page">
  <?php require_once __DIR__ . '/layout/sidebar.php'; ?>
  <div class="page-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle"><a href="customers.php" class="text-muted">Pelanggan</a> / Detail</div>
            <h2 class="page-title d-flex align-items-center gap-2">
              <?= htmlspecialchars($c['name']) ?>
              <span class="badge bg-<?= $status_sc ?>-lt"><?= ucfirst($c['status']) ?></span>
            </h2>
          </div>
          <div class="col-auto d-flex gap-2">
            <a href="customers.php" class="btn btn-ghost-secondary">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M15 6l-6 6l6 6"/></svg>
              Kembali
            </a>
            <a href="customer_form.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M7 7H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-1"/><path d="M20.385 6.585a2.1 2.1 0 0 0-2.97-2.97L9 12v3h3l8.385-8.415z"/></svg>
              Edit
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <div class="row g-3">

          <!-- ══════════════ LEFT COLUMN ══════════════ -->
          <div class="col-lg-4">

            <!-- LIVE SESSION CARD -->
            <div class="card mb-3" style="border:2px solid <?= $live_session ? 'var(--tblr-green)' : 'var(--tblr-border-color)' ?>;">
              <div class="card-header">
                <h3 class="card-title">
                  <?php if ($live_session): ?>
                  <span class="status-dot status-dot-animated bg-green me-2"></span>
                  <?php else: ?>
                  <span class="status-dot bg-secondary me-2"></span>
                  <?php endif; ?>
                  Sesi PPPoE
                </h3>
                <div class="card-options">
                  <span class="badge bg-<?= $live_session ? 'green' : 'secondary' ?>">
                    <?= $live_session ? 'Online' : 'Offline' ?>
                  </span>
                </div>
              </div>

              <?php if ($live_session): ?>
              <div class="card-body p-0">

                <!-- IP + Uptime -->
                <div class="row g-0 border-bottom text-center">
                  <div class="col-6 py-3 border-end">
                    <div class="text-muted small mb-1">IP Address</div>
                    <code class="fs-5"><?= htmlspecialchars($live_session['address'] ?? '-') ?></code>
                  </div>
                  <div class="col-6 py-3">
                    <div class="text-muted small mb-1">Uptime</div>
                    <div class="fw-semibold"><?= htmlspecialchars($live_session['uptime'] ?? '-') ?></div>
                  </div>
                </div>

                <!-- Download / Upload speed -->
                <div class="row g-0 border-bottom text-center">
                  <div class="col-6 py-3 border-end">
                    <div class="text-muted small mb-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm text-primary me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14m-7-7l7 7l7-7"/></svg>
                      Download
                    </div>
                    <?php if ($queue_data): ?>
                    <div class="fw-bold text-primary fs-4" style="line-height:1.1;"><?= bitsToHuman($queue_data['dl_rate']) ?></div>
                    <div class="text-muted small"><?= fmtBytes($queue_data['dl_bytes']) ?> sesi ini</div>
                    <?php else: ?>
                    <div class="text-muted">—</div>
                    <?php endif; ?>
                  </div>
                  <div class="col-6 py-3">
                    <div class="text-muted small mb-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm text-secondary me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 19V5m7 7l-7-7l-7 7"/></svg>
                      Upload
                    </div>
                    <?php if ($queue_data): ?>
                    <div class="fw-bold text-secondary fs-4" style="line-height:1.1;"><?= bitsToHuman($queue_data['ul_rate']) ?></div>
                    <div class="text-muted small"><?= fmtBytes($queue_data['ul_bytes']) ?> sesi ini</div>
                    <?php else: ?>
                    <div class="text-muted">—</div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Limit + MAC -->
                <div class="px-3 py-2">
                  <?php if ($queue_data && ($queue_data['dl_limit'] || $queue_data['ul_limit'])): ?>
                  <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">Limit paket</span>
                    <span class="badge bg-secondary-lt">
                      <?= bitsToHuman($queue_data['dl_limit']) ?> / <?= bitsToHuman($queue_data['ul_limit']) ?>
                    </span>
                  </div>
                  <?php endif; ?>
                  <div class="d-flex justify-content-between small">
                    <span class="text-muted">MAC / Caller ID</span>
                    <code class="small"><?= htmlspecialchars($live_session['caller-id'] ?? '-') ?></code>
                  </div>
                </div>

              </div><!-- /card-body -->

              <?php else: ?>
              <div class="card-body text-center text-muted py-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2" width="32" height="32" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>Tidak ada sesi aktif</div>
                <div class="text-muted small mt-1">PPPoE: <code><?= htmlspecialchars($c['pppoe_username']) ?></code></div>
              </div>
              <?php endif; ?>
            </div>

            <!-- CREDENTIALS -->
            <div class="card mb-3">
              <div class="card-header"><h3 class="card-title">Kredensial PPPoE</h3></div>
              <div class="card-body">
                <div class="mb-3">
                  <div class="text-muted small mb-1">Username</div>
                  <div class="d-flex align-items-center gap-2">
                    <code id="pppoe-user"><?= htmlspecialchars($c['pppoe_username']) ?></code>
                    <button class="btn btn-sm btn-ghost-secondary py-0" onclick="copyText('pppoe-user')">Salin</button>
                  </div>
                </div>
                <div>
                  <div class="text-muted small mb-1">Password</div>
                  <div class="d-flex align-items-center gap-2">
                    <code id="pppoe-pass" style="filter:blur(4px);" onmouseover="this.style.filter=''" onmouseout="this.style.filter='blur(4px)'"><?= htmlspecialchars($c['pppoe_password']) ?></code>
                    <button class="btn btn-sm btn-ghost-secondary py-0" onclick="copyText('pppoe-pass')">Salin</button>
                  </div>
                  <small class="text-muted">Hover untuk tampilkan</small>
                </div>
              </div>
            </div>



            <!-- REMOTE ACCESS VIA PROXY -->
            <?php if ($wan_ip): ?>
            <div class="card mb-3">
              <div class="card-header d-flex align-items-center">
                <h3 class="card-title me-auto">Akses Remote Router</h3>
                <?php if ($proxy_url): ?>
                <span class="badge bg-green-lt">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0-6 0"/><path d="M12 12m-8 0a8 8 0 1 0 16 0a8 8 0 1 0-16 0"/></svg>
                  Via Proxy Aman
                </span>
                <?php endif; ?>
              </div>
              <div class="card-body d-flex flex-column gap-2">

                <?php if ($proxy_url): ?>
                <!-- Tombol proxy — akses lewat server, tidak langsung ke IP router -->
                <a href="<?= htmlspecialchars($proxy_url) ?>" target="_blank" class="btn btn-success">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10 14a3.5 3.5 0 0 0 5 0l4-4a3.5 3.5 0 0 0-5-5l-.5.5"/><path d="M14 10a3.5 3.5 0 0 0-5 0l-4 4a3.5 3.5 0 0 0 5 5l.5-.5"/></svg>
                  Buka Router via Proxy<?= $c['remote_mgmt_port'] ? ' (Port '.((int)$c['remote_mgmt_port']).')' : '' ?>
                </a>

                <?php else: ?>
                <!-- Sesi aktif tapi port belum diset — form manual, masih via proxy -->
                <div class="input-group">
                  <input type="number" id="manual-port" class="form-control" placeholder="Port" min="1" max="65535" value="80">
                  <button class="btn btn-success" onclick="openProxyManualPort()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10 14a3.5 3.5 0 0 0 5 0l4-4a3.5 3.5 0 0 0-5-5l-.5.5"/><path d="M14 10a3.5 3.5 0 0 0-5 0l-4 4a3.5 3.5 0 0 0 5 5l.5-.5"/></svg>
                    Buka via Proxy
                  </button>
                </div>
                <div class="form-hint">
                  Port mgmt belum tersimpan.
                  <a href="customer_form.php?id=<?= $c['id'] ?>" class="text-muted">Edit pelanggan</a> untuk menyimpannya.
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center pt-1">
                  <div class="text-muted small">
                    WAN IP: <code><?= htmlspecialchars($wan_ip) ?></code>
                    &nbsp;&bull;&nbsp;
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm text-green" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0-6 0"/><path d="M12 12m-8 0a8 8 0 1 0 16 0a8 8 0 1 0-16 0"/></svg>
                    Akses tidak langsung (via server)
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

          </div><!-- /col-lg-4 -->

          <!-- ══════════════ RIGHT COLUMN ══════════════ -->
          <div class="col-lg-8">

            <!-- DATA PELANGGAN -->
            <div class="card mb-3">
              <div class="card-header"><h3 class="card-title">Data Pelanggan</h3></div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="text-muted small">Nama Lengkap</div>
                    <div class="fw-semibold"><?= htmlspecialchars($c['name']) ?></div>
                  </div>
                  <div class="col-md-6">
                    <div class="text-muted small">No. KTP / NIK</div>
                    <div><?= htmlspecialchars($c['identity_number'] ?? '-') ?></div>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small">Telepon</div>
                    <?php if ($c['phone']): ?>
                    <a href="tel:<?= htmlspecialchars($c['phone']) ?>"><?= htmlspecialchars($c['phone']) ?></a>
                    &nbsp;<a href="https://wa.me/<?= preg_replace('/^0/','62',$c['phone']) ?>" target="_blank" class="badge bg-green-lt">WhatsApp</a>
                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small">Email</div>
                    <?= $c['email'] ? '<a href="mailto:'.htmlspecialchars($c['email']).'">'.htmlspecialchars($c['email']).'</a>' : '<span class="text-muted">-</span>' ?>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small">Node</div>
                    <div><?= htmlspecialchars($c['node_name'] ?? '-') ?></div>
                  </div>
                  <?php if ($c['address'] ?? ''): ?>
                  <div class="col-12">
                    <div class="text-muted small">Alamat</div>
                    <div><?= nl2br(htmlspecialchars($c['address'])) ?></div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- INFORMASI LAYANAN -->
            <div class="card mb-3">
              <div class="card-header"><h3 class="card-title">Informasi Layanan</h3></div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="text-muted small">Paket</div>
                    <div class="fw-semibold"><?= htmlspecialchars($c['package_name'] ?? '-') ?></div>
                    <?php if ($c['tx_max_limit']): ?>
                    <div class="text-muted small"><?= bitsToHuman($c['tx_max_limit']) ?> ↑ / <?= bitsToHuman($c['rx_max_limit']) ?> ↓</div>
                    <?php endif; ?>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small">Router MikroTik</div>
                    <?php if ($c['mk_id']): ?>
                    <a href="routers.php?id=<?= $c['mk_id'] ?>"><?= htmlspecialchars($c['router_name']) ?></a>
                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small">Harga / Bulan</div>
                    <div class="fw-semibold text-green">Rp <?= number_format($c['package_price']??0,0,',','.') ?></div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-muted small">Tgl Instalasi</div>
                    <div><?= $c['installation_date'] ? date('d M Y', strtotime($c['installation_date'])) : '-' ?></div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-muted small">Tgl Tagihan</div>
                    <div>Setiap tgl <?= $c['billing_cycle_date'] ?? '-' ?></div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-muted small">Jatuh Tempo</div>
                    <div>+<?= $c['billing_due_date'] ?? '-' ?> hari</div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-muted small">Isolasi Otomatis</div>
                    <div>+<?= $c['isolation_date'] ?? '-' ?> hari</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- PERANGKAT PELANGGAN -->
            <?php $has_device = ($c['router_brand'] ?? '') || ($c['router_type'] ?? '') || ($c['router_mac'] ?? '') || ($c['wifi_ssid'] ?? ''); ?>
            <?php if ($has_device): ?>
            <div class="card mb-3">
              <div class="card-header"><h3 class="card-title">Perangkat</h3></div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="text-muted small">Router / ONT</div>
                    <div><?= htmlspecialchars(trim(($c['router_brand']??'').' '.($c['router_type']??''))) ?: '-' ?></div>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small">MAC Address</div>
                    <code><?= htmlspecialchars($c['router_mac'] ?? '-') ?></code>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small">Port Manajemen</div>
                    <?php if ($c['remote_mgmt_port'] ?? null): ?>
                    <code><?= (int)$c['remote_mgmt_port'] ?></code>
                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                  </div>
                  <?php if ($c['wifi_ssid'] ?? ''): ?>
                  <div class="col-md-4">
                    <div class="text-muted small">WiFi SSID</div>
                    <div class="fw-semibold"><?= htmlspecialchars($c['wifi_ssid']) ?></div>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small">WiFi Password</div>
                    <code id="wifi-pass" style="filter:blur(4px);" onmouseover="this.style.filter=''" onmouseout="this.style.filter='blur(4px)'"><?= htmlspecialchars($c['wifi_password'] ?? '-') ?></code>
                    <button class="btn btn-sm btn-ghost-secondary py-0" onclick="copyText('wifi-pass')">Salin</button>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <!-- RIWAYAT TAGIHAN -->
            <div class="card">
              <div class="card-header"><h3 class="card-title">Riwayat Tagihan</h3></div>
              <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table">
                  <thead>
                    <tr>
                      <th>Periode</th>
                      <th>Jumlah</th>
                      <th>Jatuh Tempo</th>
                      <th>Status</th>
                      <th>Dibayar</th>
                      <th>Metode</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($bills as $b):
                    $overdue = ($b['status']==='unpaid' && strtotime($b['due_date'])<time());
                    $bcol = match($b['status']) { 'paid'=>'green', 'cancelled'=>'secondary', default=>($overdue?'red':'yellow') };
                  ?>
                  <tr <?= $overdue ? 'class="table-danger"' : '' ?>>
                    <td class="fw-semibold"><?= $b['period'] ?></td>
                    <td>Rp <?= number_format($b['amount'],0,',','.') ?></td>
                    <td class="text-muted small"><?= $b['due_date'] ? date('d M Y', strtotime($b['due_date'])) : '-' ?></td>
                    <td><span class="badge bg-<?= $bcol ?>-lt"><?= ucfirst($b['status']) ?></span></td>
                    <td class="text-muted small"><?= $b['paid_time'] ? date('d M Y', strtotime($b['paid_time'])) : '-' ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($b['method'] ?? '-') ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (!$bills): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">Belum ada tagihan.</td></tr>
                  <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div><!-- /col-lg-8 -->
        </div><!-- /row -->
      </div>
    </div>
    <?php require_once __DIR__ . '/layout/footer.php'; ?>
  </div>
</div>

<script>
function copyText(id) {
  const el = document.getElementById(id);
  if (!el) return;
  navigator.clipboard.writeText(el.textContent.trim()).then(() => {
    const orig = el.style.filter;
    el.style.filter = '';
    el.style.outline = '2px solid var(--tblr-green)';
    setTimeout(() => { el.style.outline = ''; el.style.filter = orig; }, 1200);
  });
}

// Manual port → buka via proxy (token digenerate server via AJAX)
function openProxyManualPort() {
    const port = parseInt(document.getElementById('manual-port')?.value || '0');
    if (!port || port < 1 || port > 65535) { alert('Masukkan port yang valid (1-65535).'); return; }
    const btn = document.querySelector('.card-body button[onclick="openProxyManualPort()"]') ||
                document.querySelector('button[onclick*="openProxyManualPort"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Membuat token...'; }

    fetch('<?= BASE_URL ?>/api/proxy_token.php?cid=<?= $c['id'] ?>&port=' + port)
        .then(r => r.json())
        .then(d => {
            if (d.url) {
                window.open(d.url, '_blank');
            } else {
                alert('Gagal membuat URL proxy: ' + (d.error || 'Unknown error'));
            }
        })
        .catch(e => alert('Error: ' + e))
        .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10 14a3.5 3.5 0 0 0 5 0l4-4a3.5 3.5 0 0 0-5-5l-.5.5"/><path d="M14 10a3.5 3.5 0 0 0-5 0l-4 4a3.5 3.5 0 0 0 5 5l.5-.5"/></svg>Buka via Proxy'; } });
}

function openManualPort(ip) {
    // Legacy: tidak dipakai lagi, digantikan openProxyManualPort
    openProxyManualPort();
}
</script>
</body>
</html>