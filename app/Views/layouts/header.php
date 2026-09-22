<?php
// layout/header.php
if (!defined('APP_LOADED')) require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

$page_title = !empty($title) ? htmlspecialchars($title) . ' - ' . htmlspecialchars($GLOBALS['app_name'] ?? 'SilverNet') : htmlspecialchars($full_title ?? $GLOBALS['full_title'] ?? 'Silver Network Management');
$header_isp_name = htmlspecialchars($isp_name ?? $GLOBALS['isp_name'] ?? 'SilverNet');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet"/>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/favicon.png">
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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

      /* Custom UI Improvements */
      :root,
      [data-bs-theme="light"],
      [data-bs-theme="dark"],
      body {
        --tblr-border-radius: 4px;
        --tblr-btn-border-radius: 4px;
      }
      .btn {
        --tblr-btn-border-radius: 4px !important;
        border-radius: 4px;
      }
      .card {
        border-radius: 0.75rem !important;
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
      }
      .card-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
      }
      [data-bs-theme="dark"] .card {
        border-radius: 0.75rem !important;
        border: 1px solid rgba(255, 255, 255, 0.05);
        background-color: #1a2230;
      }
      [data-bs-theme="dark"] .card-hover:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
      }
      .badge {
        border-radius: 6px;
        font-weight: 500;
        padding: 0.35em 0.6em;
      }
      
      /* Sidebar Collapse styles for Desktop */
      @media (min-width: 992px) {
        /* 1. Global Dimensions & Transitions */
        .navbar-vertical {
          width: 224px !important;
          min-width: 224px;
          max-width: 224px;
          position: fixed !important;
          top: 0 !important;
          left: 0 !important;
          bottom: 0 !important;
          z-index: 1030 !important;
          transform: translateX(0) !important;
          opacity: 1 !important;
          visibility: visible !important;
          pointer-events: auto !important;
          transition: width 380ms cubic-bezier(0.25, 1, 0.5, 1) !important;
          overflow: hidden !important;
          display: flex !important;
          flex-direction: column !important;
        }

        body.sidebar-collapsed .navbar-vertical {
          width: 72px !important;
          min-width: 72px !important;
          max-width: 72px !important;
          transform: translateX(0) !important;
          opacity: 1 !important;
          visibility: visible !important;
          pointer-events: auto !important;
        }

        /* Topbar Header: aligns with sidebar when open, full width when collapsed */
        .page > header.navbar,
        .navbar-vertical ~ .navbar,
        .navbar-expand-lg.navbar-vertical ~ .navbar {
          margin-left: 224px !important;
          width: calc(100% - 224px) !important;
          max-width: calc(100% - 224px) !important;
          min-width: 0 !important;
          transition: margin-left 380ms cubic-bezier(0.25, 1, 0.5, 1), width 380ms cubic-bezier(0.25, 1, 0.5, 1), max-width 380ms cubic-bezier(0.25, 1, 0.5, 1) !important;
        }

        body.sidebar-collapsed .page > header.navbar,
        body.sidebar-collapsed .navbar-vertical ~ .navbar,
        body.sidebar-collapsed .navbar-expand-lg.navbar-vertical ~ .navbar {
          margin-left: 72px !important;
          width: calc(100% - 72px) !important;
          max-width: calc(100% - 72px) !important;
        }

        /* Main Content Wrapper: margin-left offset ONLY, width: auto prevents conflict & overflow */
        .page-wrapper,
        .navbar-vertical ~ .page-wrapper,
        .navbar-expand-lg.navbar-vertical ~ .page-wrapper,
        .navbar-expand-xl.navbar-vertical ~ .page-wrapper,
        .navbar-expand-xxl.navbar-vertical ~ .page-wrapper,
        .navbar-expand.navbar-vertical ~ .page-wrapper {
          margin-left: 224px !important;
          width: auto !important;
          max-width: 100% !important;
          min-width: 0 !important;
          transition: margin-left 380ms cubic-bezier(0.25, 1, 0.5, 1) !important;
        }

        body.sidebar-collapsed .page-wrapper,
        body.sidebar-collapsed .navbar-vertical ~ .page-wrapper,
        body.sidebar-collapsed .navbar-expand-lg.navbar-vertical ~ .page-wrapper,
        body.sidebar-collapsed .navbar-expand-xl.navbar-vertical ~ .page-wrapper,
        body.sidebar-collapsed .navbar-expand-xxl.navbar-vertical ~ .page-wrapper,
        body.sidebar-collapsed .navbar-expand.navbar-vertical ~ .page-wrapper {
          margin-left: 72px !important;
          width: auto !important;
          max-width: 100% !important;
        }

        /* Full-width container utilization when sidebar is collapsed */
        body.sidebar-collapsed .container-xl,
        body.sidebar-collapsed .page-header .container-xl,
        body.sidebar-collapsed .page-body .container-xl {
          width: 100% !important;
          max-width: 100% !important;
          padding-left: 1.5rem !important;
          padding-right: 1.5rem !important;
        }

        @media (min-width: 1921px) {
          body.sidebar-collapsed .container-xl {
            max-width: 1800px !important;
            margin-left: auto !important;
            margin-right: auto !important;
          }
        }

        /* 2. Remove Tabler Default Padding so our Flex rules dominate */
        .navbar-vertical .container-fluid {
          padding: 0 !important;
        }

        /* 3. Text and Sub-elements Transition */
        .navbar-vertical .nav-link-title,
        .navbar-vertical li > span,
        .navbar-vertical .mt-auto .user-info,
        .navbar-vertical .mt-auto .avatar {
          transition: opacity 240ms ease, max-width 380ms cubic-bezier(0.25, 1, 0.5, 1), margin 380ms ease, transform 380ms cubic-bezier(0.25, 1, 0.5, 1) !important;
          opacity: 1;
          max-width: 250px;
          white-space: nowrap;
          overflow: hidden;
          transform: translateX(0);
        }

        /* When Collapsed: Shrink Text and Avatars completely */
        body.sidebar-collapsed .navbar-vertical .nav-link-title,
        body.sidebar-collapsed .navbar-vertical li > span,
        body.sidebar-collapsed .navbar-vertical .mt-auto .user-info,
        body.sidebar-collapsed .navbar-vertical .mt-auto .avatar {
          opacity: 0 !important;
          max-width: 0 !important;
          min-width: 0 !important;
          margin: 0 !important;
          padding: 0 !important;
          border: none !important;
          pointer-events: none !important;
          overflow: hidden !important;
          transform: translateX(-4px) !important;
        }

        /* 4. Unified Fixed-Padding Layout for Icons */
        /* Left padding 24px + Icon 24px + Right padding 24px = exactly 72px collapsed width!
           Because padding is identical in both states, icons NEVER shift horizontally. */
           
        /* Nav Item Height & Gap Control */
        .navbar-vertical .nav-item {
          transition: margin 380ms cubic-bezier(0.25, 1, 0.5, 1), max-height 380ms cubic-bezier(0.25, 1, 0.5, 1) !important;
          margin-bottom: 2px !important; /* Compact gap */
        }
        
        .navbar-vertical .nav-link {
          height: 36px !important; /* Compressed height for both states */
          padding-top: 0 !important;
          padding-bottom: 0 !important;
          padding-left: 24px !important;
          padding-right: 24px !important;
          display: flex !important;
          align-items: center !important;
          justify-content: flex-start !important;
          flex-wrap: nowrap !important;
        }

        .navbar-vertical .mt-auto > div {
          padding-left: 24px !important;
          padding-right: 24px !important;
          display: flex !important;
          align-items: center !important;
          justify-content: flex-start !important;
          flex-wrap: nowrap !important;
        }

        /* Strict Sidebar Layout Architecture */
        .navbar-vertical .container-fluid {
          display: flex !important;
          flex-direction: column !important;
          height: 100% !important;
          flex: 1 !important;
          min-height: 0 !important;
          padding: 0 !important;
        }
        
        .navbar-vertical .navbar-collapse {
          flex: 1 !important;
          min-height: 0 !important;
          overflow-y: auto !important;
          overflow-x: hidden !important;
        }

        /* Specific fix for Section Separators (Infrastruktur, MikroTik, Sistem) */
        .navbar-vertical li.mt-2 {
          max-height: 40px;
          overflow: hidden;
          margin-top: 16px !important;
          margin-bottom: 6px !important;
        }
        
        body.sidebar-collapsed .navbar-vertical li.mt-2 {
          margin-top: 0 !important;
          margin-bottom: 0 !important;
          max-height: 0 !important;
          border: none !important;
        }
        
        .navbar-vertical li > span {
          display: block !important;
          padding: 0 !important; /* Override inline styling */
          padding-left: 24px !important;
          padding-right: 24px !important;
        }

        /* 5. Icons configuration */
        .navbar-vertical .nav-link-icon,
        .navbar-vertical .brand-container a#sidebar-toggle,
        .navbar-vertical .mt-auto > div > a.text-muted {
          width: 24px !important;
          min-width: 24px !important;
          height: 24px !important;
          display: flex !important;
          justify-content: center !important;
          align-items: center !important;
          margin: 0 !important;
          padding: 0 !important;
          flex-shrink: 0 !important;
        }

        /* Space between icon and text when expanded */
        .navbar-vertical .nav-link-icon {
          margin-right: 12px !important;
          transition: margin-right 380ms cubic-bezier(0.25, 1, 0.5, 1) !important;
        }
        body.sidebar-collapsed .navbar-vertical .nav-link-icon {
          margin-right: 0 !important;
        }

        /* 6. Header Brand & Hamburger Fix */
        .navbar-vertical .brand-container {
          padding-left: 16px !important;
          padding-right: 12px !important;
          padding-top: 14px !important;
          padding-bottom: 14px !important;
          display: flex !important;
          align-items: center !important;
          justify-content: space-between !important;
          flex-wrap: nowrap !important;
          overflow: hidden !important;
          transition: padding 380ms cubic-bezier(0.25, 1, 0.5, 1) !important;
        }

        body.sidebar-collapsed .navbar-vertical .brand-container {
          padding-left: 20px !important;
          padding-right: 20px !important;
          justify-content: center !important;
        }
        
        .navbar-vertical .brand-title {
          font-weight: 700 !important;
          font-size: 15px !important;
          color: inherit !important;
          letter-spacing: -0.01em;
          line-height: 1.1;
          margin-bottom: 2px;
          text-align: left !important;
          white-space: nowrap !important;
        }
        
        .navbar-vertical .brand-subtitle {
          font-size: 8.5px !important; 
          font-weight: 500 !important;
          color: inherit !important;
          opacity: 0.6 !important;
          letter-spacing: 0.03em !important;
          text-transform: uppercase;
          line-height: 1;
          text-align: left !important;
          white-space: nowrap !important;
        }

        .navbar-vertical .brand-container h1,
        .navbar-vertical .brand-container .navbar-brand {
          margin: 0 !important; 
          padding: 0 !important;
          display: flex !important;
          flex-direction: column !important;
          align-items: flex-start !important;
          justify-content: center !important;
          text-align: left !important;
          flex: 1 1 auto !important;
          min-width: 0 !important;
          max-width: 170px !important;
          overflow: hidden !important;
          white-space: nowrap !important;
          opacity: 1 !important;
          transform: translateX(0) !important;
          cursor: default !important;
          user-select: none !important;
          -webkit-user-select: none !important;
          pointer-events: none !important;
          transition: opacity 240ms ease, max-width 380ms cubic-bezier(0.25, 1, 0.5, 1), transform 380ms cubic-bezier(0.25, 1, 0.5, 1) !important;
        }

        .navbar-vertical .brand-container h1 a,
        .navbar-vertical .brand-container .navbar-brand a {
          display: flex !important;
          flex-direction: column !important;
          align-items: flex-start !important;
          justify-content: center !important;
          text-align: left !important;
          width: 100% !important;
          cursor: default !important;
          user-select: none !important;
          -webkit-user-select: none !important;
          pointer-events: none !important;
          text-decoration: none !important;
          color: inherit !important;
        }

        .navbar-vertical .brand-container a#sidebar-toggle {
          pointer-events: auto !important;
        }

        body.sidebar-collapsed .navbar-vertical .brand-container h1,
        body.sidebar-collapsed .navbar-vertical .brand-container .navbar-brand {
          opacity: 0 !important;
          max-width: 0 !important;
          min-width: 0 !important;
          margin: 0 !important;
          padding: 0 !important;
          pointer-events: none !important;
          overflow: hidden !important;
          transform: translateX(-10px) !important;
        }

        /* 7. Active State */
        body.sidebar-collapsed .navbar-vertical .nav-link {
          border: none !important;
        }
        body.sidebar-collapsed .navbar-vertical .nav-link.active {
          background: transparent !important;
          box-shadow: none !important;
        }
        body.sidebar-collapsed .navbar-vertical .nav-link.active::after {
          display: none !important;
        }

        /* 8. Sticky Footer */
        .navbar-vertical .mt-auto {
          position: sticky !important;
          bottom: 0 !important;
          background-color: var(--tblr-bg-surface, #182433) !important;
          z-index: 10 !important;
          margin-bottom: 0 !important;
          padding: 0 !important;
        }
        .navbar-vertical .mt-auto > div {
          padding-top: 1rem !important;
          padding-bottom: 1rem !important;
          gap: 14px !important;
          transition: gap 380ms cubic-bezier(0.25, 1, 0.5, 1) !important;
        }
        body.sidebar-collapsed .navbar-vertical .mt-auto > div {
          gap: 0 !important;
        }

        /* Toggle Button Size & Positioning */
        .navbar-vertical .brand-container a#sidebar-toggle {
          width: 32px !important;
          height: 32px !important;
          min-width: 32px !important;
          min-height: 32px !important;
          margin: 0 !important;
          padding: 0 !important;
          display: inline-flex !important;
          align-items: center !important;
          justify-content: center !important;
          border: none !important;
          border-radius: 6px !important;
          box-shadow: none !important;
          outline: none !important;
          background: transparent !important;
          color: var(--tblr-body-color) !important;
          opacity: 0.75 !important;
          cursor: pointer;
          flex-shrink: 0 !important;
          transition: opacity 200ms ease, background-color 200ms ease, transform 150ms ease !important;
        }


        .navbar-vertical .brand-container a#sidebar-toggle:hover {
          opacity: 1 !important;
          background-color: rgba(0, 0, 0, 0.06) !important;
        }
        [data-bs-theme="dark"] .navbar-vertical .brand-container a#sidebar-toggle:hover {
          background-color: rgba(255, 255, 255, 0.08) !important;
        }

        .navbar-vertical .brand-container a#sidebar-toggle:active {
          transform: scale(0.92) !important;
        }

      }
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

    // Apply sidebar state immediately to prevent FOUC (Flash of Unstyled Content)
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
</script>

