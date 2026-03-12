<?php
// layout/header.php
if (!defined('APP_LOADED')) require_once __DIR__ . '/../config/bootstrap.php';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title><?php echo $full_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet"/>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/favicon.png">
    <style>
      @import url('https://rsms.me/inter/inter.css');
      :root { --tblr-font-sans-serif: 'Inter Var', -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; }
      body { font-feature-settings: "cv03", "cv04", "cv11"; }

      .ts-wrapper .ts-control,
      .ts-wrapper.single .ts-control {
        background-color: var(--tblr-bg-forms, #fff) !important;
        color: var(--tblr-body-color, #1d273b) !important;
        border: 1px solid var(--tblr-border-color, #d0d5dd) !important;
      }
      .ts-dropdown {
        background-color: var(--tblr-bg-surface, #fff) !important;
        color: var(--tblr-body-color, #1d273b) !important;
        border: 1px solid var(--tblr-border-color, #d0d5dd) !important;
        box-shadow: 0 4px 16px rgba(0,0,0,.12) !important;
        z-index: 1060 !important;
      }
      .ts-dropdown .option { color: var(--tblr-body-color, #1d273b) !important; background: transparent !important; }
      .ts-dropdown .option:hover, .ts-dropdown .option.active { background-color: var(--tblr-primary, #206bc4) !important; color: #fff !important; }
      .ts-dropdown .option.selected { background-color: rgba(32,107,196,0.1) !important; }
      .ts-wrapper .ts-control input, .ts-wrapper .ts-control input::placeholder { color: var(--tblr-muted, #6c7a91) !important; }
      .ts-dropdown input { background: var(--tblr-bg-surface, #fff) !important; color: var(--tblr-body-color, #1d273b) !important; border-bottom: 1px solid var(--tblr-border-color, #d0d5dd) !important; }
      [data-bs-theme="dark"] .ts-wrapper .ts-control,
      [data-bs-theme="dark"] .ts-wrapper.single .ts-control { background-color: #1e2635 !important; color: #c8d3e1 !important; border-color: #374151 !important; }
      [data-bs-theme="dark"] .ts-dropdown { background-color: #1e2635 !important; color: #c8d3e1 !important; border-color: #374151 !important; }
      [data-bs-theme="dark"] .ts-dropdown .option { color: #c8d3e1 !important; }
      [data-bs-theme="dark"] .ts-dropdown input { background: #1e2635 !important; color: #c8d3e1 !important; }
    </style>
</head>
<body>
<script>
    var themeStorageKey = 'tablerTheme';
    var defaultTheme = 'light';
    var selectedTheme = localStorage.getItem(themeStorageKey);
    if (!selectedTheme) {
        selectedTheme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }
    document.body.setAttribute("data-bs-theme", selectedTheme);
</script>

<div class="page">
    <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none">
        <div class="container-xl">
            <div class="navbar-nav flex-row order-md-last">
                <div class="d-none d-md-flex">
                    <a href="?theme=dark" class="nav-link px-0 hide-theme-dark" title="Enable dark mode" data-bs-toggle="tooltip" data-bs-placement="bottom" onclick="setTheme('dark'); return false;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1 -8.313 -12.454z" /></svg>
                    </a>
                    <a href="?theme=light" class="nav-link px-0 hide-theme-light" title="Enable light mode" data-bs-toggle="tooltip" data-bs-placement="bottom" onclick="setTheme('light'); return false;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="4" /><path d="M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7" /></svg>
                    </a>
                </div>
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown">
                        <!-- <span class="avatar avatar-sm" style="background-image: url(https://ui-avatars.com/api/?name=Admin)"></span> -->
                        <div class="d-none d-xl-block ps-2">
                            <div><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></div>
                            <div class="mt-1 small text-muted"><?php echo $_SESSION['user_role'] ?? 'Superadmin'; ?></div>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        <a href="<?= BASE_URL ?>/auth/logout.php" class="dropdown-item">Logout</a>
                    </div>
                </div>
            </div>
            <div class="collapse navbar-collapse" id="navbar-menu">
                <span class="navbar-text">
                    <b><?php echo $isp_name; ?></b>
                </span>
            </div>
        </div>
    </header>

<script>
function setTheme(theme) {
    localStorage.setItem(themeStorageKey, theme);
    document.body.setAttribute("data-bs-theme", theme);
}
</script>