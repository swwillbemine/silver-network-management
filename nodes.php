<?php
// pages/nodes.php
require_once __DIR__ . '/config/bootstrap.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }


$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $pdo->prepare("INSERT INTO nodes (name,address,latitude,longitude,owner_name,owner_contact,description) VALUES (?,?,?,?,?,?,?)")
            ->execute([$_POST['name'],$_POST['address'],$_POST['latitude']?:null,$_POST['longitude']?:null,$_POST['owner_name'],$_POST['owner_contact'],$_POST['description']]);
        $msg = 'success:Node berhasil ditambahkan.';
    } elseif ($action === 'update') {
        $pdo->prepare("UPDATE nodes SET name=?,address=?,latitude=?,longitude=?,owner_name=?,owner_contact=?,description=? WHERE id=?")
            ->execute([$_POST['name'],$_POST['address'],$_POST['latitude']?:null,$_POST['longitude']?:null,$_POST['owner_name'],$_POST['owner_contact'],$_POST['description'],$_POST['id']]);
        $msg = 'success:Node berhasil diperbarui.';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM nodes WHERE id=?")->execute([$_POST['id']]);
        $msg = 'success:Node berhasil dihapus.';
    }
}

$nodes = $pdo->query("SELECT n.*, (SELECT COUNT(*) FROM customers c WHERE c.node_id=n.id) AS cust_count FROM nodes n ORDER BY n.name")->fetchAll(PDO::FETCH_ASSOC);
[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];
?>
<!doctype html>
<html lang="id">
<?php require_once __DIR__ . '/layout/header.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<body>
<div class="page">
  <?php require_once __DIR__ . '/layout/sidebar.php'; ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle">Infrastruktur</div>
            <h2 class="page-title">Node</h2>
          </div>
          <div class="col-auto">
            <button class="btn btn-primary" id="btn-add-node">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14M5 12h14"/></svg>
              Tambah Node
            </button>
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

        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Peta Lokasi Node</h3></div>
          <div id="overview-map-wrap" style="position:relative;">
          <div id="overview-map" style="height:380px;z-index:0;"></div>
        </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Daftar Node (<?= count($nodes) ?>)</h3>
            <div class="card-options">
              <input type="text" id="search-node" class="form-control form-control-sm" placeholder="Cari node...">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr><th>#</th><th>Nama Node</th><th>Alamat</th><th>Pemilik</th><th>Koordinat</th><th>Pelanggan</th><th class="w-1">Aksi</th></tr>
              </thead>
              <tbody id="node-table">
              <?php foreach ($nodes as $i => $n): ?>
              <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td><div class="fw-semibold"><?= htmlspecialchars($n['name']) ?></div></td>
                <td class="text-muted small"><?= htmlspecialchars($n['address']) ?></td>
                <td>
                  <div><?= htmlspecialchars($n['owner_name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($n['owner_contact']) ?></div>
                </td>
                <td class="text-muted small">
                  <?php if ($n['latitude'] && $n['longitude']): ?>
                    <?= number_format($n['latitude'],6) ?>, <?= number_format($n['longitude'],6) ?>
                  <?php else: ?>
                    <span class="text-danger small">Belum diset</span>
                  <?php endif; ?>
                </td>
                <td><span class="badge bg-blue-lt"><?= $n['cust_count'] ?></span></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-ghost-secondary" onclick="openEdit(<?= htmlspecialchars(json_encode($n)) ?>)">Edit</button>
                    <button class="btn btn-ghost-danger" onclick="openDelete(<?= $n['id'] ?>,'<?= addslashes($n['name']) ?>')">Hapus</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$nodes): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Belum ada node terdaftar.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php require_once __DIR__ . '/layout/footer.php'; ?>
  </div>
</div>

<!-- ══ MODAL NODE ═══════════════════════════════════════════════════════════ -->
<div class="modal modal-blur fade" id="modalNode" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="node-form">
        <input type="hidden" name="action" id="form-action" value="create">
        <input type="hidden" name="id" id="form-id">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Tambah Node</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required">Nama Node</label>
              <input type="text" name="name" id="f-name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Alamat</label>
              <input type="text" name="address" id="f-address" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nama Pemilik</label>
              <input type="text" name="owner_name" id="f-owner" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Kontak Pemilik</label>
              <input type="text" name="owner_contact" id="f-contact" class="form-control" placeholder="08xx">
            </div>
            <div class="col-12">
              <label class="form-label">Koordinat GPS</label>
              <div class="input-group">
                <input type="number" name="latitude" id="f-lat" class="form-control" step="any" placeholder="Latitude">
                <input type="number" name="longitude" id="f-lng" class="form-control" step="any" placeholder="Longitude">
                <!-- KEY FIX: use JS onclick, NOT data-bs-toggle nested modal -->
                <button type="button" class="btn btn-outline-primary" id="btn-open-picker">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><circle cx="12" cy="11" r="3"/><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z"/></svg>
                  Pilih di Peta
                </button>
              </div>
              <small class="text-muted">Atau klik "Pilih di Peta" untuk memilih secara visual</small>
            </div>
            <div class="col-12">
              <label class="form-label">Keterangan</label>
              <textarea name="description" id="f-desc" class="form-control" rows="2"></textarea>
            </div>
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

<!-- ══ MAP PICKER (standalone, NOT nested) ═════════════════════════════════ -->
<div class="modal modal-blur fade" id="mapPickerModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pilih Koordinat di Peta</h5>
        <!-- X button cancels without saving coords -->
        <button type="button" class="btn-close" id="picker-cancel-x"></button>
      </div>
      <div class="modal-body p-0">
        <div class="p-2 bg-light border-bottom d-flex gap-2">
          <input type="text" id="picker-search" class="form-control form-control-sm" placeholder="Ketik nama lokasi lalu Enter untuk cari...">
        </div>
        <div id="picker-map-wrap" style="position:relative;">
        <div id="picker-map" style="height:440px;"></div>
        </div>
        <div class="p-2 bg-light border-top small">
          Klik peta untuk memilih &mdash; Koordinat terpilih: <strong id="picker-coords" class="text-primary">-</strong>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost-secondary" id="picker-cancel-btn">Batal</button>
        <button type="button" class="btn btn-primary" id="picker-confirm-btn">Konfirmasi Koordinat</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ DELETE ════════════════════════════════════════════════════════════════ -->
<div class="modal modal-blur fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="del-id">
        <div class="modal-body"><p>Hapus node <strong id="del-name"></strong>? Data POP dan pelanggan terkait akan terpengaruh.</p></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Hapus</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* ── SilverNet Map Utilities ── */
const SNM_TILES = {
  osm: {
    url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    attr: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
    name: 'Peta Normal'
  },
  dark: {
    url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
    attr: '© <a href="https://openstreetmap.org">OSM</a> © <a href="https://carto.com">CARTO</a>',
    name: 'Mode Gelap'
  },
  satellite: {
    url: 'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
    attr: '© Google',
    name: 'Google Satellite',
    maxNativeZoom: 22
  }
};

function snmTileLayer(mode) {
  const t = SNM_TILES[mode];
  return L.tileLayer(t.url, {
    attribution: t.attr,
    maxZoom: 22,
    maxNativeZoom: t.maxNativeZoom || 19
  });
}

/**
 * Attach fullscreen + tile-switcher controls to a Leaflet map.
 * containerId = wrapper div that CONTAINS the map div.
 */
function snmInitControls(map, containerId, defaultMode) {
  defaultMode = defaultMode || 'osm';
  const wrapper = document.getElementById(containerId);
  // The actual Leaflet map div is the first child of the wrapper
  const mapDiv  = wrapper.querySelector('[id$="-map"]') || wrapper.firstElementChild;
  let currentLayer = snmTileLayer(defaultMode).addTo(map);
  let currentMode  = defaultMode;
  let isFullscreen = false;
  let origWrapStyle, origMapStyle;

  /* ── Tile switcher ── */
  const TileCtrl = L.Control.extend({
    onAdd: function() {
      const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
      div.style.cssText = 'background:#fff;padding:2px;display:flex;gap:2px;border-radius:4px;box-shadow:0 1px 5px rgba(0,0,0,.3);';
      const modes = [
        { key:'osm',       icon:'🗺️', label:'Normal'  },
        { key:'dark',      icon:'🌙', label:'Gelap'   },
        { key:'satellite', icon:'🛰️', label:'Satelit' },
      ];
      modes.forEach(m => {
        const btn = L.DomUtil.create('button', '', div);
        btn.title = m.label; btn.innerHTML = m.icon; btn.dataset.key = m.key;
        btn.style.cssText = 'border:none;background:transparent;cursor:pointer;font-size:16px;width:30px;height:30px;border-radius:3px;';
        btn.style.opacity = m.key === defaultMode ? '1' : '0.4';
        L.DomEvent.disableClickPropagation(btn);
        L.DomEvent.on(btn, 'click', () => {
          if (currentMode === m.key) return;
          map.removeLayer(currentLayer);
          currentLayer = snmTileLayer(m.key).addTo(map);
          currentMode  = m.key;
          div.querySelectorAll('button').forEach(b =>
            b.style.opacity = b.dataset.key === m.key ? '1' : '0.4');
        });
      });
      return div;
    }
  });
  new TileCtrl({ position: 'topright' }).addTo(map);

  /* ── Fullscreen ── */
  function enterFS() {
    origWrapStyle = wrapper.getAttribute('style') || '';
    origMapStyle  = mapDiv.getAttribute('style')  || '';
    // Make wrapper cover entire viewport
    wrapper.style.cssText = 'position:fixed!important;top:0!important;left:0!important;width:100vw!important;height:100vh!important;z-index:9999!important;margin:0!important;padding:0!important;';
    // Make inner map div fill wrapper completely
    mapDiv.style.cssText  = 'width:100%!important;height:100%!important;';
    isFullscreen = true;
    setTimeout(() => map.invalidateSize(), 50);
  }
  function exitFS() {
    wrapper.setAttribute('style', origWrapStyle);
    mapDiv.setAttribute('style',  origMapStyle);
    isFullscreen = false;
    setTimeout(() => map.invalidateSize(), 50);
  }

  const FsCtrl = L.Control.extend({
    onAdd: function() {
      const btn = L.DomUtil.create('button', 'leaflet-bar leaflet-control');
      btn.innerHTML = '⛶'; btn.title = 'Fullscreen';
      btn.style.cssText = 'background:#fff;border:none;cursor:pointer;font-size:18px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:4px;box-shadow:0 1px 5px rgba(0,0,0,.3);';
      L.DomEvent.disableClickPropagation(btn);
      L.DomEvent.on(btn, 'click', () => {
        if (!isFullscreen) { enterFS(); btn.innerHTML = '✕'; btn.title = 'Keluar Fullscreen'; }
        else                { exitFS();  btn.innerHTML = '⛶'; btn.title = 'Fullscreen'; }
      });
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && isFullscreen) { exitFS(); btn.innerHTML = '⛶'; btn.title = 'Fullscreen'; }
      });
      return btn;
    }
  });
  new FsCtrl({ position: 'topleft' }).addTo(map);
}

