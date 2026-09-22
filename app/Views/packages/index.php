<?php
$title = 'Paket Internet';
\App\Core\View::header(['title' => $title]);

if (!function_exists('bpsToDisplay')) {
    function bpsToDisplay(int $bps): array {
        if ($bps >= 1_000_000_000 && $bps % 1_000_000_000 === 0)
            return [(int)($bps/1_000_000_000), 'G'];
        if ($bps >= 1_000_000 && $bps % 1_000_000 === 0)
            return [(int)($bps/1_000_000), 'M'];
        if ($bps >= 1_000 && $bps % 1_000 === 0)
            return [(int)($bps/1_000), 'k'];
        return [round($bps/1_000, 2), 'k'];
    }
}

$pkg_by_router = [];
foreach ($packages as $p) {
    $pkg_by_router[$p['mk_id']]['name']  = $p['router_name'];
    $pkg_by_router[$p['mk_id']]['items'][] = $p;
}
?>
<body>
<div class="page">
  <?php \App\Core\View::sidebar(); ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle">Pelanggan</div>
            <h2 class="page-title">Paket Internet</h2>
          </div>
          <div class="col-auto">
            <a href="<?= BASE_URL ?>/packages/import" class="btn btn-ghost-secondary me-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/><polyline points="7 11 12 16 17 11"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
              Import Paket
            </a>
            <a href="<?= BASE_URL ?>/packages/create" class="btn btn-primary">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14M5 12h14"/></svg>
              Tambah Paket
            </a>
          </div>
        </div>
      </div>
    </div>
    <div class="page-body">
      <div class="container-xl">
        <?php if (!empty($msg_text)): ?>
        <div class="alert alert-<?= htmlspecialchars($msg_type) ?> alert-dismissible mb-3">
          <?= htmlspecialchars($msg_text) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header d-flex align-items-center gap-2 flex-wrap">
            <h3 class="card-title me-2">
              Daftar Paket
              <span class="badge bg-secondary-lt ms-1" id="pkg-count"><?= count($packages) ?></span>
            </h3>
            <!-- Router filter dropdown -->
            <select id="filter-router" class="form-select form-select-sm" style="width:auto;min-width:180px;">
              <option value="all">Semua Router (<?= count($packages) ?>)</option>
              <?php foreach ($pkg_by_router as $mk_id => $grp): ?>
              <option value="<?= $mk_id ?>">
                <?= htmlspecialchars($grp['name']) ?> (<?= count($grp['items']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="search-pkg" class="form-control form-control-sm ms-auto" placeholder="Cari nama paket..." style="width:200px;">
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr>
                  <th>Nama Paket</th>
                  <th>Router</th>
                  <th>Harga/bln</th>
                  <th>Rate Limit</th>
                  <th>PPP Profile</th>
                  <th>Status</th>
                  <th class="w-1">Aksi</th>
                </tr>
              </thead>
              <tbody id="pkg-table">
              <?php foreach ($packages as $p):
                [$up_v,$up_u] = bpsToDisplay((int)$p['tx_max_limit']);
                [$dn_v,$dn_u] = bpsToDisplay((int)$p['rx_max_limit']);
              ?>
              <tr data-mk="<?= $p['mk_id'] ?>" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>">
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($p['description']??'') ?></div>
                </td>
                <td>
                  <span class="badge bg-azure-lt"><?= htmlspecialchars($p['router_name']) ?></span>
                </td>
                <td class="fw-semibold">Rp <?= number_format($p['price'],0,',','.') ?></td>
                <td>
                  <code class="text-primary">↓<?= $dn_v.$dn_u ?></code>
                  <span class="text-muted mx-1">/</span>
                  <code class="text-secondary">↑<?= $up_v.$up_u ?></code>
                </td>
                <td>
                  <code class="small text-muted"><?= htmlspecialchars($p['mikrotik_profile_name']) ?></code>
                  <?php if (!empty($p['dns_server1'])): ?>
                  <div class="text-muted mt-1" style="font-size:.7rem;">
                    <span title="DNS">⬡</span>
                    <?= htmlspecialchars($p['dns_server1']) ?>
                    <?php if (!empty($p['dns_server2'])): ?>
                    / <?= htmlspecialchars($p['dns_server2']) ?>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                </td>
                <td><span class="badge bg-<?= $p['is_active']?'success':'secondary' ?>-lt"><?= $p['is_active']?'Aktif':'Nonaktif' ?></span></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="<?= BASE_URL ?>/packages/<?= $p['id'] ?>/edit" class="btn btn-ghost-secondary">Edit</a>
                    <button class="btn btn-ghost-danger" onclick="deletePkg(<?= $p['id'] ?>,'<?= addslashes($p['name']) ?>')">Hapus</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$packages): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Belum ada paket.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer d-flex align-items-center justify-content-between" id="pkg-pagination-footer">
            <div class="text-muted small" id="pkg-pager-info"></div>
            <ul class="pagination m-0" id="pkg-pager"></ul>
          </div>
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
        <div class="modal-body"><p>Hapus paket <strong id="del-name"></strong>?</p></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Hapus</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function deletePkg(id, name) {
    document.getElementById('del-id').value = id;
    document.getElementById('del-name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

let activeRouter = 'all';
document.getElementById('search-pkg').addEventListener('input', filterPkgTable);
document.getElementById('filter-router').addEventListener('change', function() {
    activeRouter = this.value;
    filterPkgTable();
});

const PKG_PER_PAGE = 20;
let pkgCurrentPage = 1;

function getAllVisiblePkgRows() {
    const q  = document.getElementById('search-pkg').value.toLowerCase();
    const mk = (typeof activeRouter !== 'undefined') ? activeRouter : 'all';
    return Array.from(document.querySelectorAll('#pkg-table tr[data-mk]')).filter(tr => {
        const matchRouter = mk === 'all' || tr.dataset.mk === mk;
        const matchText   = !q || tr.dataset.name.includes(q) || tr.textContent.toLowerCase().includes(q);
        return matchRouter && matchText;
    });
}

function renderPkgPagination() {
    const rows       = getAllVisiblePkgRows();
    const total      = rows.length;
    const totalPages = Math.max(1, Math.ceil(total / PKG_PER_PAGE));
    pkgCurrentPage   = Math.min(pkgCurrentPage, totalPages);
    const start = (pkgCurrentPage - 1) * PKG_PER_PAGE;
    const end   = start + PKG_PER_PAGE;

    document.querySelectorAll('#pkg-table tr[data-mk]').forEach(tr => tr.style.display = 'none');
    rows.forEach((tr, i) => { tr.style.display = (i >= start && i < end) ? '' : 'none'; });

    const footer = document.getElementById('pkg-pagination-footer');
    const info   = document.getElementById('pkg-pager-info');
    const pager  = document.getElementById('pkg-pager');
    if (!footer) return;
    footer.style.removeProperty('display');
    info.innerHTML = `Menampilkan <strong>${Math.min(start+1,total)}–${Math.min(end,total)}</strong> dari <strong>${total}</strong> paket`;

    let html = '';
    const pd = pkgCurrentPage <= 1 ? 'disabled' : '';
    const nd = pkgCurrentPage >= totalPages ? 'disabled' : '';
    html += `<li class="page-item ${pd}"><a class="page-link" href="#" onclick="goPkgPage(${pkgCurrentPage-1});return false;"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="15 18 9 12 15 6"/></svg></a></li>`;
    let s = Math.max(1, pkgCurrentPage-2), e2 = Math.min(totalPages, pkgCurrentPage+2);
    if (s > 1) { html += `<li class="page-item"><a class="page-link" href="#" onclick="goPkgPage(1);return false;">1</a></li>`; if (s > 2) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`; }
    for (let i = s; i <= e2; i++) html += `<li class="page-item ${i===pkgCurrentPage?'active':''}"><a class="page-link" href="#" onclick="goPkgPage(${i});return false;">${i}</a></li>`;
    if (e2 < totalPages) { if (e2 < totalPages-1) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`; html += `<li class="page-item"><a class="page-link" href="#" onclick="goPkgPage(${totalPages});return false;">${totalPages}</a></li>`; }
    html += `<li class="page-item ${nd}"><a class="page-link" href="#" onclick="goPkgPage(${pkgCurrentPage+1});return false;"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="9 18 15 12 9 6"/></svg></a></li>`;
    pager.innerHTML = html;
}

function goPkgPage(p) { pkgCurrentPage = p; renderPkgPagination(); }

renderPkgPagination();
function filterPkgTable() {
    pkgCurrentPage = 1;
    renderPkgPagination();
    const cnt = document.getElementById('pkg-count');
    if (cnt) cnt.textContent = getAllVisiblePkgRows().length;
}
</script>
  </div>
</div>
</body>
</html>

