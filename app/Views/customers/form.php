<?php
\App\Core\View::header(['title' => $page_title]);
?>
<body>
<div class="page">
  <?php \App\Core\View::sidebar(); ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col-auto">
            <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-ghost-secondary btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M15 6l-6 6 6 6"/></svg>
              Kembali
            </a>
          </div>
          <div class="col">
            <div class="page-pretitle">Pelanggan</div>
            <h2 class="page-title"><?= $page_title ?></h2>
            <?php if ($is_edit): ?>
            <div class="text-muted small"><?= $page_sub ?></div>
            <?php endif; ?>
          </div>
          <?php if ($is_edit): ?>
          <div class="col-auto">
            <a href="<?= BASE_URL ?>/customers/<?= $cust['id'] ?>" class="btn btn-outline-secondary btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 12m-2 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0M12 12l0 9M3.6 9h16.8M3.6 9a9 9 0 1 0 16.8 0"/></svg>
              Lihat Detail
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">

        <?php if (!empty($msg_text)): ?>
        <div class="alert alert-<?= htmlspecialchars($msg_type) ?> alert-dismissible mb-3">
          <?= htmlspecialchars($msg_text) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
          <?php if ($is_edit): ?>
          <input type="hidden" name="id" value="<?= $cust['id'] ?>">
          <?php endif; ?>

          <?php
          $v = function(string $field, $default = '') use ($cust, $is_edit): string {
              if (isset($_POST[$field])) return htmlspecialchars((string)$_POST[$field]);
              if ($is_edit && $cust && isset($cust[$field])) return htmlspecialchars((string)$cust[$field]);
              return htmlspecialchars((string)$default);
          };
          $sel = function(string $field, string $value) use ($v): string {
              return $v($field) === $value ? 'selected' : '';
          };
          ?>

          <!-- ── IDENTITAS ─────────────────────────────────────── -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-muted" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0-8 0M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>
                Identitas Pelanggan
              </h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label required">Nama Lengkap</label>
                  <input type="text" name="name" class="form-control" required value="<?= $v('name') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">No. KTP / NIK</label>
                  <input type="text" name="identity_number" class="form-control" value="<?= $v('identity_number') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label required">No. Telepon</label>
                  <input type="text" name="phone" id="f-phone" class="form-control" required value="<?= $v('phone') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" value="<?= $v('email') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label required">Node</label>
                  <select name="node_id" id="f-node" class="form-select" required>
                    <option value="">-- Pilih Node --</option>
                    <?php foreach ($nodes as $n): ?>
                    <option value="<?= $n['id'] ?>"
                            data-phone="<?= htmlspecialchars($n['phone']) ?>"
                            <?= $v('node_id') == $n['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($n['name']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <div id="node-phone-hint" class="form-hint d-none">
                    No. HP node: <a id="node-phone-val" href="#" onclick="useNodePhone(event)" class="text-primary fw-semibold"></a>
                    <span class="text-muted small">(klik untuk pakai)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── PAKET ─────────────────────────────────────────── -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-muted" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M3 6a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6z"/><path d="M3 10h18"/></svg>
                Paket & Layanan
              </h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label required">Router MikroTik</label>
                  <select id="f-router-filter" class="form-select">
                    <option value="">-- Pilih Router --</option>
                    <?php foreach ($routers as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-hint">Pilih router untuk melihat paket yang tersedia</div>
                </div>
                <div class="col-md-8">
                  <label class="form-label required">Paket Internet</label>
                  <select name="package_id" id="f-package" class="form-select" required disabled>
                    <option value="">-- Pilih Router Dulu --</option>
                    <?php foreach ($packages as $p):
                      $dl = (int)$p['rx_max_limit'];
                      $ul = (int)$p['tx_max_limit'];
                      $fmtSpd = fn($b) => $b>=1e6 ? round($b/1e6,0).'M' : ($b>=1e3 ? round($b/1e3,0).'K' : $b);
                      $label = $p['pkg_name'] . '  —  ↓'.$fmtSpd($dl).'/'.'↑'.$fmtSpd($ul).'  —  Rp '.number_format($p['price'],0,',','.');
                    ?><option value="<?= $p['id'] ?>"
                              data-mk="<?= $p['mikrotik_id'] ?>"
                              data-ul="<?= $ul ?>"
                              data-dl="<?= $dl ?>"
                              data-price="<?= $p['price'] ?>"
                              data-name="<?= htmlspecialchars($p['pkg_name']) ?>"
                              <?= $v('package_id') == $p['id'] ? 'selected' : '' ?>>
                      <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <div id="pkg-hint" class="d-none mt-2">
                    <div class="card card-sm border-primary" style="border-width:2px!important;">
                      <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                          <div>
                            <div class="fw-semibold text-primary" id="pkg-hint-name"></div>
                            <div class="small text-muted">
                              <span class="me-2">↓ <strong id="pkg-hint-dl"></strong></span>
                              <span>↑ <strong id="pkg-hint-ul"></strong></span>
                            </div>
                          </div>
                          <div class="text-end">
                            <div class="fs-4 fw-bold text-success" id="pkg-hint-price"></div>
                            <div class="text-muted small">per bulan</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── DATA LAYANAN ──────────────────────────────────── -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-muted" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M5 12H3l9-9 9 9h-2M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/><path d="M9 21v-6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6"/></svg>
                Data Layanan
              </h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <?php if ($is_edit && !empty($cust['customer_number'])): ?>
                <div class="col-md-3">
                  <label class="form-label">Nomor Pelanggan</label>
                  <div class="input-group">
                    <span class="input-group-text text-muted">#</span>
                    <input type="text" class="form-control fw-bold font-monospace"
                           value="<?= htmlspecialchars($cust['customer_number']) ?>"
                           readonly style="background:var(--tblr-bg-surface-secondary);" tabindex="-1">
                  </div>
                  <div class="form-hint">ID unik, tidak bisa diubah</div>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                  <label class="form-label">PPPoE Username</label>
                  <div class="input-group">
                    <input type="text" name="pppoe_username" id="f-pppoe-user" class="form-control"
                           value="<?= $v('pppoe_username') ?>"
                           placeholder="<?= $is_edit ? '' : 'Kosongkan = pakai No. Pelanggan' ?>">
                    <?php if ($is_edit): ?>
                    <span class="input-group-text text-muted" title="Username PPPoE untuk MikroTik">
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon m-0" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M15 11l4 4-4 4M10 11l-4 4 4 4"/></svg>
                    </span>
                    <?php endif; ?>
                  </div>
                  <?php if (!$is_edit): ?>
                  <div class="form-hint">Kosongkan untuk otomatis pakai nomor pelanggan</div>
                  <?php endif; ?>
                </div>
                <div class="col-md-3">
                  <label class="form-label">PPPoE Password</label>
                  <div class="input-group">
                    <input type="text" name="pppoe_password" id="f-pppoe-pass" class="form-control"
                           value="<?= $v('pppoe_password') ?>"
                           placeholder="Kosongkan = <?= $is_edit ? 'generate baru' : 'generate otomatis' ?>">
                    <button type="button" class="btn btn-outline-secondary" onclick="randomPppoePass()" tabindex="-1" title="Generate password acak">
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon m-0" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M20 11a8.1 8.1 0 0 0-15.5-2m-.5-4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg>
                    </button>
                  </div>
                  <div class="form-hint">Kosongkan untuk password acak 8 karakter</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label required">Tgl Instalasi</label>
                  <input type="date" name="installation_date" class="form-control" required
                          value="<?= $v('installation_date', date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Status Koneksi</label>
                  <select name="status" class="form-select">
                    <option value="active"      <?= $sel('status','active') ?>>Aktif</option>
                    <option value="isolated"    <?= $sel('status','isolated') ?>>Isolir</option>
                    <option value="terminated"  <?= $sel('status','terminated') ?>>Terminasi</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Tipe Tagihan</label>
                  <select name="billing_type" id="f-billing-type" class="form-select" onchange="toggleFreeNote(this.value)">
                    <option value="normal"  <?= $sel('billing_type','normal') ?>>Normal</option>
                    <option value="free"    <?= $sel('billing_type','free') ?>>Gratis (tidak ditagih)</option>
                  </select>
                </div>
                <div class="col-12 <?= $v('billing_type') === 'free' ? '' : 'd-none' ?>" id="free-note">
                  <div class="alert alert-info mb-0 small">
                    <strong>Tagihan Gratis</strong> — Cocok untuk masjid, toko keluarga, kantor sendiri, atau fasilitas umum.
                    PPPoE tetap aktif di MikroTik dan bandwidth sesuai paket. Tidak ada tagihan yang dibuat secara otomatis.
                  </div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Tgl Tagihan</label>
                  <input type="number" name="billing_cycle_date" class="form-control" min="1" max="31"
                         value="<?= $v('billing_cycle_date', 20) ?>">
                  <div class="form-hint">Tanggal generate tagihan tiap bulan</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Jatuh Tempo (+hari)</label>
                  <input type="number" name="billing_due_date" class="form-control" min="1" max="31"
                         value="<?= $v('billing_due_date', 30) ?>">
                  <div class="form-hint">Hari setelah tgl tagihan</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Isolir (+hari)</label>
                  <input type="number" name="isolation_date" class="form-control" min="1" max="31"
                         value="<?= $v('isolation_date', 1) ?>">
                  <div class="form-hint">Hari setelah jatuh tempo</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Diskon Tagihan (Rp)</label>
                  <input type="number" name="discount" class="form-control" min="0"
                         value="<?= $v('discount', 0) ?>" placeholder="0">
                  <div class="form-hint">Potongan tetap tiap bulan. 0 = tidak ada diskon.</div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── PERANGKAT ──────────────────────────────────────── -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-muted" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12h.01"/><path d="M7 12h.01"/><path d="M17 12h.01"/></svg>
                Perangkat Pelanggan
              </h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">Merk Router</label>
                  <input type="text" name="router_brand" class="form-control"
                         placeholder="TP-Link" value="<?= $v('router_brand') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Tipe Router</label>
                  <input type="text" name="router_type" class="form-control"
                         placeholder="TL-WR840N" value="<?= $v('router_type') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">MAC Address</label>
                  <input type="text" name="router_mac" class="form-control"
                         placeholder="AA:BB:CC:DD:EE:FF" value="<?= $v('router_mac') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Port Remote Mgmt</label>
                  <input type="number" name="remote_mgmt_port" class="form-control"
                         placeholder="e.g. 8291" min="1" max="65535"
                         value="<?= $v('remote_mgmt_port') ?>">
                  <div class="form-hint">Kosongkan jika tidak ada</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">WiFi SSID</label>
                  <input type="text" name="wifi_ssid" class="form-control" value="<?= $v('wifi_ssid') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WiFi Password</label>
                  <input type="text" name="wifi_password" class="form-control" value="<?= $v('wifi_password') ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- ── ACTION BUTTONS ─────────────────────────────────── -->
          <div class="d-flex gap-2 justify-content-end align-items-center mb-4">
            <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-ghost-secondary btn-md">
              Batal
            </a>
            <button type="submit" class="btn btn-primary btn-md">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
              <?= $is_edit ? 'Simpan Perubahan' : 'Tambah Pelanggan' ?>
            </button>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>

<script>
function fmtSpd(b) {
    b = parseInt(b)||0;
    if (b >= 1e9) return (b/1e9).toFixed(1)+' Gbps';
    if (b >= 1e6) return (b/1e6).toFixed(0)+' Mbps';
    if (b >= 1e3) return (b/1e3).toFixed(0)+' Kbps';
    return b+' bps';
}

function showPkgHint(opt) {
    const hint = document.getElementById('pkg-hint');
    if (!hint) return;
    if (opt && opt.dataset.price) {
        document.getElementById('pkg-hint-name').textContent  = opt.dataset.name || '';
        document.getElementById('pkg-hint-dl').textContent    = fmtSpd(opt.dataset.dl);
        document.getElementById('pkg-hint-ul').textContent    = fmtSpd(opt.dataset.ul);
        document.getElementById('pkg-hint-price').textContent = 'Rp ' + parseInt(opt.dataset.price).toLocaleString('id-ID');
        hint.classList.remove('d-none');
    } else {
        hint.classList.add('d-none');
    }
}

function filterPackagesByRouter(mkId, resetVal) {
    const sel = document.getElementById('f-package');
    if (resetVal !== false) { sel.value = ''; showPkgHint(null); }
    let count = 0;
    sel.querySelectorAll('option[data-mk]').forEach(opt => {
        const show = !mkId || opt.dataset.mk === mkId;
        opt.hidden = !show;
        if (show) count++;
    });
    if (mkId) {
        sel.disabled = false;
        sel.querySelector('option[value=""]').textContent = '-- Pilih Paket (' + count + ' tersedia) --';
    } else {
        sel.disabled = true;
        sel.querySelector('option[value=""]').textContent = '-- Pilih Router Dulu --';
    }
}

document.getElementById('f-package').addEventListener('change', function() {
    showPkgHint(this.options[this.selectedIndex]);
});

document.getElementById('f-router-filter').addEventListener('change', function() {
    filterPackagesByRouter(this.value, true);
});

document.getElementById('f-node').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const nodePhone = opt?.dataset.phone || '';
    const hint = document.getElementById('node-phone-hint');
    const hintVal = document.getElementById('node-phone-val');
    if (nodePhone) {
        hintVal.textContent = nodePhone;
        hint.classList.remove('d-none');
        const phoneField = document.getElementById('f-phone');
        if (!phoneField.value.trim()) {
            phoneField.value = nodePhone;
            phoneField.style.borderColor = 'var(--tblr-primary)';
            setTimeout(() => phoneField.style.borderColor = '', 1500);
        }
    } else {
        hint.classList.add('d-none');
    }
});

