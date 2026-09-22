<?php
$title = 'Dashboard';
\App\Core\View::header(['title' => $title]);
?>
<style>
  /* -------------------------------------------------------------
     DASHBOARD RESPONSIVE STYLES
     ------------------------------------------------------------- */
  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }

  /* 1. Global Stat Cards Adaptive Grid */
  .dash-stat-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
  }

  /* Small devices (tablets/phones in landscape, 576px and up) */
  @media (min-width: 576px) {
    .dash-stat-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1rem;
    }
  }

  /* Medium & Large devices (standard laptops, desktops with sidebar, 992px to 1399px) */
  @media (min-width: 992px) {
    .dash-stat-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1rem;
    }
  }

  /* Extra large desktop (1400px and up) */
  @media (min-width: 1400px) {
    .dash-stat-grid {
      grid-template-columns: repeat(6, minmax(0, 1fr));
      gap: 1rem;
    }
  }

  /* When sidebar is collapsed on desktop (>= 1200px), 6 columns fit smoothly */
  @media (min-width: 1200px) {
    body.sidebar-collapsed .dash-stat-grid {
      grid-template-columns: repeat(6, minmax(0, 1fr));
    }
  }

  /* Ultra compact screens (< 360px) */
  @media (max-width: 359.98px) {
    .dash-stat-grid {
      grid-template-columns: 1fr;
      gap: 0.5rem;
    }
  }

  /* 2. Stat Card Component & Internal Spacing */
  .dash-stat-card {
    min-width: 0;
    height: 100%;
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
  }

  .dash-stat-card .card-body {
    padding: 0.75rem 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
  }

  @media (max-width: 575.98px) {
    .dash-stat-card .card-body {
      padding: 0.7rem 0.625rem;
      gap: 0.5rem;
    }
  }

  /* Avatar icon */
  .dash-stat-avatar {
    flex-shrink: 0;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  @media (max-width: 575.98px) {
    .dash-stat-avatar {
      width: 2.25rem;
      height: 2.25rem;
    }
    .dash-stat-avatar svg {
      width: 18px;
      height: 18px;
    }
  }

  /* Content area containing value and label */
  .dash-stat-content {
    min-width: 0;
    flex: 1 1 0%;
    overflow: hidden;
  }

  .dash-stat-value {
    font-size: 1.15rem;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.01em;
    color: var(--tblr-body-color, #1d273b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .dash-stat-value-sm {
    font-size: 1rem;
  }

  @media (min-width: 992px) and (max-width: 1399.98px) {
    .dash-stat-value {
      font-size: 1.25rem;
    }
    .dash-stat-value-sm {
      font-size: 1.1rem;
    }
  }

  [data-bs-theme="dark"] .dash-stat-value {
    color: #f8fafc;
  }

  .dash-stat-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--tblr-muted, #6c7a91);
    line-height: 1.25;
    margin-top: 0.2rem;
    white-space: normal;
    word-break: normal;
    overflow-wrap: normal;
  }

  @media (max-width: 575.98px) {
    .dash-stat-label {
      font-size: 0.7rem;
    }
  }

  /* 3. Infra Stats Cards */
  .infra-card .card-body {
    padding: 0.625rem 0.5rem;
  }
  .infra-card .infra-title {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: var(--tblr-muted, #6c7a91);
  }
  .infra-card .infra-num {
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.2;
  }

  /* 4. Router Cards Interface Table */
  .table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .interface-name {
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: inline-block;
    vertical-align: middle;
  }
</style>
<body>
<div class="page">
  <?php \App\Core\View::sidebar(); ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center g-2">
          <div class="col-12 col-sm">
            <div class="page-pretitle">Overview</div>
            <h2 class="page-title">Dashboard</h2>
          </div>
          <div class="col-12 col-sm-auto">
            <div class="d-flex align-items-center justify-content-between justify-content-sm-end gap-2">
              <span class="text-muted small" id="last-updated-label" style="font-size:.75rem;"></span>
              <div class="d-flex align-items-center gap-1">
                <svg id="refresh-spinner" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" style="display:none;animation:spin .6s linear infinite;"><path d="M4 4v6h6"/><path d="M20 20v-6h-6"/><path d="M20 10a8 8 0 00-14.93-2M4 14a8 8 0 0014.93 2"/></svg>
                <span class="badge" id="refresh-badge" style="min-width:52px;cursor:pointer;" onclick="manualRefresh()" title="Klik untuk refresh sekarang">-- s</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">

        <!-- GLOBAL STATS -->
        <div class="dash-stat-grid mb-3" id="global-stats">
          <div class="card card-sm card-hover dash-stat-card">
            <div class="card-body">
              <span class="bg-blue text-white avatar dash-stat-avatar"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.85"/></svg></span>
              <div class="dash-stat-content">
                <div class="dash-stat-value" id="g-customers" title="--">--</div>
                <div class="dash-stat-label">Pelanggan</div>
              </div>
            </div>
          </div>
          <div class="card card-sm card-hover dash-stat-card">
            <div class="card-body">
              <span class="bg-green text-white avatar dash-stat-avatar"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 11l2 2l4-4"/></svg></span>
              <div class="dash-stat-content">
                <div class="dash-stat-value" id="g-online" title="--">--</div>
                <div class="dash-stat-label">Online PPPoE</div>
              </div>
            </div>
          </div>
          <div class="card card-sm card-hover dash-stat-card">
            <div class="card-body">
              <span class="bg-cyan text-white avatar dash-stat-avatar"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="9" width="14" height="10" rx="2"/><circle cx="14" cy="14" r="2"/><path d="M17 9v-2a2 2 0 0 0-2-2h-10a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/></svg></span>
              <div class="dash-stat-content">
                <div class="dash-stat-value dash-stat-value-sm" id="g-income" title="--">--</div>
                <div class="dash-stat-label">Pendapatan</div>
              </div>
            </div>
          </div>
          <div class="card card-sm card-hover dash-stat-card">
            <div class="card-body">
              <span class="bg-indigo text-white avatar dash-stat-avatar"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 17l6-6 4 4 6-6"/><path d="M4 7h16"/></svg></span>
              <div class="dash-stat-content">
                <div class="dash-stat-value" id="g-rx" title="--">--</div>
                <div class="dash-stat-label">Download (RX)</div>
              </div>
            </div>
          </div>
          <div class="card card-sm card-hover dash-stat-card">
            <div class="card-body">
              <span class="bg-orange text-white avatar dash-stat-avatar"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 7l6 6 4-4 6 6"/><path d="M4 17h16"/></svg></span>
              <div class="dash-stat-content">
                <div class="dash-stat-value" id="g-tx" title="--">--</div>
                <div class="dash-stat-label">Upload (TX)</div>
              </div>
            </div>
          </div>
          <div class="card card-sm card-hover dash-stat-card">
            <div class="card-body">
              <span class="bg-teal text-white avatar dash-stat-avatar"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><rect x="3" y="4" width="18" height="8" rx="3"/><rect x="3" y="12" width="18" height="8" rx="3"/><line x1="7" y1="8" x2="7" y2="8.01"/><line x1="7" y1="16" x2="7" y2="16.01"/></svg></span>
              <div class="dash-stat-content">
                <div class="dash-stat-value"><span id="g-online-rt">-</span>/<span id="g-total-rt">-</span></div>
                <div class="dash-stat-label">Router Online</div>
              </div>
            </div>
          </div>
        </div>

        <!-- INFRA STATS -->
        <div class="row g-2 mb-3 align-items-stretch" id="infra-stats">
          <div class="col-6 col-sm-3">
            <div class="card card-sm h-100 infra-card">
              <div class="card-body text-center py-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon text-primary mb-1 d-block mx-auto" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="3" y="4" width="18" height="8" rx="3" /><rect x="3" y="12" width="18" height="8" rx="3" /><line x1="7" y1="8" x2="7" y2="8.01" /><line x1="7" y1="16" x2="7" y2="16.01" /></svg>
                <div class="infra-title">NODE</div>
                <div class="infra-num" id="i-nodes">-</div>
              </div>
            </div>
          </div>
          <div class="col-6 col-sm-3">
            <div class="card card-sm h-100 infra-card">
              <div class="card-body text-center py-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon text-teal mb-1 d-block mx-auto" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="11" r="3" /><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0z" /></svg>
                <div class="infra-title">POP</div>
                <div class="infra-num" id="i-pops">-</div>
              </div>
            </div>
          </div>
          <div class="col-6 col-sm-3">
            <div class="card card-sm h-100 infra-card">
              <div class="card-body text-center py-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon text-azure mb-1 d-block mx-auto" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="3" y="13" width="18" height="8" rx="2" /><line x1="17" y1="17" x2="17" y2="17.01" /><line x1="13" y1="17" x2="13" y2="17.01" /><line x1="15" y1="13" x2="15" y2="11" /><path d="M11.75 8.75a4 4 0 0 1 6.5 0" /><path d="M8.5 6.5a8 8 0 0 1 13 0" /></svg>
                <div class="infra-title">ROUTER</div>
                <div class="infra-num" id="i-mikrotiks">-</div>
              </div>
            </div>
          </div>
          <div class="col-6 col-sm-3">
            <div class="card card-sm h-100 infra-card">
              <div class="card-body text-center py-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon text-orange mb-1 d-block mx-auto" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="18" x2="12.01" y2="18" /><path d="M9.172 15.172a4 4 0 0 1 5.656 0" /><path d="M6.343 12.343a8 8 0 0 1 11.314 0" /><path d="M3.515 9.515c4.686 -4.686 12.284 -4.686 16.97 0" /></svg>
                <div class="infra-title">HOTSPOT</div>
                <div class="infra-num" id="i-hotspots">-</div>
              </div>
            </div>
          </div>
        </div>

        <!-- TRAFFIC SOURCE NOTE -->
        <div class="alert alert-info alert-dismissible d-none mb-3" id="traffic-note">
          <div class="text-muted small"><strong>Sumber traffic:</strong> <span id="traffic-sources"></span></div>
        </div>

        <!-- CHARTS SECTION -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-lg-8">
            <div class="card h-100">
              <div class="card-header border-0 pb-0">
                <h3 class="card-title">Grafik Pertumbuhan Pelanggan</h3>
              </div>
              <div class="card-body px-2 pb-0" style="min-width:0; overflow:hidden;">
                <div id="chart-customers" style="min-height: 250px;"></div>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-4">
            <div class="card h-100">
              <div class="card-header border-0 pb-0">
                <h3 class="card-title">Status Router</h3>
              </div>
              <div class="card-body d-flex align-items-center justify-content-center" style="min-width:0; overflow:hidden;">
                <div id="chart-routers" style="min-height: 200px;"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- ROUTER DETAIL CARDS -->
        <div class="row g-3" id="router-cards">
          <div class="col-12 text-center text-muted py-5">
            <div class="spinner-border spinner-border-sm me-2"></div> Memuat data router...
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
// Safe helper: returns fallback if value is null/undefined
const safe = (v, fallback = '-') => (v !== null && v !== undefined) ? v : fallback;
const safeNum = (v, fallback = 0) => {
  const n = parseInt(v, 10);
  return isNaN(n) ? fallback : n;
};
const setText = (id, val) => {
  const el = document.getElementById(id);
  if (el) {
    el.textContent = val;
    el.setAttribute('title', val);
  }
};

function showApiError(msg) {
  const container = document.getElementById('router-cards');
  if (container) {
    container.innerHTML = `
      <div class="col-12">
        <div class="alert alert-danger d-flex align-items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon flex-shrink-0" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div><strong>Gagal memuat data dashboard.</strong><br><span class="small text-muted">${msg}</span></div>
          <button class="btn btn-sm btn-outline-danger ms-auto" onclick="loadDashboard()">Coba Lagi</button>
        </div>
      </div>`;
  }
}

async function loadDashboard() {
  try {
    const res = await fetch('api/dashboard.php');

    // Check HTTP status first
    if (!res.ok) {
      showApiError('HTTP ' + res.status + ' dari /api/dashboard.php');
      return;
    }

    const text = await res.text();

    // Try parsing JSON — if PHP has warnings/notices they'll break JSON
    let data;
    try {
      data = JSON.parse(text);
    } catch (jsonErr) {
      // Show first 300 chars of raw response to help debug PHP errors
      showApiError('Response bukan JSON yang valid. Output PHP:<br><code style="font-size:.75rem;">' +
        text.substring(0, 300).replace(/</g, '&lt;') + '</code>');
      return;
    }

    // API-level error
    if (data.error) {
      showApiError(data.error);
      return;
    }

    // Guard: ensure expected keys exist
    const g   = data.global_stats  || {};
    const inf = data.infra_stats   || {};
    const routers = Array.isArray(data.routers_detail) ? data.routers_detail : [];

    // ── Global stats ─────────────────────────────────────────────────────────
    setText('g-customers',  safeNum(g.total_customers).toLocaleString('id-ID'));
    setText('g-online',     safeNum(g.total_online_ppp).toLocaleString('id-ID'));
    setText('g-income',     safe(g.total_income, 'Rp 0'));
    setText('g-rx',         safe(g.traffic_rx, '0 B'));
    setText('g-tx',         safe(g.traffic_tx, '0 B'));
    setText('g-online-rt',  safeNum(inf.online_mikrotiks));
    setText('g-total-rt',   safeNum(inf.mikrotiks));
    setText('i-nodes',      safeNum(inf.nodes));
    setText('i-pops',       safeNum(inf.pops));
    setText('i-mikrotiks',  safeNum(inf.mikrotiks));
    setText('i-hotspots',   safeNum(inf.hotspots));
    setText('last-updated', data.updated_at ? 'Diperbarui ' + data.updated_at : '');

    // Update Router Chart dynamically
    if (window.routerChart) {
      const onlineRouters = safeNum(inf.online_mikrotiks);
      const offlineRouters = safeNum(inf.mikrotiks) - onlineRouters;
      // Prevent all-zero donut which breaks apexcharts rendering
      if (onlineRouters === 0 && offlineRouters === 0) {
        window.routerChart.updateSeries([0, 1]); // Show 1 offline as empty state
      } else {
        window.routerChart.updateSeries([onlineRouters, offlineRouters]);
      }
    }

    // Update Customer Growth Chart
    if (window.customerChart && data.chart_data) {
      window.customerChart.updateSeries([{
        name: "Pelanggan Aktif",
        data: data.chart_data.customer_growth || []
      }]);
      window.customerChart.updateOptions({
        xaxis: {
          categories: data.chart_data.customer_labels || []
        }
      });
    }

    // Traffic sources note
    const sources = Array.isArray(g.traffic_sources) ? g.traffic_sources : [];
    const noteEl  = document.getElementById('traffic-note');
    const srcEl   = document.getElementById('traffic-sources');
    if (noteEl && srcEl && sources.length) {
      noteEl.classList.remove('d-none');
      srcEl.textContent = sources.join(', ');
    }

    // ── Router Cards ─────────────────────────────────────────────────────────
    const container = document.getElementById('router-cards');
    if (!container) return;
    container.innerHTML = '';

    if (!routers.length) {
      container.innerHTML = '<div class="col-12 text-center text-muted py-5">Belum ada router terdaftar. <a href="<?= BASE_URL ?>/routers">Tambah router</a></div>';
      return;
    }

    routers.forEach(r => {
      const online  = r.status === 'online';
      const cpu     = safeNum(r.cpu);
      const ram     = safeNum(r.ram);
      const pppActive = safeNum(r.ppp_active);

      const cpuColor = cpu > 80 ? 'bg-red' : cpu > 60 ? 'bg-yellow' : 'bg-blue';
      const ramColor = ram > 85 ? 'bg-red' : ram > 70 ? 'bg-yellow' : 'bg-teal';

      const cpuBar = `<div class="progress mb-1" style="height:4px;">
        <div class="progress-bar ${cpuColor}" style="width:${cpu}%"></div></div>`;
      const ramBar = `<div class="progress" style="height:4px;">
        <div class="progress-bar ${ramColor}" style="width:${ram}%"></div></div>`;

      // Interfaces table rows
      const ifaces = Array.isArray(r.interfaces) ? r.interfaces : [];
      let ifaceRows = '';
      ifaces.forEach(iface => {
        const ifName = safe(iface.name);
        ifaceRows += `<tr>
          <td class="text-muted small pe-2"><span class="interface-name" title="${ifName}">${ifName}</span></td>
          <td class="small text-end text-nowrap">${safe(iface.rx_fmt, '0 B')}</td>
          <td class="small text-end text-nowrap">${safe(iface.tx_fmt, '0 B')}</td>
        </tr>`;
      });

      const identity = safe(r.identity, safe(r.name, 'Router'));
      const model    = safe(r.model, '-');
      const name     = safe(r.name, '');
      const version  = safe(r.version, '-');
      const uptime   = safe(r.uptime, '-');
      const rxFmt    = safe(r.traffic_rx_fmt, '0 B');
      const txFmt    = safe(r.traffic_tx_fmt, '0 B');
      const host     = safe(r.host, '-');
      const id       = safe(r.id, '');

      container.innerHTML += `
      <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100">
          <div class="card-header" style="border-bottom:2px solid ${online ? 'var(--tblr-green)' : 'var(--tblr-red)'};">
            <div class="d-flex align-items-center gap-2 w-100 overflow-hidden">
              <span class="status-dot status-dot-animated ${online ? 'bg-green' : 'bg-red'} flex-shrink-0"></span>
              <div class="overflow-hidden flex-grow-1" style="min-width:0;">
                <div class="fw-bold text-truncate" title="${identity}">${identity}</div>
                <div class="text-muted text-truncate" style="font-size:.75rem;" title="${name} — ${model}">${name} &bull; ${model}</div>
              </div>
              <div class="ms-auto flex-shrink-0 text-end ps-2">
                <div class="fs-3 fw-bold text-${online ? 'green' : 'secondary'} lh-1">${pppActive}</div>
                <div style="font-size:.65rem;opacity:.6;">PPPoE</div>
              </div>
            </div>
          </div>

          <div class="card-body py-2 px-3">
            ${online ? `
              <div class="row g-2 mb-2">
                <div class="col-6">
                  <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">CPU</span>
                    <span class="small fw-bold">${cpu}%</span>
                  </div>${cpuBar}
                </div>
                <div class="col-6">
                  <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">RAM</span>
                    <span class="small fw-bold">${ram}%</span>
                  </div>${ramBar}
                </div>
              </div>

              <div class="row g-1 mb-2 text-center">
                <div class="col-4">
                  <div class="text-muted" style="font-size:.65rem;">UPTIME</div>
                  <div class="small fw-semibold text-truncate" title="${uptime}">${uptime}</div>
                </div>
                <div class="col-4">
                  <div class="text-muted" style="font-size:.65rem;">RX</div>
                  <div class="small fw-semibold">${rxFmt}</div>
                </div>
                <div class="col-4">
                  <div class="text-muted" style="font-size:.65rem;">TX</div>
                  <div class="small fw-semibold">${txFmt}</div>
                </div>
              </div>

              <div class="text-muted mb-1" style="font-size:.7rem;">RouterOS ${version}</div>

              ${ifaceRows ? `
              <div class="table-responsive mb-0">
                <table class="table table-sm table-borderless mb-0" style="font-size:.75rem;">
                  <thead>
                    <tr>
                      <th class="text-muted fw-normal py-0 ps-0">Interface</th>
                      <th class="text-muted fw-normal py-0 text-end">RX</th>
                      <th class="text-muted fw-normal py-0 text-end">TX</th>
                    </tr>
                  </thead>
                  <tbody>${ifaceRows}</tbody>
                </table>
              </div>` : '<div class="text-muted small">Tidak ada data interface.</div>'}
            ` : `
              <div class="text-center text-muted py-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon text-red mb-2" width="28" height="28" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div class="small fw-semibold">Tidak Terjangkau</div>
                <div class="text-muted" style="font-size:.75rem;">${host}</div>
              </div>
            `}
          </div>

          <div class="card-footer py-1 px-3">
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-muted small">${host}</span>
              <a href="<?= BASE_URL ?>/routers?id=${id}" class="btn btn-sm btn-ghost-secondary py-0">Detail</a>
            </div>
          </div>
        </div>
      </div>`;
    });

  } catch (e) {
    console.error('loadDashboard error:', e);
    showApiError(e.message || 'Unknown error');
  }
}

// ── Countdown refresh timer ───────────────────────────────────────────────
const REFRESH_SEC = 10;
let countdown      = REFRESH_SEC;
let lastUpdateTime = null;
let isLoading      = false;

function updateBadge() {
  const badge   = document.getElementById('refresh-badge');
  const spinner = document.getElementById('refresh-spinner');
  const label   = document.getElementById('last-updated-label');
  if (!badge) return;
  if (isLoading) {
    badge.className   = 'badge bg-blue-lt text-blue';
    badge.textContent = 'memuat...';
    if (spinner) spinner.style.display = 'inline-block';
  } else {
    if (spinner) spinner.style.display = 'none';
    badge.className   = countdown <= 3 ? 'badge bg-orange-lt text-orange' : 'badge bg-green-lt text-green';
    badge.textContent = countdown + 's';
  }
  if (lastUpdateTime && label) {
    const secs = Math.round((Date.now() - lastUpdateTime) / 1000);
    label.textContent = secs < 5 ? 'Baru saja' : secs + 's lalu';
  }
}

async function manualRefresh() {
  countdown = 1;
}

async function doRefresh() {
  if (isLoading) return;
  isLoading = true;
  countdown = REFRESH_SEC;
  updateBadge();
  await loadDashboard();
  lastUpdateTime = Date.now();
  isLoading = false;
  updateBadge();
}

setInterval(() => {
  if (!isLoading) { countdown--; updateBadge(); }
  if (countdown <= 0) doRefresh();
}, 1000);

doRefresh();

// ── Initialize Charts ──────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", function () {
  // Chart Customers (Dummy Data initial setup)
  window.customerChartOptions = {
    chart: {
      type: "area",
      fontFamily: 'inherit',
      height: 250,
      parentHeightOffset: 0,
      toolbar: { show: false },
      animations: { enabled: true }
    },
    dataLabels: { enabled: false },
    fill: { opacity: 0.16, type: 'solid' },
    stroke: { width: 2, lineCap: "round", curve: "smooth" },
    series: [{
      name: "Pelanggan Aktif",
      data: [0,0,0,0,0,0,0,0,0,0,0,0]
    }],
    grid: {
      padding: { top: -20, right: 0, left: -4, bottom: -4 },
      strokeDashArray: 4,
    },
    xaxis: {
      labels: { padding: 0 },
      tooltip: { enabled: false },
      axisBorder: { show: false },
      categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    },
    yaxis: { labels: { padding: 4 } },
    colors: ["var(--tblr-primary)"],
    legend: { show: false }
  };
  
  if (document.getElementById('chart-customers')) {
    window.customerChart = new ApexCharts(document.getElementById('chart-customers'), window.customerChartOptions);
    window.customerChart.render();
  }

  // Chart Routers (Will be updated dynamically in loadDashboard, setting up empty first)
  window.routerChartOptions = {
    chart: {
      type: "donut",
      fontFamily: 'inherit',
      height: 200,
      sparkline: { enabled: true },
      animations: { enabled: true }
    },
    fill: { opacity: 1 },
    series: [1, 1],
    labels: ["Online", "Offline"],
    tooltip: {
      theme: 'dark'
    },
    grid: { strokeDashArray: 4 },
    colors: ["var(--tblr-green)", "var(--tblr-red)"],
    legend: {
      show: true,
      position: 'bottom',
      offsetY: 12,
      markers: { width: 10, height: 10, radius: 100 },
      itemMargin: { horizontal: 8, vertical: 8 },
    },
  };
  
  if (document.getElementById('chart-routers')) {
    window.routerChart = new ApexCharts(document.getElementById('chart-routers'), window.routerChartOptions);
    window.routerChart.render();
  }
});
</script>
</body>
</html>

