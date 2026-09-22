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
            <h2 class="page-title">IP Pool</h2>
          </div>
          <div class="col-auto d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
              <select name="router_id" class="form-select form-select-sm" onchange="this.form.submit()" style="width:200px;">
                <?php foreach ($routers as $r): ?>
                <option value="<?= $r['id'] ?>" <?= $r['id'] == $selected_router_id ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
            <?php if ($client): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalPool">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                   stroke-width="2" stroke="currentColor" fill="none">
                <path d="M12 5v14M5 12h14"/>
              </svg>  
              Tambah Pool
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="page-body">
      <div class="container-xl">
        <?php if (!empty($msg_text)): ?>
        <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3" role="alert">
          <?= htmlspecialchars($msg_text) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!$selected_router): ?>
        <div class="alert alert-warning">Tidak ada router terdaftar. <a href="<?= BASE_URL ?>/routers">Tambah router</a> terlebih dahulu.</div>
        <?php elseif (!$client): ?>
        <div class="alert alert-danger">Tidak dapat terhubung ke router <strong><?= htmlspecialchars($selected_router['name']) ?></strong> (<?= $selected_router['host'] ?>). Periksa koneksi dan kredensial.</div>
        <?php else: ?>

        <!-- ROUTER INFO -->
        <div class="alert alert-info d-flex gap-3 mb-3">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon text-info flex-shrink-0" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div>Router: <strong><?= htmlspecialchars($selected_router['name']) ?></strong> &mdash; <?= htmlspecialchars($selected_router['host']) ?> &mdash; <?= htmlspecialchars($selected_router['pop_name']) ?></div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">IP Pools di <?= htmlspecialchars($selected_router['name']) ?> (<?= count($pools) ?>)</h3>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nama Pool</th>
                  <th>Range IP</th>
                  <th>Next Pool</th>
                  <th class="w-1">Aksi</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($pools as $pool): ?>
              <tr>
                <td class="text-muted small"><code><?= htmlspecialchars($pool['.id'] ?? '-') ?></code></td>
                <td class="fw-semibold"><?= htmlspecialchars($pool['name'] ?? '-') ?></td>
                <td><code><?= htmlspecialchars($pool['ranges'] ?? '-') ?></code></td>
                <td class="text-muted"><?= htmlspecialchars($pool['next-pool'] ?? 'none') ?></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-ghost-secondary" onclick="editPool(<?= htmlspecialchars(json_encode($pool)) ?>)">Edit</button>
                    <button class="btn btn-ghost-danger" onclick="deletePool('<?= addslashes($pool['.id'] ?? '') ?>', '<?= addslashes($pool['name'] ?? '') ?>')">Hapus</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$pools): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada IP Pool di router ini.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($client): ?>
<!-- MODAL POOL -->
<div class="modal modal-blur fade" id="modalPool" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="form-action" value="create">
        <input type="hidden" name="mikrotik_id" id="form-mid">
        <input type="hidden" name="router_id" value="<?= $selected_router_id ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Tambah IP Pool</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label required">Nama Pool</label>
            <input type="text" name="name" id="f-name" class="form-control" required placeholder="cth: pool-pppoe-1">
          </div>
          <div class="mb-3">
            <label class="form-label required">Range IP</label>
            <input type="text" name="ranges" id="f-ranges" class="form-control" required placeholder="cth: 192.168.10.10-192.168.10.254">
            <small class="text-muted">Format: IP_awal-IP_akhir atau IP/prefix</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan ke MikroTik</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE FORM -->
<form method="POST" id="delete-form" style="display:none;">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="mikrotik_id" id="del-mid">
  <input type="hidden" name="router_id" value="<?= $selected_router_id ?>">
</form>

<div class="modal modal-blur fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body"><p>Hapus pool <strong id="del-name"></strong> dari MikroTik?</p></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" onclick="document.getElementById('delete-form').submit()">Hapus</button>
      </div>
    </div>
  </div>
</div>

<script>
function editPool(p) {
  document.getElementById('form-action').value = 'update';
  document.getElementById('form-mid').value = p['.id'] || '';
  document.getElementById('f-name').value = p['name'] || '';
  document.getElementById('f-ranges').value = p['ranges'] || '';
  document.getElementById('modal-title').textContent = 'Edit IP Pool';
  new bootstrap.Modal(document.getElementById('modalPool')).show();
}
function deletePool(id, name) {
  document.getElementById('del-mid').value = id;
  document.getElementById('del-name').textContent = name;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
document.getElementById('modalPool').addEventListener('hidden.bs.modal', function() {
  document.getElementById('form-action').value = 'create';
  document.getElementById('form-mid').value = '';
  document.getElementById('modal-title').textContent = 'Tambah IP Pool';
  this.querySelector('form').reset();
});
</script>
<?php endif; ?>
</body>
</html>

