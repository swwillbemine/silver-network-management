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
            <div class="page-pretitle">Sistem</div>
            <h2 class="page-title">Manajemen User</h2>
          </div>
          <?php if ($is_superadmin): ?>
          <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUser">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14M5 12h14"/></svg>
              Tambah User
            </button>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">

        <?php if (!empty($msg_text)): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible mb-3" role="alert">
          <?= htmlspecialchars($msg_text) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ROLE SUMMARY CARDS -->
        <div class="row g-3 mb-3">
          <?php foreach ($role_labels as $role => $label):
            $cnt = count(array_filter($users, fn($u) => $u['role'] === $role));
          ?>
          <div class="col-6 col-sm-3">
            <div class="card card-sm">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-auto">
                    <span class="avatar bg-<?= $role_colors[$role] ?>-lt text-<?= $role_colors[$role] ?>"><?= strtoupper(substr($label, 0, 1)) ?></span>
                  </div>
                  <div class="col">
                    <div class="fw-semibold"><?= $cnt ?></div>
                    <div class="text-muted small"><?= $label ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- USER TABLE -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Daftar User (<?= count($users) ?>)</h3>
            <div class="card-options">
              <input type="text" id="search-user" class="form-control form-control-sm" placeholder="Cari user..." style="width:180px;">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr>
                  <th>Nama</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Telepon</th>
                  <th>Role</th>
                  <th>Terakhir Login</th>
                  <th>Status</th>
                  <th class="w-1">Aksi</th>
                </tr>
              </thead>
              <tbody id="user-table">
              <?php foreach ($users as $u): ?>
              <?php $is_self = $u['id'] == $current_user_id; ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm bg-<?= $role_colors[$u['role']] ?? 'secondary' ?>-lt text-<?= $role_colors[$u['role']] ?? 'secondary' ?>">
                      <?= mb_strtoupper(mb_substr($u['name'], 0, 1)) ?>
                    </span>
                    <div>
                      <div class="fw-semibold"><?= htmlspecialchars($u['name']) ?>
                        <?php if ($is_self): ?><span class="badge bg-blue-lt ms-1">Anda</span><?php endif; ?>
                      </div>
                      <?php if ($u['telegram_id']): ?>
                      <div class="text-muted small">@<?= htmlspecialchars($u['telegram_id']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                <td class="text-muted"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                <td class="text-muted"><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                <td>
                  <span class="badge bg-<?= $role_colors[$u['role']] ?? 'secondary' ?>-lt">
                    <?= $role_labels[$u['role']] ?? $u['role'] ?>
                  </span>
                </td>
                <td class="text-muted small">
                  <?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Belum pernah' ?>
                </td>
                <td>
                  <span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>-lt">
                    <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                  </span>
                </td>
                <td>
                  <div class="dropdown">
                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                      <?php
                      $safe_edit = htmlspecialchars(json_encode([
                          'id'          => $u['id'],
                          'name'        => $u['name'],
                          'email'       => $u['email'],
                          'phone'       => $u['phone'],
                          'telegram_id' => $u['telegram_id'],
                          'role'        => $u['role'],
                          'is_active'   => $u['is_active'],
                      ]), ENT_QUOTES);
                      $safe_rp   = htmlspecialchars(json_encode([
                          'id'       => $u['id'],
                          'name'     => $u['name'],
                          'username' => $u['username'],
                      ]), ENT_QUOTES);
                      $skip_old = ($is_superadmin && !$is_self) ? 'true' : 'false';
                      ?>
                      <?php if ($is_superadmin || $is_self): ?>
                      <li><button type="button" class="dropdown-item" onclick='editUser(<?= $safe_edit ?>)'>
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Data
                      </button></li>
                      <li><button type="button" class="dropdown-item" onclick='openResetPass(<?= $safe_rp ?>, <?= $skip_old ?>)'>
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Ganti Password
                      </button></li>
                      <?php endif; ?>
                      <?php if ($is_superadmin && !$is_self): ?>
                      <li><hr class="dropdown-divider"></li>
                      <li><button type="button" class="dropdown-item <?= $u['is_active'] ? 'text-warning' : 'text-success' ?>" onclick="toggleActive(<?= $u['id'] ?>, '<?= $u['is_active'] ? 'nonaktifkan' : 'aktifkan' ?>')">
                        <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                      </button></li>
                      <li><button type="button" class="dropdown-item text-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6m4-6v6"/><path d="M9 6V4h6v2"/></svg>
                        Hapus
                      </button></li>
                      <?php endif; ?>
                    </ul>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$users): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Belum ada user.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- INFO ROLES -->
        <div class="card mt-3">
          <div class="card-header"><h3 class="card-title">Keterangan Role</h3></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-sm-3">
                <div class="fw-semibold text-purple">Super Admin</div>
                <div class="text-muted small">Akses penuh: semua fitur, manajemen user, pengaturan sistem.</div>
              </div>
              <div class="col-sm-3">
                <div class="fw-semibold text-blue">Admin</div>
                <div class="text-muted small">Kelola pelanggan, tagihan, router, infrastruktur. Tidak bisa kelola user lain.</div>
              </div>
              <div class="col-sm-3">
                <div class="fw-semibold text-cyan">Teknisi</div>
                <div class="text-muted small">Akses infrastruktur, router, node, koneksi. Tanpa akses keuangan.</div>
              </div>
              <div class="col-sm-3">
                <div class="fw-semibold text-orange">Kasir</div>
                <div class="text-muted small">Kelola tagihan dan pembayaran saja.</div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL CREATE/EDIT USER ══════════════════════════════════════════════ -->
