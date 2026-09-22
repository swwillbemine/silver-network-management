<!doctype html>
<html lang="id">
<?php \App\Core\View::header(); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<body>
<div class="page">
  <?php \App\Core\View::sidebar(); ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle">Pelanggan</div>
            <h2 class="page-title">Hotspot</h2>
          </div>
          <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalHs">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14M5 12h14"/></svg>
              Tambah Hotspot
            </button>
          </div>
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

        <!-- MAP -->
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Peta Sebaran Hotspot</h3></div>
          <div id="hs-map-wrap" style="position:relative;">
          <div id="hs-map" style="height:380px;z-index:0;"></div>
        </div>
        </div>

        <!-- TABLE -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Daftar Hotspot (<?= count($hotspots) ?>)</h3>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr>
                  <th>Nama</th>
                  <th>SSID</th>
                  <th>Node</th>
                  <th>Router</th>
                  <th>Status</th>
                  <th class="w-1">Aksi</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($hotspots as $h): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($h['name']) ?></td>
                <td><code><?= htmlspecialchars($h['ssid']) ?></code></td>
                <td>
                  <div><?= htmlspecialchars($h['node_name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($h['node_address']) ?></div>
                </td>
                <td class="text-muted"><?= htmlspecialchars($h['router_name']) ?></td>
                <td>
                  <span class="badge bg-<?= $h['is_active'] ? 'success' : 'secondary' ?>-lt">
                    <?= $h['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                  </span>
                </td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-ghost-secondary" onclick="editHs(<?= htmlspecialchars(json_encode($h)) ?>)">Edit</button>
                    <button class="btn btn-ghost-danger" onclick="deleteHs(<?= $h['id'] ?>, '<?= addslashes($h['name']) ?>')">Hapus</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$hotspots): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Belum ada hotspot terdaftar.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal modal-blur fade" id="modalHs" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="form-action" value="create">
        <input type="hidden" name="id" id="form-id">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Tambah Hotspot</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label required">Nama Hotspot</label>
            <input type="text" name="name" id="f-name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label required">SSID</label>
            <input type="text" name="ssid" id="f-ssid" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label required">Node Lokasi</label>
            <select name="node_id" id="f-node" class="form-select" required>
              <option value="">-- Pilih Node --</option>
              <?php foreach ($nodes as $n): ?><option value="<?= $n['id'] ?>"><?= htmlspecialchars($n['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label required">Router MikroTik</label>
            <select name="mikrotik_id" id="f-router" class="form-select" required>
              <option value="">-- Pilih Router --</option>
              <?php foreach ($routers as $r): ?><option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" id="f-active" value="1" checked>
              <label class="form-check-label" for="f-active">Aktif</label>
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

<!-- DELETE MODAL -->
<div class="modal modal-blur fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="del-id">
        <div class="modal-body"><p>Hapus hotspot <strong id="del-name"></strong>?</p></div>
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
    wrapper.style.cssText = 'position:fixed!important;top:0!important;left:0!important;width:100vw!important;height:100vh!important;z-index:9999!important;margin:0!important;padding:0!important;';
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
const hsMap = L.map('hs-map', {maxZoom:22}).setView([-7.02580113, 112.47867107], 14);
snmInitControls(hsMap, 'hs-map-wrap');

const hsData = <?= json_encode(array_filter($hotspots, fn($h) => $h['latitude'] && $h['longitude'])) ?>;
Object.values(hsData).forEach(h => {
  const color = h.is_active ? '#3b82f6' : '#9ca3af';
  L.circleMarker([h.latitude, h.longitude], { radius: 10, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9 })
    .addTo(hsMap)
    .bindPopup(`<strong>${h.name}</strong><br>SSID: ${h.ssid}<br>Node: ${h.node_name}<br>Router: ${h.router_name}`);
});

const withCoords = Object.values(hsData);
if (withCoords.length) hsMap.fitBounds(withCoords.map(h => [h.latitude, h.longitude]), {padding:[30,30]});

function editHs(h) {
  document.getElementById('form-action').value = 'update';
  document.getElementById('form-id').value = h.id;
  document.getElementById('f-name').value = h.name||'';
  document.getElementById('f-ssid').value = h.ssid||'';
  document.getElementById('f-node').value = h.node_id;
  document.getElementById('f-router').value = h.mikrotik_id;
  document.getElementById('f-active').checked = h.is_active == 1;
  document.getElementById('modal-title').textContent = 'Edit Hotspot';
  new bootstrap.Modal(document.getElementById('modalHs')).show();
}
function deleteHs(id, name) {
  document.getElementById('del-id').value = id;
  document.getElementById('del-name').textContent = name;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
document.getElementById('modalHs').addEventListener('hidden.bs.modal', function() {
  document.getElementById('form-action').value = 'create';
  document.getElementById('form-id').value = '';
  document.getElementById('modal-title').textContent = 'Tambah Hotspot';
  this.querySelector('form').reset();
});
</script>
</body>
</html>

