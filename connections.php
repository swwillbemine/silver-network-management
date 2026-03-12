<?php
// pages/connections.php
// require_once 'config/settings.php';
// require_once 'config/database.php';
// require_once 'helpers/security.php';
require_once __DIR__ . '/config/bootstrap.php';

requireLogin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO connections (name,start_node_id,end_node_id,type,length_estimated,core_color,loss_db,wireless_frequency,wireless_ssid,wireless_password,wireless_ip_radio_ap,wireless_ip_radio_station,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            (function() use ($pdo) {
                $nm = trim($_POST['name'] ?? '');
                if ($nm === '') {
                    $sn = $pdo->prepare("SELECT name FROM nodes WHERE id=?");
                    $sn->execute([$_POST['start_node_id']]);
                    $sname = $sn->fetchColumn() ?: 'Node';
                    $en = $pdo->prepare("SELECT name FROM nodes WHERE id=?");
                    $en->execute([$_POST['end_node_id']]);
                    $ename = $en->fetchColumn() ?: 'Node';
                    // Abbreviate: take first word or up to 8 chars
                    $sa = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/','',$sname), 0, 6));
                    $ea = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/','',$ename), 0, 6));
                    $rand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                    $nm = $sa . '-' . $ea . '-' . $rand;
                }
                return $nm;
            })(), $_POST['start_node_id'], $_POST['end_node_id'], $_POST['type'],
            $_POST['length_estimated'] ?: 0, $_POST['core_color'], $_POST['loss_db'] ?: null,
            $_POST['wireless_frequency'], $_POST['wireless_ssid'], $_POST['wireless_password'],
            $_POST['wireless_ip_radio_ap'], $_POST['wireless_ip_radio_station'], $_POST['status']
        ]);
        $msg = 'success:Jalur berhasil ditambahkan.';
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE connections SET name=?,start_node_id=?,end_node_id=?,type=?,length_estimated=?,core_color=?,loss_db=?,wireless_frequency=?,wireless_ssid=?,wireless_password=?,wireless_ip_radio_ap=?,wireless_ip_radio_station=?,status=? WHERE id=?");
        $stmt->execute([
            $_POST['name'], $_POST['start_node_id'], $_POST['end_node_id'], $_POST['type'],
            $_POST['length_estimated'] ?: 0, $_POST['core_color'], $_POST['loss_db'] ?: null,
            $_POST['wireless_frequency'], $_POST['wireless_ssid'], $_POST['wireless_password'],
            $_POST['wireless_ip_radio_ap'], $_POST['wireless_ip_radio_station'], $_POST['status'], $_POST['id']
        ]);
        $msg = 'success:Jalur berhasil diperbarui.';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM connections WHERE id=?")->execute([$_POST['id']]);
        $msg = 'success:Jalur berhasil dihapus.';
    }
}

