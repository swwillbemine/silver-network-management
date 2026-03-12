<?php
// package_form.php — Tambah / Edit Paket Internet
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

require_once BASE_PATH . '/mikrotik/connection.php';
require_once BASE_PATH . '/mikrotik/ppp.php';

$msg = '';

// ── Helpers ────────────────────────────────────────────────────────────────
function pfBpsToMkRate(int $bps): string {
    if ($bps <= 0) return '0';
    if ($bps >= 1_000_000_000 && $bps % 1_000_000_000 === 0) return ($bps/1_000_000_000).'G';
    if ($bps >= 1_000_000     && $bps % 1_000_000     === 0) return ($bps/1_000_000).'M';
    if ($bps >= 1_000         && $bps % 1_000         === 0) return ($bps/1_000).'k';
    return $bps.'';
}
function pfBuildRateLimit(int $rx,int $tx,int $rx_b=0,int $tx_b=0,int $rx_thr=0,int $tx_thr=0,int $rx_t=0,int $tx_t=0): string {
    $s = pfBpsToMkRate($rx).'/'.pfBpsToMkRate($tx);
    if ($rx_b>0||$tx_b>0) {
        $s .= ' '.pfBpsToMkRate($rx_b).'/'.pfBpsToMkRate($tx_b);
        $s .= ' '.pfBpsToMkRate($rx_thr).'/'.pfBpsToMkRate($tx_thr);
        $s .= ' '.$rx_t.'/'.$tx_t;
    }
    return $s;
}
function pfSpeedToBps(float $val, string $unit): int {
    return match($unit) {
        'G' => (int)($val*1_000_000_000),
        'M' => (int)($val*1_000_000),
        'k' => (int)($val*1_000),
        default => (int)$val,
    };
}
function pfBpsToDisplay(int $bps): array {
    if ($bps<=0) return [0,'M'];
    if ($bps>=1_000_000_000&&$bps%1_000_000_000===0) return [(int)($bps/1_000_000_000),'G'];
    if ($bps>=1_000_000    &&$bps%1_000_000    ===0) return [(int)($bps/1_000_000),'M'];
    if ($bps>=1_000        &&$bps%1_000        ===0) return [(int)($bps/1_000),'k'];
    return [round($bps/1_000,2),'k'];
}
function pfAppComment(string $name): string {
    return '[SNM-PROFILE] '.$name.' | via Silver Network Management | '.date('Y-m-d');
}

// ── Migration: tambah kolom only_one jika belum ada ───────────────────────
try {
    $pdo->query("ALTER TABLE packages ADD COLUMN only_one ENUM('default','yes','no') NOT NULL DEFAULT 'default'");
} catch (PDOException $e) { /* kolom sudah ada */ }

// ── Mode ───────────────────────────────────────────────────────────────────
$edit_id = (int)($_GET['id'] ?? 0);
$is_edit = $edit_id > 0;
$pkg     = null;

if ($is_edit) {
    $stmt = $pdo->prepare("
        SELECT p.*, mk.name AS router_name, mk.id AS mk_id,
               COALESCE(p.only_one,'default') AS only_one
        FROM packages p JOIN mikrotiks mk ON mk.id=p.mikrotik_id
        WHERE p.id=?");
    $stmt->execute([$edit_id]);
    $pkg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pkg) { header('Location: packages.php'); exit; }
}