</script>
<script>
// ── OVERVIEW MAP ─────────────────────────────────────────────────────────────
const nodeData = <?= json_encode(array_values(array_filter($nodes, fn($n) => $n['latitude'] && $n['longitude']))) ?>;
const overviewMap = L.map('overview-map', {maxZoom:22}).setView([-7.02580113, 112.47867107], 14);
snmInitControls(overviewMap, 'overview-map-wrap');
nodeData.forEach(n => {
  L.marker([parseFloat(n.latitude), parseFloat(n.longitude)])
    .addTo(overviewMap)
    .bindPopup(`<strong>${n.name}</strong><br><small>${n.address||''}</small><br>Pelanggan: ${n.cust_count}`);
});
if (nodeData.length) {
  overviewMap.fitBounds(nodeData.map(n => [parseFloat(n.latitude), parseFloat(n.longitude)]), {padding:[30,30]});
}

// ── MODAL NODE (managed manually) ────────────────────────────────────────────
const bsNodeModal   = new bootstrap.Modal(document.getElementById('modalNode'), {backdrop:'static'});
const bsPickerModal = new bootstrap.Modal(document.getElementById('mapPickerModal'), {backdrop:true});

document.getElementById('btn-add-node').addEventListener('click', () => {
  resetForm();
  bsNodeModal.show();
});