$connections = $pdo->query("
    SELECT c.*, 
        sn.name AS start_name, sn.latitude AS start_lat, sn.longitude AS start_lng,
        en.name AS end_name,   en.latitude AS end_lat,   en.longitude AS end_lng
    FROM connections c
    JOIN nodes sn ON sn.id = c.start_node_id
    JOIN nodes en ON en.id = c.end_node_id
    ORDER BY c.name")->fetchAll(PDO::FETCH_ASSOC);

$nodes = $pdo->query("SELECT id, name, latitude, longitude FROM nodes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
$status_labels = ['connected' => 'Terhubung', 'broken' => 'Putus', 'maintenance' => 'Maintenance'];
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
            <h2 class="page-title">Jalur Koneksi</h2>
          </div>
          <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalConn">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14M5 12h14"/></svg>
              Tambah Jalur
            </button>
          </div>
        </div>
      </div>
    </div>
    <div class="page-body">
      <div class="container-xl">
        <?php if ($msg_text): ?>
        <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3" role="alert">
          <?= htmlspecialchars($msg_text) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- TOPOLOGY MAP -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title">Peta Topologi Jaringan</h3>
            <div class="card-options">
              <div class="d-flex gap-3 align-items-center small text-muted">
                <span><span style="display:inline-block;width:24px;height:3px;background:#3b82f6;vertical-align:middle;"></span> Wireless PtP</span>
                <span><span style="display:inline-block;width:24px;height:3px;border-top:3px dashed #f59e0b;vertical-align:middle;"></span> Wireless lain</span>
                <span><span style="display:inline-block;width:24px;height:3px;background:#10b981;vertical-align:middle;"></span> Fiber Optik</span>
                <span><span style="display:inline-block;width:24px;height:3px;background:#6b7280;vertical-align:middle;"></span> UTP/Lainnya</span>
                <span><span style="display:inline-block;width:24px;height:3px;background:#ef4444;vertical-align:middle;"></span> Putus</span>
              </div>
            </div>
          </div>
          <div id="topology-map-wrap" style="position:relative;">
          <div id="topology-map" style="height:460px;z-index:0;"></div>
        </div>
        </div>

        <!-- CONNECTION TABLE -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Daftar Jalur (<?= count($connections) ?>)</h3>
            <div class="card-options">
              <input type="text" id="search-conn" class="form-control form-control-sm" placeholder="Cari jalur...">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr>
                  <th>Nama</th>
                  <th>Dari Node</th>
                  <th>Ke Node</th>
                  <th>Tipe</th>
                  <th>Panjang</th>
                  <th>Status</th>
                  <th class="w-1">Aksi</th>
                </tr>
              </thead>
              <tbody id="conn-table">
              <?php foreach ($connections as $c): ?>
              <?php
                $isWireless = str_starts_with($c['type'], 'wireless');
                $typeColor  = $isWireless ? 'blue' : ($c['type'] === 'fo_precon' ? 'green' : 'secondary');
                $statusColor = $c['status'] === 'connected' ? 'success' : ($c['status'] === 'broken' ? 'danger' : 'warning');
              ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['start_name']) ?></td>
                <td><?= htmlspecialchars($c['end_name']) ?></td>
                <td><span class="badge bg-<?= $typeColor ?>-lt"><?= htmlspecialchars($c['type']) ?></span></td>
                <td class="text-muted"><?= $c['length_estimated'] ? $c['length_estimated'].' m' : '-' ?></td>
                <td><span class="badge bg-<?= $statusColor ?>-lt"><?= $status_labels[$c['status']] ?? $c['status'] ?></span></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-ghost-secondary" onclick="editConn(<?= htmlspecialchars(json_encode($c)) ?>)">Edit</button>
                    <button class="btn btn-ghost-danger" onclick="deleteConn(<?= $c['id'] ?>, '<?= addslashes($c['name']) ?>')">Hapus</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$connections): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Belum ada jalur koneksi.</td></tr>
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

