<!doctype html>
<html lang="id">
<?php \App\Core\View::header(); ?>
<body>
<div class="page">
  <?php \App\Core\View::sidebar(); ?>
  <div class="page-wrapper">

    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle">MikroTik</div>
            <h2 class="page-title">PPPoE Server</h2>
          </div>
          <div class="col-auto d-flex gap-2">
            <!-- Router selector -->
            <form method="GET" class="d-flex gap-2">
              <select name="router_id" class="form-select form-select-sm"
                      onchange="this.form.submit()" style="width:220px;">
                <?php foreach ($routers as $r): ?>
                <option value="<?= $r['id'] ?>" <?= $r['id'] == $selected_router_id ? 'selected' : '' ?>>
                  <?= htmlspecialchars($r['name']) ?>
                  <span class="text-muted">(<?= htmlspecialchars($r['pop_name']) ?>)</span>
                </option>
                <?php endforeach; ?>
              </select>
            </form>
            <?php if ($client): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalServer"
                    onclick="openCreate()">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                   stroke-width="2" stroke="currentColor" fill="none">
                <path d="M12 5v14M5 12h14"/>
              </svg>
              Tambah Server
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">

        <?php if (!empty($msg_text)): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible mb-3" role="alert">
          <?= htmlspecialchars($msg_text) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!$selected_router): ?>
        <div class="alert alert-warning">
          Tidak ada router terdaftar.
          <a href="<?= BASE_URL ?>/routers">Tambah router</a> terlebih dahulu.
        </div>

        <?php elseif (!$client): ?>
        <div class="alert alert-danger">
          <strong>Tidak dapat terhubung ke MikroTik</strong>
          (<?= htmlspecialchars($selected_router['host']) ?>).
          Periksa koneksi dan kredensial router.
        </div>

        <?php else: ?>

        <!-- INFO ROUTER -->
        <div class="card mb-3">
          <div class="card-body py-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <div>
                <span class="text-muted small">Router</span>
                <div class="fw-semibold"><?= htmlspecialchars($selected_router['name']) ?></div>
              </div>
              <div>
                <span class="text-muted small">Host</span>
                <div><code><?= htmlspecialchars($selected_router['host']) ?></code></div>
              </div>
              <div>
                <span class="text-muted small">POP</span>
                <div><?= htmlspecialchars($selected_router['pop_name']) ?></div>
              </div>
              <div class="ms-auto">
                <span class="badge bg-success-lt">
                  <span class="status-dot status-dot-animated status-dot-green me-1"></span>
                  Terhubung
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- TABEL PPPoE SERVERS -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              Daftar PPPoE Server
              <span class="badge bg-blue-lt ms-2"><?= count($servers) ?></span>
            </h3>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr>
                  <th>Service Name</th>
                  <th>Interface</th>
                  <th>Authentication</th>
                  <th>Max MTU / MRU</th>
                  <th>Keepalive</th>
                  <th>Status</th>
                  <th class="w-1">Aksi</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($servers)): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">
                  Belum ada PPPoE Server. Klik <strong>Tambah Server</strong> untuk menambahkan.
                </td>
              </tr>
              <?php else: foreach ($servers as $s):
                $disabled  = ($s['disabled'] ?? 'false') === 'true';
                $auth_list = array_map('trim', explode(',', $s['authentication'] ?? ''));
              ?>
              <tr class="<?= $disabled ? 'opacity-50' : '' ?>">
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($s['service-name'] ?? '-') ?></div>
                  <div class="text-muted" style="font-size:.7rem;">ID: <?= htmlspecialchars($s['.id'] ?? '-') ?></div>
                </td>
                <td>
                  <span class="badge bg-azure-lt">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                         stroke-width="2" stroke="currentColor" fill="none" class="me-1">
                      <rect x="2" y="6" width="20" height="12" rx="2"/>
                      <path d="M12 12h.01"/><path d="M17 12h.01"/><path d="M7 12h.01"/>
                    </svg>
                    <?= htmlspecialchars($s['interface'] ?? '-') ?>
                  </span>
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($auth_list as $a):
                      if (!$a) continue;
                      $color = match(strtolower($a)) {
                          'mschap2' => 'green', 'mschap1' => 'teal',
                          'chap'    => 'blue',  'pap'     => 'orange',
                          default   => 'secondary'
                      };
                    ?>
                    <span class="badge bg-<?= $color ?>-lt"><?= strtoupper(htmlspecialchars($a)) ?></span>
                    <?php endforeach; ?>
                  </div>
                </td>
                <td class="text-muted small">
                  <?= htmlspecialchars($s['max-mtu'] ?? '1480') ?> /
                  <?= htmlspecialchars($s['max-mru'] ?? '1480') ?>
                </td>
                <td class="text-muted small">
                  <?= htmlspecialchars($s['keepalive-timeout'] ?? '10') ?>s
                </td>
                <td>
                  <?php if ($disabled): ?>
                  <span class="badge bg-secondary-lt">Disabled</span>
                  <?php else: ?>
                  <span class="badge bg-success-lt">Enabled</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="dropdown">
                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle"
                            data-bs-toggle="dropdown" type="button">Aksi</button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                      <li>
                        <button type="button" class="dropdown-item"
                                onclick='openEdit(<?= htmlspecialchars(json_encode([
                                    '.id'            => $s['.id'],
                                    'service-name'   => $s['service-name'] ?? '',
                                    'interface'      => $s['interface'] ?? '',
                                    'authentication' => $s['authentication'] ?? '',
                                    'max-mtu'        => $s['max-mtu'] ?? '',
                                    'max-mru'        => $s['max-mru'] ?? '',
                                    'keepalive-timeout' => $s['keepalive-timeout'] ?? '',
                                ]), ENT_QUOTES) ?>)'>
                          <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16"
                               viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                          </svg>
                          Edit
                        </button>
                      </li>
                      <li>
                        <form method="POST" class="d-inline">
                          <input type="hidden" name="action" value="toggle">
                          <input type="hidden" name="mikrotik_id" value="<?= htmlspecialchars($s['.id']) ?>">
                          <input type="hidden" name="current_disabled" value="<?= $disabled ? 'true' : 'false' ?>">
                          <input type="hidden" name="router_id" value="<?= $selected_router_id ?>">
                          <button type="submit" class="dropdown-item <?= $disabled ? 'text-success' : 'text-warning' ?>">
                            <?= $disabled ? 'Enable' : 'Disable' ?>
                          </button>
                        </form>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <button type="button" class="dropdown-item text-danger"
                                onclick="deleteServer('<?= htmlspecialchars($s['.id'], ENT_QUOTES) ?>',
                                                       '<?= htmlspecialchars($s['service-name'] ?? '-', ENT_QUOTES) ?>')">
                          <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16"
                               viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6l-1 14H6L5 6"/>
                            <path d="M10 11v6m4-6v6"/><path d="M9 6V4h6v2"/>
                          </svg>
                          Hapus
                        </button>
                      </li>
                    </ul>
                  </div>
                </td>
              </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL CREATE / EDIT ══════════════════════════════════════════════════ -->