function openEdit(n) {
  document.getElementById('form-action').value = 'update';
  document.getElementById('form-id').value     = n.id;
  document.getElementById('f-name').value      = n.name    || '';
  document.getElementById('f-address').value   = n.address || '';
  document.getElementById('f-owner').value     = n.owner_name    || '';
  document.getElementById('f-contact').value   = n.owner_contact || '';
  document.getElementById('f-lat').value       = n.latitude  || '';
  document.getElementById('f-lng').value       = n.longitude || '';
  document.getElementById('f-desc').value      = n.description || '';
  document.getElementById('modal-title').textContent = 'Edit Node — ' + n.name;
  bsNodeModal.show();
}

function resetForm() {
  document.getElementById('form-action').value = 'create';
  document.getElementById('form-id').value     = '';
  document.getElementById('modal-title').textContent = 'Tambah Node';
  document.getElementById('node-form').reset();
}

document.getElementById('modalNode').addEventListener('hidden.bs.modal', resetForm);

// ── MAP PICKER (open / close flow) ───────────────────────────────────────────
// BUG FIX: Close modalNode BEFORE opening picker, reopen after confirm/cancel.
// This avoids Bootstrap's stacked-modal backdrop issues entirely.

let pickerMap = null, pickerMarker = null;
let tempLat = null, tempLng = null;

