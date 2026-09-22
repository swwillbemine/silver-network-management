<?php
// layout/sidebar.php
function nav_active($pages) {
    return \App\Core\Router::is($pages) ? 'active' : '';
}
$user_role = $_SESSION['user_role'] ?? '';
?>
<aside class="navbar navbar-vertical navbar-expand-lg">
  <div class="container-fluid">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="brand-container d-flex align-items-center justify-content-between w-100">
      <h1 class="navbar-brand m-0 p-0 text-start flex-shrink-0">
        <a href="<?= BASE_URL ?>/" class="text-decoration-none text-reset d-flex flex-column justify-content-center">
          <div class="brand-title"><?php echo $isp_name ?? 'SilverNet'; ?></div>
          <div class="brand-subtitle">Silver Network Management</div>
        </a>
      </h1>
      <a class="d-none d-lg-flex flex-shrink-0" id="sidebar-toggle" title="Toggle Sidebar" style="cursor: pointer; margin-right: 0 !important; width: 24px; justify-content: center; padding: 0 !important; border: none !important;">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="4" y="4" width="16" height="16" rx="2" /><line x1="9" y1="4" x2="9" y2="20" /></svg>
      </a>
    </div>

    <div class="collapse navbar-collapse" id="sidebar-menu">
      <ul class="navbar-nav">

        <li class="nav-item">
          <a class="nav-link <?= nav_active(['/', 'index.php']) ?>" title="Dashboard" href="<?= BASE_URL ?>/">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M5 12l-2 0l9 -9l9 9l-2 0"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7"/><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6"/></svg>
            </span>
            <span class="nav-link-title">Dashboard</span>
          </a>
        </li>

        <!-- ── INFRASTRUKTUR ─────────────────── -->
        <li class="nav-item mt-2">
          <span style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;opacity:.4;padding:.5rem 1rem .25rem;display:block;">Infrastruktur</span>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('nodes') ?>" title="Node" href="<?= BASE_URL ?>/nodes">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><circle cx="12" cy="9" r="4"/><path d="M12 13v8"/><path d="M9 17l3 1l3-1"/><circle cx="5" cy="20" r="2"/><circle cx="19" cy="20" r="2"/></svg>
            </span>
            <span class="nav-link-title">Node</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('pops') ?>" title="POP" href="<?= BASE_URL ?>/pops">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><rect x="4" y="4" width="16" height="6" rx="1"/><rect x="4" y="14" width="16" height="6" rx="1"/><line x1="8" y1="7" x2="8" y2="7.01"/><line x1="8" y1="17" x2="8" y2="17.01"/></svg>
            </span>
            <span class="nav-link-title">POP</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('connections') ?>" title="Jalur Koneksi" href="<?= BASE_URL ?>/connections">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 17l4-4 4 4 4-4 4 4"/><path d="M4 7l4 4 4-4 4 4 4-4"/></svg>
            </span>
            <span class="nav-link-title">Jalur Koneksi</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('routers') ?>" title="Router" href="<?= BASE_URL ?>/routers">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><rect x="3" y="4" width="18" height="8" rx="3"/><rect x="3" y="12" width="18" height="8" rx="3"/><line x1="7" y1="8" x2="7" y2="8.01"/><line x1="7" y1="16" x2="7" y2="16.01"/></svg>
            </span>
            <span class="nav-link-title">Router</span>
          </a>
        </li>

        <!-- ── MIKROTIK ──────────────────────── -->
        <?php if (in_array($user_role, ['superadmin','admin','teknisi'])): ?>
        <li class="nav-item mt-2">
          <span style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;opacity:.4;padding:.5rem 1rem .25rem;display:block;">MikroTik</span>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('ip-pool') ?>" title="IP Pool" href="<?= BASE_URL ?>/ip-pool">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><ellipse cx="12" cy="7" rx="9" ry="4"/><path d="M3 7v6c0 2.21 4.03 4 9 4s9-1.79 9-4V7"/><path d="M3 13v6c0 2.21 4.03 4 9 4s9-1.79 9-4v-6"/></svg>
            </span>
            <span class="nav-link-title">IP Pool</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('pppoe-server') ?>" title="PPPoE Server" href="<?= BASE_URL ?>/pppoe-server">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </span>
            <span class="nav-link-title">PPPoE Server</span>
          </a>
        </li>
        <?php endif; ?>

        <!-- ── PELANGGAN ─────────────────────── -->
        <li class="nav-item mt-2">
          <span style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;opacity:.4;padding:.5rem 1rem .25rem;display:block;">Pelanggan</span>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('customers') ?>" title="Data Pelanggan" href="<?= BASE_URL ?>/customers">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.85"/></svg>
            </span>
            <span class="nav-link-title">Data Pelanggan</span>
          </a>
        </li>

        <?php if (in_array($user_role, ['superadmin','admin','kasir'])): ?>
        <li class="nav-item">
          <a class="nav-link <?= nav_active('billing') ?>" title="Tagihan" href="<?= BASE_URL ?>/billing">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M9 5h-2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/></svg>
            </span>
            <span class="nav-link-title">Tagihan</span>
          </a>
        </li>
        <?php endif; ?>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('packages') ?>" title="Paket Internet" href="<?= BASE_URL ?>/packages">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="12 3 20 7.5 20 16.5 12 21 4 16.5 4 7.5 12 3"/><line x1="12" y1="12" x2="20" y2="7.5"/><line x1="12" y1="12" x2="12" y2="21"/><line x1="12" y1="12" x2="4" y2="7.5"/></svg>
            </span>
            <span class="nav-link-title">Paket Internet</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('import/pppoe') ?>" title="Import PPPoE" href="<?= BASE_URL ?>/import/pppoe">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/><polyline points="7 11 12 16 17 11"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
            </span>
            <span class="nav-link-title">Import PPPoE</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('hotspot') ?>" title="Hotspot" href="<?= BASE_URL ?>/hotspot">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><line x1="12" y1="18" x2="12.01" y2="18"/><path d="M9.172 15.172a4 4 0 0 1 5.656 0"/><path d="M6.343 12.343a8 8 0 0 1 11.314 0"/><path d="M3.515 9.515c4.686-4.687 12.284-4.687 16.97 0"/></svg>
            </span>
            <span class="nav-link-title">Hotspot</span>
          </a>
        </li>

        <!-- ── SISTEM ─────────────────────────── -->
        <li class="nav-item mt-2">
          <span style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;opacity:.4;padding:.5rem 1rem .25rem;display:block;">Sistem</span>
        </li>

        <?php if (in_array($user_role, ['superadmin','admin'])): ?>
        <li class="nav-item">
          <a class="nav-link <?= nav_active('users') ?>" title="Manajemen User" href="<?= BASE_URL ?>/users">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </span>
            <span class="nav-link-title">Manajemen User</span>
          </a>
        </li>
        <?php endif; ?>

        <li class="nav-item">
          <a class="nav-link <?= nav_active('settings') ?>" title="Pengaturan" href="<?= BASE_URL ?>/settings">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
            <span class="nav-link-title">Pengaturan</span>
          </a>
        </li>

      </ul>
    </div>

    <!-- LOGGED IN USER (bottom) -->
    <div class="mt-auto border-top w-100 flex-shrink-0">
      <div class="d-flex align-items-center">
        <span class="avatar avatar-sm bg-blue-lt text-blue" style="flex-shrink:0;">
          <?= mb_strtoupper(mb_substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
        </span>
        <div class="user-info flex-grow-1" style="min-width: 0;">
          <div class="fw-semibold small" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; margin-bottom: 2px;">
            <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
          </div>
          <div style="font-size:.65rem;opacity:.5;line-height:1;"><?= htmlspecialchars(ucfirst($_SESSION['user_role'] ?? '')) ?></div>
        </div>
        <a href="<?= BASE_URL ?>/logout" class="text-muted flex-shrink-0" title="Logout">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M9 12h12l-3 -3" /><path d="M18 15l3 -3" /></svg>
        </a>
      </div>
    </div>
  </div>
</aside>