<div class="modal modal-blur fade" id="modalUser" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="user-form">
        <input type="hidden" name="action" id="form-action" value="create">
        <input type="hidden" name="id" id="form-id">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Tambah User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required">Nama Lengkap</label>
              <input type="text" name="name" id="f-name" class="form-control" required>
            </div>
            <div class="col-md-6" id="username-field">
              <label class="form-label required">Username</label>
              <input type="text" name="username" id="f-username" class="form-control" required
                pattern="[a-zA-Z0-9_\-]+" title="Hanya huruf, angka, underscore, dan tanda minus">
              <small class="text-muted">Tidak dapat diubah setelah dibuat.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="f-email" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">No. Telepon</label>
              <input type="text" name="phone" id="f-phone" class="form-control" placeholder="08xx">
            </div>
            <div class="col-md-6">
              <label class="form-label">Telegram Username</label>
              <div class="input-group">
                <span class="input-group-text">@</span>
                <input type="text" name="telegram_id" id="f-telegram" class="form-control" placeholder="username">
              </div>
            </div>
            <?php if ($is_superadmin): ?>
            <div class="col-md-3">
              <label class="form-label required">Role</label>
              <select name="role" id="f-role" class="form-select">
                <option value="admin">Admin</option>
                <option value="teknisi">Teknisi</option>
                <option value="kasir">Kasir</option>
                <option value="superadmin">Super Admin</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="f-active" value="1" checked>
                <label class="form-check-label" for="f-active">Aktif</label>
              </div>
            </div>
            <?php endif; ?>
            <div class="col-12" id="password-field">
              <label class="form-label required" id="pass-label">Password</label>
              <div class="input-group">
                <input type="password" name="password" id="f-password" class="form-control" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVis('f-password')">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10 12a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M21 12c-2.4 4-5.4 6-9 6s-6.6-2-9-6c2.4-4 5.4-6 9-6s6.6 2 9 6"/></svg>
                </button>
              </div>
              <small class="text-muted" id="pass-hint">Minimal 6 karakter.</small>
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

<!-- ══ MODAL RESET PASSWORD ═══════════════════════════════════════════════ -->
<div class="modal modal-blur fade" id="modalResetPass" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="id" id="rp-id">
        <div class="modal-header">
          <h5 class="modal-title">Ganti Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="text-muted mb-3 small">User: <strong id="rp-name"></strong></div>

          <div class="mb-3" id="rp-old-field">
            <label class="form-label required">Password Lama</label>
            <div class="input-group">
              <input type="password" name="old_password" id="rp-old" class="form-control">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVis('rp-old')">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10 12a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M21 12c-2.4 4-5.4 6-9 6s-6.6-2-9-6c2.4-4 5.4-6 9-6s6.6 2 9 6"/></svg>
              </button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label required">Password Baru</label>
            <div class="input-group">
              <input type="password" name="new_password" id="rp-new" class="form-control" required minlength="6">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVis('rp-new')">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10 12a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M21 12c-2.4 4-5.4 6-9 6s-6.6-2-9-6c2.4-4 5.4-6 9-6s6.6 2 9 6"/></svg>
              </button>
            </div>
            <!-- Password strength -->
            <div class="progress mt-1" style="height:3px;" id="strength-bar-wrap">
              <div class="progress-bar" id="strength-bar" style="width:0;"></div>
            </div>
            <small id="strength-label" class="text-muted"></small>
          </div>
          <div class="mb-3">
            <label class="form-label required">Konfirmasi Password Baru</label>
            <input type="password" name="confirm_password" id="rp-confirm" class="form-control" required>
            <small id="confirm-match" class="text-muted"></small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-warning">Simpan Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ TOGGLE / DELETE FORMS ═══════════════════════════════════════════════ -->
<form method="POST" id="toggle-form" style="display:none;">
  <input type="hidden" name="action" value="toggle_active">
  <input type="hidden" name="id" id="toggle-id">