function useNodePhone(e) {
    e.preventDefault();
    const nodePhone = document.getElementById('node-phone-val').textContent;
    const phoneField = document.getElementById('f-phone');
    phoneField.value = nodePhone;
    phoneField.focus();
    phoneField.style.borderColor = 'var(--tblr-primary)';
    setTimeout(() => phoneField.style.borderColor = '', 1500);
}

document.getElementById('f-phone')?.addEventListener('change', function() {
    const nodeOpt = document.querySelector('#f-node option:checked');
    if (!nodeOpt || !nodeOpt.value) return;
    const newPhone = this.value.trim();
    if (newPhone && newPhone !== nodeOpt.dataset.phone) {
        nodeOpt.dataset.phone = newPhone;
        fetch('api/sync_node_phone.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ node_id: nodeOpt.value, phone: newPhone })
        });
    }
});

const CHARS = 'abcdefghjkmnpqrstuvwxyz23456789';
function randomPppoePass() {
    let p = '';
    for (let i = 0; i < 8; i++) p += CHARS[Math.floor(Math.random() * CHARS.length)];
    const f = document.getElementById('f-pppoe-pass');
    f.value = p;
    f.style.borderColor = 'var(--tblr-primary)';
    setTimeout(() => f.style.borderColor = '', 1500);
}

function toggleFreeNote(val) {
    document.getElementById('free-note').classList.toggle('d-none', val !== 'free');
}

(function initRouterFilter() {
    const pkgSel = document.getElementById('f-package');
    const selOpt = pkgSel.options[pkgSel.selectedIndex];
    if (selOpt && selOpt.dataset.mk) {
        const routerSel = document.getElementById('f-router-filter');
        routerSel.value = selOpt.dataset.mk;
        filterPackagesByRouter(selOpt.dataset.mk, false);
        showPkgHint(selOpt);
    }
})();
</script>
</body>
</html>

