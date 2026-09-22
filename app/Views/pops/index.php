<?php
$title = 'Point of Presence (POP)';
\App\Core\View::header(['title' => $title]);
?>
<body>
<div class="page">
  <?php \App\Core\View::sidebar(); ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle">Infrastruktur</div>
            <h2 class="page-title">Point of Presence (POP)</h2>
          </div>
          <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPop">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14M5 12h14"/></svg>
              Tambah POP
            </button>
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

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Daftar POP (<?= count($pops) ?>)</h3>
            <div class="card-options">
              <input type="text" id="search-pop" class="form-control form-control-sm" placeholder="Cari POP...">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nama POP</th>
                  <th>Node</th>
                  <th>Backup Power</th>
                  <th>Kapasitas Baterai</th>
                  <th>Tgl Instalasi</th>
                  <th>Router</th>
                  <th class="w-1">Aksi</th>
                </tr>
              </thead>
              <tbody id="pop-table">
              <?php foreach ($pops as $i => $p): ?>
              <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td><div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div></td>
                <td>
                  <div><?= htmlspecialchars($p['node_name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($p['node_address']) ?></div>
                </td>
                <td>
                  <?php $bp = $p['backup_power']; ?>
                  <span class="badge bg-<?= $bp==='none'?'secondary':($bp==='ups'?'blue':($bp==='genset'?'orange':'green')) ?>-lt">
                    <?= $backup_labels[$bp] ?? $bp ?>
                  </span>
                </td>
                <td class="text-muted"><?= htmlspecialchars($p['battery_capacity']) ?: '-' ?></td>
                <td class="text-muted"><?= $p['installation_date'] ? date('d M Y', strtotime($p['installation_date'])) : '-' ?></td>
                <td><span class="badge bg-blue-lt"><?= $p['router_count'] ?></span></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-ghost-secondary" onclick="editPop(<?= htmlspecialchars(json_encode($p)) ?>)">Edit</button>
                    <button class="btn btn-ghost-danger" onclick="deletePop(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>')">Hapus</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$pops): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Belum ada POP terdaftar.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL POP -->
<div class="modal modal-blur fade" id="modalPop" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="form-action" value="create">
        <input type="hidden" name="id" id="form-id">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Tambah POP</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label required">Node</label>
            <select name="node_id" id="f-node" class="form-select" required>
              <option value="">-- Pilih Node --</option>
              <?php foreach ($nodes as $n): ?>
              <option value="<?= $n['id'] ?>"><?= htmlspecialchars($n['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label required">Nama POP</label>
            <input type="text" name="name" id="f-name" class="form-control" required placeholder="cth: POP Pasar Baru">
          </div>
          <div class="mb-3">
            <label class="form-label">Backup Power</label>
            <select name="backup_power" id="f-backup" class="form-select">
              <option value="none">Tidak Ada</option>
              <option value="ups">UPS</option>
              <option value="genset">Genset</option>
              <option value="solar">Solar Panel</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Kapasitas Baterai</label>
            <input type="text" name="battery_capacity" id="f-battery" class="form-control" placeholder="cth: 100Ah">
          </div>
          <div class="mb-3">
            <label class="form-label">Tanggal Instalasi</label>
            <input type="date" name="installation_date" id="f-install-date" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal modal-blur fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="del-id">
        <div class="modal-body">
          <p>Hapus POP <strong id="del-name"></strong>?</p>
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
function editPop(p) {
  document.getElementById('form-action').value = 'update';
  document.getElementById('form-id').value = p.id;
  document.getElementById('f-node').value = p.node_id;
  document.getElementById('f-name').value = p.name || '';
  document.getElementById('f-backup').value = p.backup_power || 'none';
  document.getElementById('f-battery').value = p.battery_capacity || '';
  document.getElementById('f-install-date').value = p.installation_date || '';
  document.getElementById('modal-title').textContent = 'Edit POP';
  new bootstrap.Modal(document.getElementById('modalPop')).show();
}
function deletePop(id, name) {
  document.getElementById('del-id').value = id;
  document.getElementById('del-name').textContent = name;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
document.getElementById('modalPop').addEventListener('hidden.bs.modal', function() {
  document.getElementById('form-action').value = 'create';
  document.getElementById('form-id').value = '';
  document.getElementById('modal-title').textContent = 'Tambah POP';
  this.querySelector('form').reset();
});
document.getElementById('search-pop').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#pop-table tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>