// ── POST ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mk_id  = (int)($_POST['mikrotik_id'] ?? 0);
    $router = null;
    if ($mk_id) {
        $rq = $pdo->prepare("SELECT * FROM mikrotiks WHERE id=?");
        $rq->execute([$mk_id]);
        $router = $rq->fetch(PDO::FETCH_ASSOC);
    }

    if (in_array($action,['create','update'])) {
        $tx      = pfSpeedToBps((float)$_POST['tx_val'],     $_POST['tx_unit']     ?? 'M');
        $rx      = pfSpeedToBps((float)$_POST['rx_val'],     $_POST['rx_unit']     ?? 'M');
        $tx_b    = pfSpeedToBps((float)($_POST['tx_burst_val'] ?? 0), $_POST['tx_burst_unit'] ?? 'M');
        $rx_b    = pfSpeedToBps((float)($_POST['rx_burst_val'] ?? 0), $_POST['rx_burst_unit'] ?? 'M');
        $tx_thr  = pfSpeedToBps((float)($_POST['tx_thr_val']   ?? 0), $_POST['tx_thr_unit']   ?? 'M');
        $rx_thr  = pfSpeedToBps((float)($_POST['rx_thr_val']   ?? 0), $_POST['rx_thr_unit']   ?? 'M');
        $profile_name  = trim($_POST['mikrotik_profile_name']);
        $queue_type    = $_POST['queue_type']           ?? 'default';
        $parent_queue  = trim($_POST['parent_queue']    ?? '') ?: null;
        $insert_before = trim($_POST['queue_insert_before'] ?? '') ?: null;
        $tx_bt  = (int)($_POST['tx_burst_time'] ?? 0);
        $rx_bt  = (int)($_POST['rx_burst_time'] ?? 0);
        $tx_pri = (int)($_POST['tx_priority'] ?? 8);
        $rx_pri = (int)($_POST['rx_priority'] ?? 8);
        $local_addr  = trim($_POST['local_address'] ?? '');
        $remote_pool = trim($_POST['remote_pool']   ?? '');
        $dns1        = trim($_POST['dns_server1'] ?? '');
        $dns2        = trim($_POST['dns_server2'] ?? '');
        $only_one    = in_array($_POST['only_one'] ?? '', ['yes','no','default']) ? $_POST['only_one'] : 'default';

        if ($action === 'create') {
            $pdo->prepare("INSERT INTO packages
                (mikrotik_id,name,price,tx_max_limit,rx_max_limit,
                 tx_burst_limit,rx_burst_limit,tx_burst_threshold,rx_burst_threshold,
                 tx_burst_time,rx_burst_time,tx_priority,rx_priority,
                 queue_type,parent_queue,queue_insert_before,
                 local_address,remote_pool,dns_server1,dns_server2,
                 mikrotik_profile_name,is_active,only_one,description)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)")
                ->execute([$mk_id,$_POST['name'],$_POST['price'],$tx,$rx,
                    $tx_b,$rx_b,$tx_thr,$rx_thr,$tx_bt,$rx_bt,$tx_pri,$rx_pri,
                    $queue_type,$parent_queue,$insert_before,
                    $local_addr,$remote_pool,$dns1,$dns2,
                    $profile_name,$only_one,$_POST['description']??'']);

            if ($router) {
                $client = get_mikrotik_client($router['host'],$router['username'],$router['password'],$router['port']);
                if ($client) {
                    $rate   = pfBuildRateLimit($tx,$rx,$tx_b,$rx_b,$tx_thr,$rx_thr,$tx_bt,$rx_bt);
                    $params = ['name'=>$profile_name,'rate-limit'=>$rate,'comment'=>pfAppComment($profile_name)];
                    if ($local_addr)  $params['local-address']  = $local_addr;
                    if ($remote_pool) $params['remote-address'] = $remote_pool;
                    if ($dns1) $params['dns-server'] = $dns2 ? "$dns1,$dns2" : $dns1;
                    if ($queue_type && $queue_type !== 'default') $params['queue-type'] = $queue_type;
                    if ($parent_queue)  $params['parent-queue'] = $parent_queue;
                    $params['only-one'] = $only_one;
                    // insert-queue-before adalah field valid di /ppp/profile RouterOS
                    // nilainya langsung nama queue — tidak perlu resolve .id
                    if ($insert_before) $params['insert-queue-before'] = $insert_before;
                    $raw  = mikrotik_query($client, '/ppp/profile', 'add', $params);
                    // $raw is array of arrays — check each row for !trap
                    $trap = array_filter($raw, fn($r) => is_array($r)
                        ? in_array('!trap', $r) || in_array('failure', $r)
                        : (is_string($r) && (str_contains($r,'!trap')||str_contains($r,'failure'))));
                    if ($trap) $msg = 'warning:Paket tersimpan, tapi MikroTik error: '.json_encode(array_values($trap));
                } else {
                    $msg = 'warning:Paket tersimpan, tapi router tidak bisa dihubungi.';
                }
            }
            if (!$msg) $msg = 'success:Paket ditambahkan dan PPP Profile dibuat di MikroTik.';
            if (!str_starts_with($msg,'danger')) {
                header('Location: packages.php?msg='.urlencode($msg));
                exit;
            }

        } else { // update
            $old_pkg = $pdo->prepare("SELECT mikrotik_profile_name FROM packages WHERE id=?");
            $old_pkg->execute([$_POST['id']]);
            $old_profile_name = $old_pkg->fetchColumn();

            $pdo->prepare("UPDATE packages SET
                mikrotik_id=?,name=?,price=?,tx_max_limit=?,rx_max_limit=?,
                tx_burst_limit=?,rx_burst_limit=?,tx_burst_threshold=?,rx_burst_threshold=?,
                tx_burst_time=?,rx_burst_time=?,tx_priority=?,rx_priority=?,
                queue_type=?,parent_queue=?,queue_insert_before=?,
                local_address=?,remote_pool=?,dns_server1=?,dns_server2=?,
                mikrotik_profile_name=?,only_one=?,description=? WHERE id=?")
                ->execute([$mk_id,$_POST['name'],$_POST['price'],$tx,$rx,
                    $tx_b,$rx_b,$tx_thr,$rx_thr,$tx_bt,$rx_bt,$tx_pri,$rx_pri,
                    $queue_type,$parent_queue,$insert_before,
                    $local_addr,$remote_pool,$dns1,$dns2,
                    $profile_name,$only_one,$_POST['description']??'',$_POST['id']]);

            if ($router) {
                $client = get_mikrotik_client($router['host'],$router['username'],$router['password'],$router['port']);
                if ($client) {
                    $search_name   = $old_profile_name ?: $profile_name;
                    $_all_profiles = mikrotik_query($client, '/ppp/profile', 'print', []);
                    $existing      = array_values(array_filter($_all_profiles, fn($p) => ($p['name']??'') === $search_name));
                    $rate          = pfBuildRateLimit($tx,$rx,$tx_b,$rx_b,$tx_thr,$rx_thr,$tx_bt,$rx_bt);
                    $set_params    = ['name'=>$profile_name,'rate-limit'=>$rate,'comment'=>pfAppComment($profile_name)];
                    if ($local_addr)  $set_params['local-address']  = $local_addr;
                    if ($remote_pool) $set_params['remote-address'] = $remote_pool;
                    if ($dns1) $set_params['dns-server'] = $dns2 ? "$dns1,$dns2" : $dns1;
                    if ($queue_type && $queue_type !== 'default') $set_params['queue-type'] = $queue_type;
                    if ($parent_queue)   $set_params['parent-queue'] = $parent_queue;
                    else                 $set_params['parent-queue'] = ''; // kosongkan jika dihapus
                    $set_params['only-one'] = $only_one;
                    // place-before tidak valid di /set — gunakan /move setelah set
                    // insert-queue-before harus masuk ke params SEBELUM dipanggil
                    if ($insert_before) $set_params['insert-queue-before'] = $insert_before;
                    if (!empty($existing[0]['.id'])) {
                        mikrotik_query($client, '/ppp/profile', 'set', $set_params, $existing[0]['.id']);
                        $msg = $old_profile_name && $old_profile_name !== $profile_name
                            ? 'success:Paket diperbarui. Profile direname: '.$old_profile_name.' → '.$profile_name
                            : 'success:Paket diperbarui dan PPP Profile disinkronkan.';
                    } else {
                        if ($insert_before) $set_params['insert-queue-before'] = $insert_before;
                        mikrotik_query($client, '/ppp/profile', 'add', $set_params);
                        $msg = 'success:Paket diperbarui. Profile baru dibuat (lama tidak ditemukan).';
                    }
                } else {
                    $msg = 'warning:Paket diperbarui di database, tapi router tidak bisa dihubungi.';
                }
            } else {
                $msg = 'success:Paket diperbarui.';
            }
            header('Location: packages.php?msg='.urlencode($msg));
            exit;
        }
    }
}