document.getElementById('btn-open-picker').addEventListener('click', () => {
  tempLat = document.getElementById('f-lat').value || null;
  tempLng = document.getElementById('f-lng').value || null;

  // Hide node modal WITHOUT resetting (backdrop:static prevents auto-close)
  bsNodeModal.hide();
});

// Once nodeModal finishes hiding, open picker
document.getElementById('modalNode').addEventListener('hidden.bs.modal', function onNodeHiddenForPicker() {
  // Only open picker when this specific button triggered it
  if (_pickerPending) {
    _pickerPending = false;
    bsPickerModal.show();
  }
}, {passive:true});

// Better approach: track state
let _pickerPending = false;

document.getElementById('btn-open-picker').addEventListener('click', () => {
  _pickerPending = true;
}, true); // capture phase, runs BEFORE the main listener above

// Init picker map when shown
document.getElementById('mapPickerModal').addEventListener('shown.bs.modal', function() {
  if (!pickerMap) {
    pickerMap = L.map('picker-map', {maxZoom:22}).setView([-7.02580113, 112.47867107], 14);
    snmInitControls(pickerMap, 'picker-map-wrap');
    pickerMap.on('click', function(e) {
      tempLat = e.latlng.lat.toFixed(8);
      tempLng = e.latlng.lng.toFixed(8);
      document.getElementById('picker-coords').textContent = tempLat + ', ' + tempLng;
      if (pickerMarker) pickerMarker.setLatLng(e.latlng);
      else pickerMarker = L.marker(e.latlng, {draggable:true}).addTo(pickerMap);
      pickerMarker.on('dragend', function(ev) {
        const ll = ev.target.getLatLng();
        tempLat = ll.lat.toFixed(8);
        tempLng = ll.lng.toFixed(8);
        document.getElementById('picker-coords').textContent = tempLat + ', ' + tempLng;
      });
    });
  }
  setTimeout(() => pickerMap.invalidateSize(), 200);

  // Pre-fill if already has coords
  const lat = document.getElementById('f-lat').value;
  const lng = document.getElementById('f-lng').value;
  if (lat && lng) {
    const pos = [parseFloat(lat), parseFloat(lng)];
    pickerMap.setView(pos, 15);
    if (!pickerMarker) pickerMarker = L.marker(pos, {draggable:true}).addTo(pickerMap);
    else pickerMarker.setLatLng(pos);
    tempLat = lat; tempLng = lng;
    document.getElementById('picker-coords').textContent = lat + ', ' + lng;
  }
});

// CONFIRM: save coords → close picker → reopen node modal
document.getElementById('picker-confirm-btn').addEventListener('click', () => {
  if (tempLat !== null) {
    document.getElementById('f-lat').value = tempLat;
    document.getElementById('f-lng').value = tempLng;
  }
  bsPickerModal.hide();
});

// CANCEL (both X and Batal): discard, reopen node modal
function cancelPicker() { bsPickerModal.hide(); }
document.getElementById('picker-cancel-btn').addEventListener('click', cancelPicker);
document.getElementById('picker-cancel-x').addEventListener('click', cancelPicker);

// When picker closes → reopen node modal
document.getElementById('mapPickerModal').addEventListener('hidden.bs.modal', () => {
  bsNodeModal.show();
});

// Nominatim search
document.getElementById('picker-search').addEventListener('keydown', async function(e) {
  if (e.key !== 'Enter') return;
  const q = this.value.trim();
  if (!q || !pickerMap) return;
  try {
    const r = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q)}&format=json&limit=1`);
    const d = await r.json();
    if (d.length) pickerMap.setView([parseFloat(d[0].lat), parseFloat(d[0].lon)], 16);
  } catch(e) { console.error(e); }
});

// ── CRUD HELPERS ─────────────────────────────────────────────────────────────
function openDelete(id, name) {
  document.getElementById('del-id').value = id;
  document.getElementById('del-name').textContent = name;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.getElementById('search-node').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#node-table tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>