<div class="modal modal-blur fade" id="modalServer" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <form method="POST" id="server-form">
        <input type="hidden" name="action" id="f-action" value="create">
        <input type="hidden" name="mikrotik_id" id="f-id">
        <input type="hidden" name="router_id" value="<?= $selected_router_id ?>">

        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Tambah PPPoE Server</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <!-- Service Name -->
            <div class="col-12">
              <label class="form-label required">Service Name</label>
              <input type="text" name="service_name" id="f-service-name" class="form-control"
                     placeholder="pppoe-server" required maxlength="64">
              <small class="text-muted">Nama unik identifikasi server (terlihat di client PPPoE).</small>
            </div>

            <!-- Interface -->
            <div class="col-12">
              <label class="form-label required">Interface</label>
              <select name="interface" id="f-interface" class="form-select" required>
                <option value="">-- Pilih Interface --</option>
                <?php foreach ($ifaces as $iface): ?>
                <option value="<?= htmlspecialchars($iface) ?>"><?= htmlspecialchars($iface) ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Interface fisik atau bridge tempat client PPPoE terhubung.</small>
            </div>

            <!-- Authentication -->
            <div class="col-12">
              <label class="form-label required">Authentication</label>
              <div class="card card-sm">
                <div class="card-body py-2">
                  <div class="row g-2">
                    <?php foreach ($AUTH_OPTIONS as $key => $label): ?>
                    <div class="col-6">
                      <label class="form-check">
                        <input class="form-check-input auth-check" type="checkbox"
                               name="authentication[]" value="<?= $key ?>"
                               id="auth-<?= $key ?>" checked>
                        <span class="form-check-label">
                          <strong><?= $label ?></strong>
                          <?php if ($key === 'mschap2'): ?>
                          <span class="badge bg-green-lt ms-1">Direkomendasikan</span>
                          <?php elseif ($key === 'pap'): ?>
                          <span class="badge bg-red-lt ms-1">Tidak Aman</span>
                          <?php endif; ?>
                        </span>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="text-muted mt-2" style="font-size:.7rem;" id="auth-preview"></div>
                </div>
              </div>
            </div>

            <!-- Advanced: MTU/MRU/Keepalive -->
            <div class="col-12">
              <a class="text-muted small" data-bs-toggle="collapse" href="#advanced-opts" role="button">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                     stroke-width="2" stroke="currentColor" fill="none" class="me-1">
                  <circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>
                </svg>
                Pengaturan Lanjutan (MTU / MRU / Keepalive)
              </a>
              <div class="collapse mt-2" id="advanced-opts">
                <div class="row g-2">
                  <div class="col-4">
                    <label class="form-label">Max MTU</label>
                    <input type="number" name="max_mtu" id="f-mtu" class="form-control form-control-sm"
                           placeholder="1480" min="64" max="65535">
                  </div>
                  <div class="col-4">
                    <label class="form-label">Max MRU</label>
                    <input type="number" name="max_mru" id="f-mru" class="form-control form-control-sm"
                           placeholder="1480" min="64" max="65535">
                  </div>
                  <div class="col-4">
                    <label class="form-label">Keepalive (detik)</label>
                    <input type="number" name="keepalive" id="f-keepalive" class="form-control form-control-sm"
                           placeholder="10" min="0" max="3600">
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary" id="submit-btn">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ MODAL DELETE CONFIRM ═════════════════════════════════════════════════ -->
<div class="modal modal-blur fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="router_id" value="<?= $selected_router_id ?>">
        <input type="hidden" name="mikrotik_id" id="del-id">
        <div class="modal-body">
          <div class="modal-title mb-2">Hapus PPPoE Server</div>
          <p class="text-muted">
            Yakin hapus server <strong id="del-name"></strong>?
            Semua koneksi PPPoE yang menggunakan server ini akan terputus.
          </p>
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
// ── Dropdown: fixed strategy ──────────────────────────────────────────────────
document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
    new bootstrap.Dropdown(el, { popperConfig: { strategy: 'fixed' } });
});