// ── Data ───────────────────────────────────────────────────────────────────
$routers = $pdo->query("SELECT id,name,host FROM mikrotiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$queue_types = [
    'default'=>'default','default-small'=>'default-small','ethernet-default'=>'ethernet-default',
    'wireless-default'=>'wireless-default','hotspot-default'=>'hotspot-default',
    'only-hardware-queue'=>'only-hardware-queue','multi-queue-ethernet-default'=>'multi-queue-ethernet-default',
    'pcq-upload-default'=>'pcq-upload-default','pcq-download-default'=>'pcq-download-default',
    'sfq'=>'sfq','red'=>'red','cake'=>'cake','synchronous-default'=>'synchronous-default',
];

[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];

// For edit: pre-compute display values
$pre = [];
if ($is_edit && $pkg) {
    [$pre['tx_v'],$pre['tx_u']] = pfBpsToDisplay((int)$pkg['tx_max_limit']);
    [$pre['rx_v'],$pre['rx_u']] = pfBpsToDisplay((int)$pkg['rx_max_limit']);
    [$pre['txb_v'],$pre['txb_u']] = pfBpsToDisplay((int)$pkg['tx_burst_limit']);
    [$pre['rxb_v'],$pre['rxb_u']] = pfBpsToDisplay((int)$pkg['rx_burst_limit']);
    [$pre['txt_v'],$pre['txt_u']] = pfBpsToDisplay((int)$pkg['tx_burst_threshold']);
    [$pre['rxt_v'],$pre['rxt_u']] = pfBpsToDisplay((int)$pkg['rx_burst_threshold']);
}
$v = function(string $field, $default='') use ($pkg,$is_edit): string {
    if (isset($_POST[$field])) return htmlspecialchars((string)$_POST[$field]);
    if ($is_edit && $pkg && isset($pkg[$field])) return htmlspecialchars((string)$pkg[$field]);
    return htmlspecialchars((string)$default);
};
?>
<!doctype html>
<html lang="id">
<?php require_once __DIR__ . '/layout/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<body>
<div class="page">
  <?php require_once __DIR__ . '/layout/sidebar.php'; ?>
  <div class="page-wrapper">
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
            <h2 class="page-title"><?= $is_edit ? 'Edit Paket' : 'Tambah Paket' ?></h2>
            <?php if ($is_edit): ?>
            <div class="text-muted small"><?= htmlspecialchars($pkg['name']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">

        <?php if ($msg_text): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible mb-3">
          <?= htmlspecialchars($msg_text) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
          <?php if ($is_edit): ?>
          <input type="hidden" name="id" value="<?= $edit_id ?>">
          <?php endif; ?>

          <!-- ── INFORMASI PAKET ──────────────────────────────── -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-muted" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Informasi Paket
              </h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label required">Nama Paket</label>
                  <input type="text" name="name" class="form-control" required value="<?= $v('name') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label required">Harga / Bulan (Rp)</label>
                  <input type="number" name="price" class="form-control" required min="0" value="<?= $v('price') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label required">Router MikroTik</label>
                  <select name="mikrotik_id" id="f-router" class="form-select" required>
                    <option value="">-- Pilih Router --</option>
                    <?php foreach ($routers as $r): ?>
                    <option value="<?= $r['id'] ?>"
                            <?= ($is_edit&&$pkg&&$pkg['mk_id']==$r['id'])||($_POST['mikrotik_id']??'')==$r['id']?'selected':'' ?>>
                      <?= htmlspecialchars($r['name']) ?> (<?= $r['host'] ?>)
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label required">Nama PPP Profile</label>
                  <input type="text" name="mikrotik_profile_name" class="form-control" required
                         placeholder="cth: pppoe-10m" value="<?= $v('mikrotik_profile_name') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Local Address</label>
                  <input type="text" name="local_address" class="form-control"
                         placeholder="10.0.0.1" value="<?= $v('local_address') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Remote Pool</label>
                  <input type="hidden" name="remote_pool" id="hidden-remote-pool" value="">
                  <select id="f-pool" class="form-select">
                    <option value="">-- Pilih router dulu --</option>
                    <?php if ($is_edit && !empty($pkg['remote_pool'])): ?>
                    <option value="<?= htmlspecialchars($pkg['remote_pool']) ?>" selected><?= htmlspecialchars($pkg['remote_pool']) ?></option>
                    <?php endif; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">DNS Server 1</label>
                  <input type="text" name="dns_server1" class="form-control"
                         placeholder="10.10.0.1" maxlength="45" value="<?= $v('dns_server1') ?>">
                  <div class="form-hint">DNS utama / lokal</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">DNS Server 2</label>
                  <input type="text" name="dns_server2" class="form-control"
                         placeholder="8.8.8.8" maxlength="45" value="<?= $v('dns_server2') ?>">
                  <div class="form-hint">DNS cadangan (opsional)</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Keterangan</label>
                  <input type="text" name="description" class="form-control" value="<?= $v('description') ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- ── BANDWIDTH ───────────────────────────────────────── -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-muted" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M3 12h4l3-9 4 18 3-9h4"/></svg>
                Bandwidth Limit
              </h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <?php
                $speed_unit_sel = fn($id,$sel) => '<select name="'.$id.'" id="'.$id.'" class="form-select speed-unit" style="max-width:75px;">'.
                    '<option value="k"'.($sel==='k'?' selected':'').'>kbps</option>'.
                    '<option value="M"'.($sel==='M'?' selected':'').'>Mbps</option>'.
                    '<option value="G"'.($sel==='G'?' selected':'').'>Gbps</option></select>';
                ?>
                <div class="col-md-3">
                  <label class="form-label required">Max Upload</label>
                  <div class="input-group">
                    <input type="number" name="tx_val" id="f-tx-val" class="form-control speed-input"
                           required min="0.001" step="any"
                           value="<?= $is_edit ? $pre['tx_v'] : '' ?>">
                    <?= $speed_unit_sel('tx_unit', $is_edit ? $pre['tx_u'] : 'M') ?>
                  </div>
                  <small class="text-muted speed-preview" id="prev-tx"></small>
                </div>
                <div class="col-md-3">
                  <label class="form-label required">Max Download</label>
                  <div class="input-group">
                    <input type="number" name="rx_val" id="f-rx-val" class="form-control speed-input"
                           required min="0.001" step="any"
                           value="<?= $is_edit ? $pre['rx_v'] : '' ?>">
                    <?= $speed_unit_sel('rx_unit', $is_edit ? $pre['rx_u'] : 'M') ?>
                  </div>
                  <small class="text-muted speed-preview" id="prev-rx"></small>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Priority Upload</label>
                  <select name="tx_priority" id="f-tx-pri" class="form-select">
                    <?php for ($i=1;$i<=8;$i++): ?>
                    <option value="<?=$i?>" <?=$v('tx_priority',8)==$i?'selected':''?>>
                      <?=$i?><?=$i==1?' — Tertinggi':($i==8?' — Terendah':'')?>
                    </option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Priority Download</label>
                  <select name="rx_priority" id="f-rx-pri" class="form-select">
                    <?php for ($i=1;$i<=8;$i++): ?>
                    <option value="<?=$i?>" <?=$v('rx_priority',8)==$i?'selected':''?>>
                      <?=$i?><?=$i==1?' — Tertinggi':($i==8?' — Terendah':'')?>
                    </option>
                    <?php endfor; ?>
                  </select>
                </div>

                <!-- BURST (collapsible) -->
                <div class="col-12 mt-1">
                  <a href="#burst-fields" class="text-muted small fw-semibold text-uppercase"
                     data-bs-toggle="collapse" aria-expanded="<?= $is_edit&&($pre['txb_v']>0||$pre['rxb_v']>0)?'true':'false' ?>">
                    ▸ Burst (opsional)
                  </a>
                  <hr class="my-1 mb-0">
                </div>
                <div class="col-12 collapse <?= $is_edit&&($pre['txb_v']>0||$pre['rxb_v']>0)?'show':'' ?>" id="burst-fields">
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label class="form-label">Burst Upload</label>
                      <div class="input-group">
                        <input type="number" name="tx_burst_val" id="f-tx-burst-val" class="form-control speed-input" min="0" step="any" value="<?= $is_edit?$pre['txb_v']:0 ?>">
                        <?= $speed_unit_sel('tx_burst_unit', $is_edit?$pre['txb_u']:'M') ?>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Burst Download</label>
                      <div class="input-group">
                        <input type="number" name="rx_burst_val" id="f-rx-burst-val" class="form-control speed-input" min="0" step="any" value="<?= $is_edit?$pre['rxb_v']:0 ?>">
                        <?= $speed_unit_sel('rx_burst_unit', $is_edit?$pre['rxb_u']:'M') ?>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Threshold Upload</label>
                      <div class="input-group">
                        <input type="number" name="tx_thr_val" id="f-tx-thr-val" class="form-control speed-input" min="0" step="any" value="<?= $is_edit?$pre['txt_v']:0 ?>">
                        <?= $speed_unit_sel('tx_thr_unit', $is_edit?$pre['txt_u']:'M') ?>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Threshold Download</label>
                      <div class="input-group">
                        <input type="number" name="rx_thr_val" id="f-rx-thr-val" class="form-control speed-input" min="0" step="any" value="<?= $is_edit?$pre['rxt_v']:0 ?>">
                        <?= $speed_unit_sel('rx_thr_unit', $is_edit?$pre['rxt_u']:'M') ?>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Burst Time Upload (dtk)</label>
                      <input type="number" name="tx_burst_time" id="f-tx-bt" class="form-control" min="0" value="<?= $v('tx_burst_time',0) ?>">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Burst Time Download (dtk)</label>
                      <input type="number" name="rx_burst_time" id="f-rx-bt" class="form-control" min="0" value="<?= $v('rx_burst_time',0) ?>">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── QUEUE CONFIG ─────────────────────────────────────── -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-muted" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                Konfigurasi Queue
              </h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Queue Type</label>
                  <select name="queue_type" id="f-queue-type" class="form-select">
                    <?php foreach ($queue_types as $val=>$lbl): ?>
                    <option value="<?= $val ?>" <?= $v('queue_type','default')===$val?'selected':'' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Only One Session</label>
                  <select name="only_one" class="form-select">
                    <option value="default" <?= $v('only_one','default')==='default'?'selected':'' ?>>default</option>
                    <option value="yes"     <?= $v('only_one','default')==='yes'    ?'selected':'' ?>>yes — 1 sesi/user</option>
                    <option value="no"      <?= $v('only_one','default')==='no'     ?'selected':'' ?>>no — multi sesi</option>
                  </select>
                  <div class="form-hint">Batasi 1 koneksi per username</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Parent Queue</label>
                  <input type="hidden" name="parent_queue" id="hidden-parent-queue" value="">
                  <select id="f-parent-queue" class="form-select">
                    <option value="">— none —</option>
                    <?php if ($is_edit && !empty($pkg['parent_queue'])): ?>
                    <option value="<?= htmlspecialchars($pkg['parent_queue']) ?>" selected><?= htmlspecialchars($pkg['parent_queue']) ?></option>
                    <?php endif; ?>
                  </select>
                  <div class="form-hint">Pilih router untuk memuat daftar queue</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Insert Queue Before</label>
                  <input type="hidden" name="queue_insert_before" id="hidden-insert-before" value="">
                  <select id="f-insert-before" class="form-select">
                    <option value="">— append (paling akhir) —</option>
                    <?php if ($is_edit && !empty($pkg['queue_insert_before'])): ?>
                    <option value="<?= htmlspecialchars($pkg['queue_insert_before']) ?>" selected><?= htmlspecialchars($pkg['queue_insert_before']) ?></option>
                    <?php endif; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- ── ACTION BUTTONS ──────────────────────────────────── -->
          <div class="d-flex gap-2 justify-content-end align-items-center mb-4">
            <a href="packages.php" class="btn btn-ghost-secondary btn-sm">Batal</a>
            <button type="submit" class="btn btn-primary btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
              <?= $is_edit ? 'Simpan Perubahan' : 'Tambah Paket' ?> &amp; Push MikroTik
            </button>
          </div>

        </form>
      </div>
    </div>
    
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
// TomSelect controls — form submission uses hidden inputs, not the TomSelect selects
// This is needed because TomSelect addOption() does NOT sync back to the native <select>
const tsPool   = new TomSelect('#f-pool',          {allowEmptyOption:true, onChange: v=>{document.getElementById('hidden-remote-pool').value=v;}});
const tsParent = new TomSelect('#f-parent-queue',  {allowEmptyOption:true, onChange: v=>{document.getElementById('hidden-parent-queue').value=v;}});
const tsBefore = new TomSelect('#f-insert-before', {allowEmptyOption:true, onChange: v=>{document.getElementById('hidden-insert-before').value=v;}});

// ── Speed previews ──────────────────────────────────────────────────────────
const MUL = {k:1e3, M:1e6, G:1e9};
function bpsToStr(b){
    if(!b)return'';
    if(b>=1e9)return(b/1e9).toFixed(2).replace(/\.?0+$/,'')+'Gbps';
    if(b>=1e6)return(b/1e6).toFixed(3).replace(/\.?0+$/,'')+'Mbps';
    if(b>=1e3)return(b/1e3).toFixed(2).replace(/\.?0+$/,'')+'Kbps';
    return b+'bps';
}
function updatePreview(vId,uId,pId){
    const v=parseFloat(document.getElementById(vId)?.value)||0;
    const u=document.getElementById(uId)?.value||'M';
    const el=document.getElementById(pId);
    if(el)el.textContent=v?'= '+bpsToStr(v*(MUL[u]||1e6)):'';
}
['tx','rx'].forEach(d=>{
    document.getElementById('f-'+d+'-val')?.addEventListener('input',()=>updatePreview('f-'+d+'-val',d+'_unit','prev-'+d));
    document.getElementById(d+'_unit')?.addEventListener('change',()=>updatePreview('f-'+d+'-val',d+'_unit','prev-'+d));
});
// Init previews on load (edit mode)
updatePreview('f-tx-val','tx_unit','prev-tx');
updatePreview('f-rx-val','rx_unit','prev-rx');

// ── Pool & Queue loaders ────────────────────────────────────────────────────
async function loadPools(rid){
    tsPool.clear(); tsPool.clearOptions();
    tsPool.addOption({value:'',text:'— Tanpa Pool —'});
    if(!rid)return;
    try{
        const d=await(await fetch('api/pools.php?router_id='+rid)).json();
        d.forEach(p=>tsPool.addOption({value:p.name,text:p.name+' ('+p.ranges+')'}));
        tsPool.refreshOptions(false);
    }catch(e){console.error(e);}
}
// Parent Queue — /queue/tree & /queue/simple dari router
async function loadQueues(rid){
    tsParent.clear();tsParent.clearOptions();
    tsParent.addOption({value:'',text:'— none —'});
    if(!rid)return;
    try{
        const d=await(await fetch('api/queues.php?router_id='+rid)).json();
        (d.queues||[]).forEach(q=>{
            const lbl=q.name+' ['+q.type+']'+(q.rate&&q.rate!=='-'?' — '+q.rate:'');
            tsParent.addOption({value:q.name,text:lbl});
        });
        tsParent.refreshOptions(false);
    }catch(e){console.error(e);}
}

// Insert Queue Before — /ppp/profile dari router (bukan queue!)
async function loadBefore(rid){
    tsBefore.clear();tsBefore.clearOptions();
    tsBefore.addOption({value:'',text:'— append (paling akhir) —'});
    if(!rid)return;
    try{
        const d=await(await fetch('api/queues.php?router_id='+rid)).json();
        (d.queues||[]).forEach(q=>{
            const lbl=q.name+' ['+q.type+']'+(q.rate&&q.rate!=='-'?' — '+q.rate:'');
            tsBefore.addOption({value:q.name,text:lbl});
        });
        tsBefore.refreshOptions(false);
    }catch(e){console.error(e);}
}

document.getElementById('f-router')?.addEventListener('change',function(){
    loadPools(this.value);
    loadQueues(this.value);
    loadBefore(this.value);
});

// On edit mode: restore saved pool/queue values after loading
<?php if ($is_edit && $pkg): ?>
(async()=>{
    const rid='<?= $pkg['mk_id'] ?>';
    const poolVal    = '<?= addslashes($pkg['remote_pool']??'') ?>';
    const parentVal  = '<?= addslashes($pkg['parent_queue']??'') ?>';
    const beforeVal  = '<?= addslashes($pkg['queue_insert_before']??'') ?>';
    await loadPools(rid);
    if(poolVal){ tsPool.addOption({value:poolVal,text:poolVal}); tsPool.setValue(poolVal); document.getElementById('hidden-remote-pool').value=poolVal; }
    await loadQueues(rid);
    if(parentVal){ tsParent.setValue(parentVal); document.getElementById('hidden-parent-queue').value=parentVal; }
    await loadBefore(rid);
    if(beforeVal){ tsBefore.addOption({value:beforeVal,text:beforeVal}); tsBefore.setValue(beforeVal); document.getElementById('hidden-insert-before').value=beforeVal; }
})();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>