</form>

<div class="modal modal-blur fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="del-id">
        <div class="modal-body">
          <p>Yakin hapus user <strong id="del-name"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
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
// ── HELPERS ──────────────────────────────────────────────────────────────────
function togglePasswordVis(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}

// ── CREATE / EDIT USER ───────────────────────────────────────────────────────
function editUser(u) {
  document.getElementById('form-action').value = 'update';
  document.getElementById('form-id').value = u.id;
  document.getElementById('f-name').value = u.name || '';
  document.getElementById('f-email').value = u.email || '';
  document.getElementById('f-phone').value = u.phone || '';
  document.getElementById('f-telegram').value = u.telegram_id || '';

  const roleEl = document.getElementById('f-role');
  if (roleEl) roleEl.value = u.role || 'admin';

  const activeEl = document.getElementById('f-active');
  if (activeEl) activeEl.checked = u.is_active == 1;

  // Hide username and password fields when editing
  document.getElementById('username-field').classList.add('d-none');
  document.getElementById('password-field').classList.add('d-none');
  document.getElementById('f-password').required = false;
  document.getElementById('f-username').required = false;

  document.getElementById('modal-title').textContent = 'Edit User — ' + u.name;
  document.getElementById('submit-btn').textContent = 'Simpan Perubahan';
  new bootstrap.Modal(document.getElementById('modalUser')).show();
}

document.getElementById('modalUser').addEventListener('hidden.bs.modal', function() {
  document.getElementById('form-action').value = 'create';
  document.getElementById('form-id').value = '';
  document.getElementById('modal-title').textContent = 'Tambah User';
  document.getElementById('submit-btn').textContent = 'Simpan';
  document.getElementById('username-field').classList.remove('d-none');
  document.getElementById('password-field').classList.remove('d-none');
  document.getElementById('f-password').required = true;
  document.getElementById('f-username').required = true;
  this.querySelector('form').reset();
});

// ── RESET PASSWORD ───────────────────────────────────────────────────────────
function openResetPass(u, skipOld) {
  document.getElementById('rp-id').value = u.id;
  document.getElementById('rp-name').textContent = u.name + ' (' + u.username + ')';

  const oldField = document.getElementById('rp-old-field');
  const oldInput = document.getElementById('rp-old');

  if (skipOld) {
    oldField.classList.add('d-none');
    oldInput.removeAttribute('required');
  } else {
    oldField.classList.remove('d-none');
    oldInput.setAttribute('required', 'required');
  }

  document.getElementById('rp-new').value = '';
  document.getElementById('rp-confirm').value = '';
  document.getElementById('strength-bar').style.width = '0';
  document.getElementById('strength-label').textContent = '';
  document.getElementById('confirm-match').textContent = '';

  new bootstrap.Modal(document.getElementById('modalResetPass')).show();
}

// Password strength meter
document.getElementById('rp-new').addEventListener('input', function() {
  const v = this.value;
  let score = 0;
  if (v.length >= 6)  score++;
  if (v.length >= 10) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^a-zA-Z0-9]/.test(v)) score++;

  const colors = ['bg-danger','bg-danger','bg-warning','bg-info','bg-success','bg-success'];
  const labels = ['','Sangat Lemah','Lemah','Cukup','Kuat','Sangat Kuat'];
  const bar = document.getElementById('strength-bar');
  bar.style.width = (score * 20) + '%';
  bar.className = 'progress-bar ' + (colors[score] || '');
  document.getElementById('strength-label').textContent = labels[score] || '';

  checkConfirm();
});

document.getElementById('rp-confirm').addEventListener('input', checkConfirm);

function checkConfirm() {
  const n = document.getElementById('rp-new').value;
  const c = document.getElementById('rp-confirm').value;
  const el = document.getElementById('confirm-match');
  if (!c) { el.textContent = ''; return; }
  if (n === c) {
    el.textContent = 'Password cocok.';
    el.className = 'text-success small';
  } else {
    el.textContent = 'Password tidak cocok.';
    el.className = 'text-danger small';
  }
}

// ── TOGGLE / DELETE ──────────────────────────────────────────────────────────
function toggleActive(id, action) {
  if (!confirm('Konfirmasi ' + action + ' user ini?')) return;
  document.getElementById('toggle-id').value = id;
  document.getElementById('toggle-form').submit();
}

function deleteUser(id, name) {
  document.getElementById('del-id').value = id;
  document.getElementById('del-name').textContent = name;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// ── DROPDOWN: fixed strategy agar tidak terpotong .table-responsive ──────────
document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(el) {
  new bootstrap.Dropdown(el, { popperConfig: { strategy: 'fixed' } });
});

// ── TABLE SEARCH ─────────────────────────────────────────────────────────────
document.getElementById('search-user').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#user-table tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>