// ── Auth preview ──────────────────────────────────────────────────────────────
function updateAuthPreview() {
    const checked = [...document.querySelectorAll('.auth-check:checked')].map(el => el.value.toUpperCase());
    const el = document.getElementById('auth-preview');
    el.textContent = checked.length
        ? 'Akan dikirim ke MikroTik: ' + checked.join(',')
        : '⚠ Pilih minimal satu metode autentikasi.';
    el.className = checked.length ? 'text-muted mt-2' : 'text-danger mt-2';
}
document.querySelectorAll('.auth-check').forEach(el => el.addEventListener('change', updateAuthPreview));
updateAuthPreview();

// ── CREATE ────────────────────────────────────────────────────────────────────
function openCreate() {
    document.getElementById('f-action').value       = 'create';
    document.getElementById('f-id').value           = '';
    document.getElementById('f-service-name').value = '';
    document.getElementById('f-interface').value    = '';
    document.getElementById('f-mtu').value          = '';
    document.getElementById('f-mru').value          = '';
    document.getElementById('f-keepalive').value    = '';
    document.querySelectorAll('.auth-check').forEach(el => el.checked = true);
    document.getElementById('modal-title').textContent = 'Tambah PPPoE Server';
    document.getElementById('submit-btn').textContent  = 'Simpan';
    updateAuthPreview();
}

// ── EDIT ──────────────────────────────────────────────────────────────────────
function openEdit(s) {
    document.getElementById('f-action').value       = 'update';
    document.getElementById('f-id').value           = s['.id'];
    document.getElementById('f-service-name').value = s['service-name'] || '';
    document.getElementById('f-interface').value    = s['interface']    || '';
    document.getElementById('f-mtu').value          = s['max-mtu']      || '';
    document.getElementById('f-mru').value          = s['max-mru']      || '';
    document.getElementById('f-keepalive').value    = s['keepalive-timeout'] || '';

    const active = (s['authentication'] || '').split(',').map(a => a.trim().toLowerCase());
    document.querySelectorAll('.auth-check').forEach(el => {
        el.checked = active.includes(el.value);
    });

    document.getElementById('modal-title').textContent = 'Edit PPPoE Server — ' + (s['service-name'] || '');
    document.getElementById('submit-btn').textContent  = 'Simpan Perubahan';
    updateAuthPreview();
    new bootstrap.Modal(document.getElementById('modalServer')).show();
}

// ── DELETE ────────────────────────────────────────────────────────────────────
function deleteServer(id, name) {
    document.getElementById('del-id').textContent   = id;
    document.getElementById('del-id').value         = id;
    document.getElementById('del-name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
</body>
</html>