<!-- MODAL CONNECTION -->
<div class="modal modal-blur fade" id="modalConn" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="form-action" value="create">
        <input type="hidden" name="id" id="form-id">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Tambah Jalur Koneksi</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Nama Jalur <span class="text-muted small fw-normal">(opsional)</span></label>
              <input type="text" name="name" id="f-name" class="form-control" placeholder="Kosongkan untuk generate otomatis">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label required">Tipe Koneksi</label>
              <select name="type" id="f-type" class="form-select" required onchange="toggleFields()">
                <option value="wireless_ptp">Wireless PtP</option>
                <option value="wireless_ptmp">Wireless PtMP</option>
                <option value="wireless_backhaul">Wireless Backhaul</option>
                <option value="fo_precon">FO Precon</option>
                <option value="fo_sc_dropcore">FO Dropcore SC/UPC</option>
                <option value="utp">UTP</option>
                <option value="other">Lainnya</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label required">Node Asal</label>
              <select name="start_node_id" id="f-start" class="form-select" required>
                <option value="">-- Pilih Node Asal --</option>
                <?php foreach ($nodes as $n): ?><option value="<?= $n['id'] ?>"><?= htmlspecialchars($n['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label required">Node Tujuan</label>
              <select name="end_node_id" id="f-end" class="form-select" required>
                <option value="">-- Pilih Node Tujuan --</option>
                <?php foreach ($nodes as $n): ?><option value="<?= $n['id'] ?>"><?= htmlspecialchars($n['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Status</label>
              <select name="status" id="f-status" class="form-select">
                <option value="connected">Terhubung</option>
                <option value="maintenance">Maintenance</option>
                <option value="broken">Putus</option>
              </select>
            </div>

            <!-- Kabel fields -->
            <div id="cable-fields" class="col-12">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Panjang (meter)</label>
                  <input type="number" name="length_estimated" id="f-length" class="form-control" min="0">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Warna Core</label>
                  <input type="text" name="core_color" id="f-core-color" class="form-control" placeholder="cth: Biru">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Loss (dB)</label>
                  <input type="number" name="loss_db" id="f-loss" class="form-control" step="0.01">
                </div>
              </div>
            </div>

            <!-- Wireless fields -->
            <div id="wireless-fields" class="col-12 d-none">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">Frekuensi</label>
                  <input type="text" name="wireless_frequency" id="f-freq" class="form-control" placeholder="5.8GHz">
                </div>
                <div class="col-md-3">
                  <label class="form-label">SSID</label>
                  <input type="text" name="wireless_ssid" id="f-ssid" class="form-control">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Password</label>
                  <input type="text" name="wireless_password" id="f-wpass" class="form-control">
                </div>
                <div class="col-md-3">
                  <label class="form-label">IP AP</label>
                  <input type="text" name="wireless_ip_radio_ap" id="f-ip-ap" class="form-control">
                </div>
                <div class="col-md-3">
                  <label class="form-label">IP Station</label>
                  <input type="text" name="wireless_ip_radio_station" id="f-ip-station" class="form-control">
                </div>
              </div>
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
        <div class="modal-body"><p>Hapus jalur <strong id="del-name"></strong>?</p></div>
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
const nodes = <?= json_encode($nodes) ?>;
const connections = <?= json_encode($connections) ?>;

// Init topology map
const tmap = L.map('topology-map', {maxZoom:22}).setView([-7.02580113, 112.47867107], 14);
snmInitControls(tmap, 'topology-map-wrap');

const nodeMap = {};
nodes.forEach(n => {
  if (n.latitude && n.longitude) {
    nodeMap[n.id] = L.circleMarker([n.latitude, n.longitude], {
      radius: 7, fillColor: '#3b82f6', color: '#fff', weight: 2, fillOpacity: 1
    }).addTo(tmap).bindPopup(`<strong>${n.name}</strong>`);
  }
});

connections.forEach(c => {
  const sn = nodes.find(n => n.id == c.start_node_id);
  const en = nodes.find(n => n.id == c.end_node_id);
  if (!sn?.latitude || !en?.latitude) return;

  const isWireless = c.type?.startsWith('wireless');
  const isBroken   = c.status === 'broken';
  const color       = isBroken ? '#ef4444'
                    : (c.type === 'fo_precon' || c.type === 'fo_sc_dropcore') ? '#10b981'
                    : isWireless ? '#3b82f6' : '#6b7280';
  const dashArray   = isWireless ? '8, 6' : null;

  L.polyline([[sn.latitude, sn.longitude], [en.latitude, en.longitude]], {
    color, weight: 3, dashArray, opacity: 0.85
  }).addTo(tmap).bindPopup(`<strong>${c.name || ''}</strong><br>${c.start_name} → ${c.end_name}<br>Tipe: ${c.type}<br>Status: ${c.status}`);
});

const allWithCoords = nodes.filter(n => n.latitude && n.longitude);
if (allWithCoords.length) {
  tmap.fitBounds(allWithCoords.map(n => [n.latitude, n.longitude]), {padding: [30, 30]});
}

function toggleFields() {
  const t = document.getElementById('f-type').value;
  const isW = t.startsWith('wireless');
  document.getElementById('wireless-fields').classList.toggle('d-none', !isW);
  document.getElementById('cable-fields').classList.toggle('d-none', isW);
}

function editConn(c) {
  document.getElementById('form-action').value = 'update';
  document.getElementById('form-id').value = c.id;
  document.getElementById('f-name').value = c.name || '';
  document.getElementById('f-type').value = c.type || 'wireless_ptp';
  tsStart.setValue(String(c.start_node_id));
  tsEnd.setValue(String(c.end_node_id));
  document.getElementById('f-status').value = c.status || 'connected';
  document.getElementById('f-length').value = c.length_estimated || '';
  document.getElementById('f-core-color').value = c.core_color || '';
  document.getElementById('f-loss').value = c.loss_db || '';
  document.getElementById('f-freq').value = c.wireless_frequency || '';
  document.getElementById('f-ssid').value = c.wireless_ssid || '';
  document.getElementById('f-wpass').value = c.wireless_password || '';
  document.getElementById('f-ip-ap').value = c.wireless_ip_radio_ap || '';
  document.getElementById('f-ip-station').value = c.wireless_ip_radio_station || '';
  toggleFields();
  document.getElementById('modal-title').textContent = 'Edit Jalur';
  new bootstrap.Modal(document.getElementById('modalConn')).show();
}

function deleteConn(id, name) {
  document.getElementById('del-id').value = id;
  document.getElementById('del-name').textContent = name;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.getElementById('modalConn').addEventListener('hidden.bs.modal', function() {
  document.getElementById('form-action').value = 'create';
  document.getElementById('form-id').value = '';
  document.getElementById('modal-title').textContent = 'Tambah Jalur Koneksi';
  this.querySelector('form').reset();
  toggleFields();
});

document.getElementById('search-conn').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#conn-table tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
// Tom Select for node dropdowns
const tsStart = new TomSelect('#f-start', {
  allowEmptyOption: true,
  placeholder: 'Cari node asal...',
  maxOptions: 200
});
const tsEnd = new TomSelect('#f-end', {
  allowEmptyOption: true,
  placeholder: 'Cari node tujuan...',
  maxOptions: 200
});

// Reset Tom Select on modal close
document.getElementById('modalConn').addEventListener('hidden.bs.modal', function() {
  tsStart.clear(); tsStart.setValue('');
  tsEnd.clear();   tsEnd.setValue('');
});
</script>
</body>
</html>