<div class="page">
    <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none sticky-top" style="z-index: 1030; background: var(--tblr-bg-surface); border-bottom: 1px solid var(--tblr-border-color);">
        <div class="container-xl d-flex align-items-center">
            <div class="collapse navbar-collapse" id="navbar-menu">
                <span class="navbar-text">
                    <b><?php echo $header_isp_name; ?></b>
                </span>
            </div>
            <!-- Navbar brand can go here if needed, but we keep the right side menu -->
            <div class="navbar-nav flex-row order-md-last ms-auto">
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
                        <a href="<?= BASE_URL ?>/logout" class="dropdown-item">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

<script>
function setTheme(theme) {
    localStorage.setItem(themeStorageKey, theme);
    document.body.setAttribute("data-bs-theme", theme);
}

// Sidebar toggle logic
document.addEventListener("DOMContentLoaded", function() {
    const sidebarToggleBtn = document.getElementById('sidebar-toggle');
    const body = document.body;

    function handleToggle(e) {
        if (e) e.preventDefault();
        body.classList.toggle('sidebar-collapsed');
        const isCollapsed = body.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);

        document.dispatchEvent(new CustomEvent('sidebar-toggle', { detail: { collapsed: isCollapsed } }));

        // Wait until the 380ms CSS transition finishes before firing resize and map invalidation
        setTimeout(function() {
            window.dispatchEvent(new Event('resize'));
            if (window.overviewMap) window.overviewMap.invalidateSize();
            if (window.tmap) window.tmap.invalidateSize();
            if (window.hsMap) window.hsMap.invalidateSize();
        }, 400);
    }

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', handleToggle);
    }
});
</script>