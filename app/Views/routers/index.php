<!doctype html>
<html lang="id">
<?php \App\Core\View::header(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<body>
<div class="page">
  <?php \App\Core\View::sidebar(); ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <?php if ($detail_id): ?>
            <div class="page-pretitle"><a href="<?= BASE_URL ?>/routers">Router</a> / Detail</div>
            <h2 class="page-title"><?= htmlspecialchars($detail['name']) ?></h2>
            <?php else: ?>
            <div class="page-pretitle">Infrastruktur</div>
            <h2 class="page-title">Router (MikroTik)</h2>
            <?php endif; ?>
          </div>
          <div class="col-auto d-flex gap-2">
            <?php if ($detail_id): ?>
            <a href="<?= BASE_URL ?>/routers" class="btn btn-ghost-secondary">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M15 6l-6 6l6 6"/></svg>
              Kembali
            </a>
            <button class="btn btn-primary" onclick="refreshDetail()">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M20 11A8.1 8.1 0 0 0 4.5 9M4 5v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg>
              Refresh
            </button>
            <?php else: ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRouter">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14M5 12h14"/></svg>
              Tambah Router
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <?php if (!empty($msg_text)): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible mb-3">
          <?= htmlspecialchars($msg_text) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

<?php if ($detail_id): // ══ DETAIL VIEW ══════════════════════════════════════
?>
        <!-- INFO CARDS ROW -->
        <div class="row g-3 mb-3" id="info-cards">
          <div class="col-auto">
            <div class="card card-sm">
              <div class="card-body d-flex align-items-center gap-3 px-4">
                <span class="status-dot status-dot-animated bg-secondary flex-shrink-0" id="d-status-dot"></span>
                <div>
                  <div class="text-muted small">Status</div>
                  <div class="fw-bold" id="d-status-txt">Memuat...</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col">
            <div class="card card-sm">
              <div class="card-body">
                <div class="row text-center g-0">
                  <div class="col border-end px-3"><div class="text-muted small">Identity</div><div class="fw-bold" id="d-identity">-</div></div>
                  <div class="col border-end px-3"><div class="text-muted small">Model</div><div class="fw-bold" id="d-model">-</div></div>
                  <div class="col border-end px-3"><div class="text-muted small">RouterOS</div><div class="fw-bold" id="d-version">-</div></div>
                  <div class="col border-end px-3"><div class="text-muted small">Uptime</div><div class="fw-bold" id="d-uptime">-</div></div>
                  <div class="col border-end px-3"><div class="text-muted small">CPU</div><div class="fw-bold" id="d-cpu">-</div></div>
                  <div class="col border-end px-3"><div class="text-muted small">RAM</div><div class="fw-bold" id="d-ram">-</div></div>
                  <div class="col px-3"><div class="text-muted small">PPPoE Aktif</div><div class="fw-bold text-green" id="d-ppp">-</div></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TRAFFIC CHART -->
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title">Grafik Traffic Interface</h3>
            <div class="card-options d-flex gap-2 align-items-center">
              <select id="iface-select" class="form-select form-select-sm" style="min-width:200px;">
                <option value="">Memuat interface...</option>
              </select>
              <span class="text-muted small" id="chart-note"></span>
            </div>
          </div>
          <div class="card-body">
            <div id="chart-offline" class="text-center text-muted py-5 d-none">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon text-red" width="40" height="40" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <div class="mt-2">Router tidak dapat dijangkau.</div>
            </div>
            <canvas id="traffic-chart" height="120"></canvas>
          </div>
          <div class="card-footer d-flex gap-4 text-center">
            <div class="flex-fill">
              <div class="text-muted small">RX Sekarang</div>
              <div class="fw-bold text-blue" id="d-rx-now">-</div>
            </div>
            <div class="flex-fill border-start">
              <div class="text-muted small">TX Sekarang</div>
              <div class="fw-bold text-cyan" id="d-tx-now">-</div>
            </div>
            <div class="flex-fill border-start">
              <div class="text-muted small">RX Peak</div>
              <div class="fw-bold" id="d-rx-peak">-</div>
            </div>
            <div class="flex-fill border-start">
              <div class="text-muted small">TX Peak</div>
              <div class="fw-bold" id="d-tx-peak">-</div>
            </div>
          </div>
        </div>

        <!-- INTERFACE TABLE + PPPoE SESSIONS -->
        <div class="row g-3">
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-header"><h3 class="card-title">Interface</h3></div>
              <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table">
                  <thead><tr><th>Nama</th><th>Type</th><th>Status</th><th class="text-end">RX</th><th class="text-end">TX</th></tr></thead>
                  <tbody id="iface-table"><tr><td colspan="5" class="text-center text-muted">Memuat...</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-header">
                <h3 class="card-title">Sesi PPPoE Aktif</h3>
                <div class="card-options"><span class="badge bg-green-lt" id="ppp-count-badge">0</span></div>
              </div>
              <div class="table-responsive" style="max-height:320px;overflow-y:auto;">
                <table class="table table-sm table-vcenter card-table">
                  <thead><tr><th>Username</th><th>IP</th><th>Uptime</th><th>RX/TX</th></tr></thead>
                  <tbody id="ppp-table"><tr><td colspan="4" class="text-center text-muted">Memuat...</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- ROUTER INFO + QUICK ACCESS -->
        <div class="card mt-3">
          <div class="card-header">
            <h3 class="card-title">Informasi Router</h3>
            <div class="card-options">
              <button class="btn btn-sm btn-outline-secondary" onclick="editRouter(<?= htmlspecialchars(json_encode($detail)) ?>)">Edit</button>
            </div>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-3"><div class="text-muted small">POP</div><div><?= htmlspecialchars($detail['pop_name']) ?></div></div>
              <div class="col-md-3"><div class="text-muted small">Node</div><div><?= htmlspecialchars($detail['node_name']) ?></div></div>
              <div class="col-md-3"><div class="text-muted small">Host</div><div><code><?= htmlspecialchars($detail['host']) ?></code></div></div>
              <div class="col-md-3"><div class="text-muted small">Port API</div><div><?= $detail['port'] ?> <?= $detail['api_ssl']?'<span class="badge bg-green-lt">SSL</span>':'' ?></div></div>
              <div class="col-md-3"><div class="text-muted small">Akses Winbox</div>
                <div><a href="http://<?= $detail['host'] ?>:8291" target="_blank" class="btn btn-sm btn-outline-secondary">Winbox Web</a></div>
              </div>
              <div class="col-md-3"><div class="text-muted small">Akses WebFig</div>
                <div><a href="http://<?= $detail['host'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">WebFig (80)</a></div>
              </div>
            </div>
          </div>
        </div>

<?php else: // ══ LIST VIEW ═══════════════════════════════════════════════════
?>
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Daftar Router (<?= count($routers) ?>)</h3>
            <div class="card-options">
              <input type="text" id="search-rt" class="form-control form-control-sm" placeholder="Cari router..." style="width:180px;">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead>
                <tr><th>Nama</th><th>POP / Node</th><th>Host</th><th>Port</th><th>SSL</th><th>Status</th><th class="w-1">Aksi</th></tr>
              </thead>
              <tbody id="rt-table">
              <?php foreach ($routers as $r): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($r['name']) ?></td>
                <td>
                  <div><?= htmlspecialchars($r['pop_name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($r['node_name']) ?></div>
                </td>
                <td><code><?= htmlspecialchars($r['host']) ?></code></td>
                <td class="text-muted"><?= $r['port'] ?></td>
                <td><?= $r['api_ssl']?'<span class="badge bg-green-lt">SSL</span>':'<span class="text-muted">-</span>' ?></td>
                <td><span class="badge bg-<?= $r['status']==='online'?'green':'red' ?>-lt"><?= ucfirst($r['status']) ?></span></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="?id=<?= $r['id'] ?>" class="btn btn-ghost-secondary">Detail</a>
                    <button class="btn btn-ghost-secondary" onclick="editRouter(<?= htmlspecialchars(json_encode($r)) ?>)">Edit</button>
                    <button class="btn btn-ghost-danger" onclick="deleteRouter(<?= $r['id'] ?>,'<?= addslashes($r['name']) ?>')">Hapus</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$routers): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Belum ada router terdaftar.</td></tr>
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

<!-- ══ MODAL CREATE/EDIT ════════════════════════════════════════════════════ -->
<div class="modal modal-blur fade" id="modalRouter" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" id="form-action" value="create">
        <input type="hidden" name="id" id="form-id">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Tambah Router</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required">POP</label>
              <select name="pop_id" id="f-pop" class="form-select" required>
                <option value="">-- Pilih POP --</option>
                <?php foreach ($pops as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label required">Nama / Label</label>
              <input type="text" name="name" id="f-name" class="form-control" required>
            </div>
            <div class="col-md-5">
              <label class="form-label required">Host / IP</label>
              <input type="text" name="host" id="f-host" class="form-control" required placeholder="192.168.1.1">
            </div>
            <div class="col-md-3">
              <label class="form-label">Port API</label>
              <input type="number" name="port" id="f-port" class="form-control" value="8728">
            </div>
            <div class="col-md-4">
              <label class="form-label">API SSL</label>
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" name="api_ssl" id="f-ssl" value="1">
                <label class="form-check-label" for="f-ssl">Aktifkan SSL (port 8729)</label>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label required">Username</label>
              <input type="text" name="username" id="f-user" class="form-control" required value="admin">
            </div>
            <div class="col-md-6">
              <label class="form-label" id="pass-label">Password</label>
              <div class="input-group">
                <input type="password" name="password" id="f-pass" class="form-control" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary" onclick="const p=document.getElementById('f-pass');p.type=p.type==='password'?'text':'password'">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10 12a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M21 12c-2.4 4-5.4 6-9 6s-6.6-2-9-6c2.4-4 5.4-6 9-6s6.6 2 9 6"/></svg>
                </button>
              </div>
              <small class="text-muted" id="pass-hint"></small>
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

<div class="modal modal-blur fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="del-id">
        <div class="modal-body"><p>Hapus router <strong id="del-name"></strong>?</p></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Hapus</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
// Tom Select for POP dropdown
new TomSelect('#f-pop', {allowEmptyOption:true});

function editRouter(r) {
  document.getElementById('form-action').value='update';
  document.getElementById('form-id').value=r.id;
  document.getElementById('f-name').value=r.name||'';
  document.getElementById('f-host').value=r.host||'';
  document.getElementById('f-port').value=r.port||8728;
  document.getElementById('f-user').value=r.username||'';
  document.getElementById('f-ssl').checked=r.api_ssl==1;
  document.getElementById('f-pass').value='';
  document.getElementById('pass-label').textContent='Password (kosongkan = tidak berubah)';
  document.getElementById('pass-hint').textContent='Kosongkan jika tidak ingin mengubah password.';
  document.querySelector('#f-pop').tomselect?.setValue(String(r.pop_id));
  document.getElementById('modal-title').textContent='Edit Router — '+r.name;
  new bootstrap.Modal(document.getElementById('modalRouter')).show();
}

function deleteRouter(id,name) {
  document.getElementById('del-id').value=id;
  document.getElementById('del-name').textContent=name;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.getElementById('modalRouter').addEventListener('hidden.bs.modal',function(){
  document.getElementById('form-action').value='create';
  document.getElementById('form-id').value='';
  document.getElementById('modal-title').textContent='Tambah Router';
  document.getElementById('pass-label').textContent='Password';
  document.getElementById('pass-hint').textContent='';
  this.querySelector('form').reset();
});

<?php if (!$detail_id): ?>
document.getElementById('search-rt').addEventListener('input',function(){
  const q=this.value.toLowerCase();
  document.querySelectorAll('#rt-table tr').forEach(tr=>{tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none';});
});
<?php endif; ?>

// ══ DETAIL VIEW LOGIC ═══════════════════════════════════════════════════════
<?php if ($detail_id): ?>
const ROUTER_ID = <?= $detail_id ?>;
const MAX_POINTS = 60;

// Chart setup
const ctx = document.getElementById('traffic-chart').getContext('2d');
const chartLabels = [];
const rxData = [], txData = [];
const trafficChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: chartLabels,
    datasets: [
      {
        label: 'RX (Download)',
        data: rxData,
        borderColor: '#206bc4',
        backgroundColor: 'rgba(32,107,196,0.08)',
        borderWidth: 2,
        fill: true,
        tension: 0.3,
        pointRadius: 0,
      },
      {
        label: 'TX (Upload)',
        data: txData,
        borderColor: '#0ca678',
        backgroundColor: 'rgba(12,166,120,0.08)',
        borderWidth: 2,
        fill: true,
        tension: 0.3,
        pointRadius: 0,
      }
    ]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    scales: {
      x: { display: false },
      y: {
        beginAtZero: true,
        ticks: {
          callback: v => formatBps(v)
        }
      }
    },
    plugins: {
      tooltip: {
        callbacks: {
          label: ctx => ctx.dataset.label + ': ' + formatBps(ctx.parsed.y)
        }
      },
      legend: { position: 'top' }
    },
    animation: { duration: 300 }
  }
});

let rxPeak = 0, txPeak = 0;
let prevRxBytes = null, prevTxBytes = null;
let selectedIface = '';
let allInterfaces = [];

function formatBps(bps) {
  if (bps >= 1e9) return (bps/1e9).toFixed(2)+' Gbps';
  if (bps >= 1e6) return (bps/1e6).toFixed(2)+' Mbps';
  if (bps >= 1e3) return (bps/1e3).toFixed(2)+' Kbps';
  return bps.toFixed(0)+' bps';
}

function formatBytes(b) {
  b=parseInt(b)||0;
  if (b>=1e9) return (b/1e9).toFixed(2)+' GB';
  if (b>=1e6) return (b/1e6).toFixed(2)+' MB';
  if (b>=1e3) return (b/1e3).toFixed(2)+' KB';
  return b+' B';
}

async function fetchDetail() {
  try {
    const res = await fetch(`api/router_detail.php?id=${ROUTER_ID}&iface=${encodeURIComponent(selectedIface)}`);
    if (!res.ok) throw new Error('HTTP '+res.status);
    const text = await res.text();
    let d;
    try { d = JSON.parse(text); }
    catch(e) { console.error('JSON parse error:', text.substring(0,200)); return; }

    if (d.offline) {
      document.getElementById('chart-offline').classList.remove('d-none');
      document.getElementById('traffic-chart').style.display='none';
      document.getElementById('d-status-dot').className='status-dot bg-red flex-shrink-0';
      document.getElementById('d-status-txt').textContent='Offline';
      return;
    }

    document.getElementById('chart-offline').classList.add('d-none');
    document.getElementById('traffic-chart').style.display='';

    // Info cards
    document.getElementById('d-status-dot').className='status-dot status-dot-animated bg-green flex-shrink-0';
    document.getElementById('d-status-txt').textContent='Online';
    document.getElementById('d-identity').textContent=d.identity||'-';
    document.getElementById('d-model').textContent=d.model||'-';
    document.getElementById('d-version').textContent=d.version||'-';
    document.getElementById('d-uptime').textContent=d.uptime||'-';
    document.getElementById('d-cpu').textContent=(d.cpu||0)+'%';
    document.getElementById('d-ram').textContent=(d.ram||0)+'%';
    document.getElementById('d-ppp').textContent=d.ppp_active||0;

    // Populate interface select (once)
    if (d.interfaces && d.interfaces.length && allInterfaces.length===0) {
      allInterfaces = d.interfaces;
      const sel = document.getElementById('iface-select');
      sel.innerHTML = '';
      d.interfaces.forEach(iface => {
        const opt = document.createElement('option');
        opt.value = iface.name;
        opt.textContent = iface.name + (iface.type ? ' ['+iface.type+']' : '');
        sel.appendChild(opt);
      });
      // Default: pick first non-loopback with traffic
      const best = d.interfaces.find(i=>i.rx_bytes>0) || d.interfaces[0];
      if (best && !selectedIface) {
        selectedIface = best.name;
        sel.value = selectedIface;
      }
    }

    // Interface table
    const tbody = document.getElementById('iface-table');
    tbody.innerHTML = '';
    (d.interfaces||[]).forEach(iface => {
      const running = iface.running ? '<span class="badge bg-green-lt">Up</span>' : '<span class="badge bg-secondary-lt">Down</span>';
      tbody.innerHTML += `<tr class="${iface.name===selectedIface?'table-active':''}">
        <td><span class="fw-semibold">${iface.name}</span></td>
        <td class="text-muted small">${iface.type||'-'}</td>
        <td>${running}</td>
        <td class="text-end text-muted small">${formatBytes(iface.rx_bytes)}</td>
        <td class="text-end text-muted small">${formatBytes(iface.tx_bytes)}</td>
      </tr>`;
    });

    // Traffic chart update for selected interface
    if (d.selected_iface) {
      const now = new Date().toLocaleTimeString('id-ID');
      const rxBytes = parseInt(d.selected_iface.rx_bytes)||0;
      const txBytes = parseInt(d.selected_iface.tx_bytes)||0;

      let rxBps=0, txBps=0;
      if (prevRxBytes !== null) {
        rxBps = Math.max(0, (rxBytes - prevRxBytes) * 8); // per second (polled every ~2s)
        txBps = Math.max(0, (txBytes - prevTxBytes) * 8);
        // Normalize to per-second
        rxBps = Math.round(rxBps / POLL_INTERVAL * 1000);
        txBps = Math.round(txBps / POLL_INTERVAL * 1000);
      }
      prevRxBytes = rxBytes;
      prevTxBytes = txBytes;

      if (chartLabels.length >= MAX_POINTS) { chartLabels.shift(); rxData.shift(); txData.shift(); }
      chartLabels.push(now);
      rxData.push(rxBps);
      txData.push(txBps);
      trafficChart.update();

      rxPeak = Math.max(rxPeak, rxBps);
      txPeak = Math.max(txPeak, txBps);

      document.getElementById('d-rx-now').textContent  = formatBps(rxBps);
      document.getElementById('d-tx-now').textContent  = formatBps(txBps);
      document.getElementById('d-rx-peak').textContent = formatBps(rxPeak);
      document.getElementById('d-tx-peak').textContent = formatBps(txPeak);
      document.getElementById('chart-note').textContent = 'Interface: '+selectedIface;
    }

    // PPPoE sessions
    const ptbody = document.getElementById('ppp-table');
    const sessions = d.ppp_sessions||[];
    document.getElementById('ppp-count-badge').textContent = sessions.length;
    ptbody.innerHTML = '';
    if (!sessions.length) {
      ptbody.innerHTML='<tr><td colspan="4" class="text-center text-muted small">Tidak ada sesi aktif.</td></tr>';
    } else {
      sessions.forEach(s => {
        ptbody.innerHTML += `<tr>
          <td class="fw-semibold small">${s.name||'-'}</td>
          <td class="text-muted small">${s.address||'-'}</td>
          <td class="text-muted small">${s.uptime||'-'}</td>
          <td class="text-muted small">${formatBytes(s['bytes-in']||0)} / ${formatBytes(s['bytes-out']||0)}</td>
        </tr>`;
      });
    }

  } catch(e) {
    console.error('fetchDetail error:', e);
  }
}

const POLL_INTERVAL = 3000; // 3 seconds
let pollTimer = null;

function refreshDetail() {
  prevRxBytes = null; prevTxBytes = null;
  rxPeak = 0; txPeak = 0;
  fetchDetail();
}

document.getElementById('iface-select').addEventListener('change', function() {
  selectedIface = this.value;
  prevRxBytes = null; prevTxBytes = null;
  rxPeak = 0; txPeak = 0;
  rxData.length=0; txData.length=0; chartLabels.length=0;
  trafficChart.update();
});

fetchDetail();
pollTimer = setInterval(fetchDetail, POLL_INTERVAL);
<?php endif; ?>
</script>
</body>
</html>

