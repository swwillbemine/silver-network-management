<?php
// layout/header.php
if (!defined('APP_LOADED')) require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
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
      :root {
        --tblr-border-radius: 0.75rem;
      }
      .card {
        border-radius: var(--tblr-border-radius);
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
      }
      .card-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
      }
      [data-bs-theme="dark"] .card {
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
        .navbar-vertical {
          overflow-x: hidden;
          transition: width 0.3s ease !important;
        }

        /* ========================================================= */
        /* PERFECT SIDEBAR ALIGNMENT & ANIMATION FIX                 */
        /* ========================================================= */

        /* 1. Global Dimensions & Transitions */
        .navbar-vertical {
          width: 224px !important;
          transition: width 280ms cubic-bezier(0.4, 0, 0.2, 1) !important;
          overflow: hidden !important;
          display: flex !important;
          flex-direction: column !important;
        }
        body.sidebar-collapsed .navbar-vertical {
          width: 72px !important;
        }
        .page-wrapper,
        .page > header.navbar {
          margin-left: 224px !important;
          width: calc(100% - 224px) !important;
          max-width: calc(100% - 224px) !important;
          min-width: 0 !important;
          transition: margin-left 280ms cubic-bezier(0.4, 0, 0.2, 1), width 280ms cubic-bezier(0.4, 0, 0.2, 1), max-width 280ms cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        body.sidebar-collapsed .page-wrapper,
        body.sidebar-collapsed .page > header.navbar {
          margin-left: 72px !important;
          width: calc(100% - 72px) !important;
          max-width: calc(100% - 72px) !important;
        }

        /* 2. Remove Tabler Default Padding so our Flex rules dominate */
        .navbar-vertical .container-fluid {
          padding: 0 !important;
        }

        /* 3. Text and Sub-elements Transition */
        .navbar-vertical .nav-link-title,
        .navbar-vertical .brand-container h1,
        .navbar-vertical li > span,
        .navbar-vertical .mt-auto .user-info,
        .navbar-vertical .mt-auto .avatar {
          transition: opacity 200ms ease, max-width 280ms cubic-bezier(0.4, 0, 0.2, 1), margin 280ms ease, transform 280ms cubic-bezier(0.4, 0, 0.2, 1) !important;
          opacity: 1;
          max-width: 250px;
          white-space: nowrap;
          overflow: hidden;
          transform: translateX(0);
        }

        /* When Collapsed: Shrink Text and Avatars completely */
        body.sidebar-collapsed .navbar-vertical .nav-link-title,
        body.sidebar-collapsed .navbar-vertical .brand-container h1,
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
          transition: margin 280ms cubic-bezier(0.4, 0, 0.2, 1), max-height 280ms cubic-bezier(0.4, 0, 0.2, 1) !important;
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

        .navbar-vertical .brand-container {
          padding-left: 24px !important;
          padding-right: 24px !important;
          display: flex !important;
          align-items: center !important;
          justify-content: flex-start !important;
          flex-wrap: nowrap !important;
          flex-shrink: 0 !important;
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
          transition: margin-right 280ms cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        body.sidebar-collapsed .navbar-vertical .nav-link-icon {
          margin-right: 0 !important;
        }

        /* 6. Header Brand & Hamburger Fix */
        .navbar-vertical .brand-container {
          padding-top: 14px !important;
          padding-bottom: 14px !important;
        }
        
        .navbar-vertical .brand-title {
          font-weight: 700 !important;
          font-size: 15px !important;
          color: inherit !important;
          letter-spacing: -0.01em;
          line-height: 1.1;
          margin-bottom: 2px;
        }
        
        .navbar-vertical .brand-subtitle {
          font-size: 8.5px !important; 
          font-weight: 500 !important;
          color: inherit !important;
          opacity: 0.6 !important;
          letter-spacing: 0.05em !important;
          text-transform: uppercase;
          line-height: 1;
        }

        .navbar-vertical .brand-container h1 {
          margin-left: 0 !important; 
          display: flex;
          align-items: center;
        }

        /* Toggle Button Size & Positioning */
        
        /* 
           MATHEMATICAL ALIGNMENT FOR COLLAPSED BUTTON:
           - Collapsed width: 72px
           - Left padding: 24px
           - Nav Icon Center: 24px + (24px/2) = 36px
           - To center a 32px button at 36px, its left edge must be at 20px.
           - We have 24px left padding, so we apply -4px margin-left to pull it to 20px!
        */
        

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
          transition: gap 280ms cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        body.sidebar-collapsed .navbar-vertical .mt-auto > div {
          gap: 0 !important;
        }
        /* Toggle Button Size & Positioning */
        .navbar-vertical .brand-container a#sidebar-toggle {
          width: 32px !important;
          height: 32px !important;
          margin: 0 !important;
          padding: 0 !important;
          display: flex !important;
          align-items: center !important;
          justify-content: center !important;
          margin-left: auto !important;
          border: none !important;
          box-shadow: none !important;
          outline: none !important;
          background: transparent !important;
          color: var(--tblr-body-color) !important;
          opacity: 0.75 !important;
          transition: margin-left 280ms cubic-bezier(0.4, 0, 0.2, 1), transform 280ms cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        body.sidebar-collapsed .navbar-vertical .brand-container a#sidebar-toggle {
          margin-left: -4px !important;
        }

        .navbar-vertical .brand-container a#sidebar-toggle:hover,
        .navbar-vertical .brand-container a#sidebar-toggle:focus,
        .navbar-vertical .brand-container a#sidebar-toggle:active {
          background: transparent !important;
          background-color: transparent !important;
          border: none !important;
          box-shadow: none !important;
          outline: none !important;
          color: var(--tblr-body-color) !important;
          opacity: 0.75 !important;
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
        <div class="container-xl">
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

// Sidebar toggle logic
document.addEventListener("DOMContentLoaded", function() {
    const toggleBtn = document.getElementById('sidebar-toggle');
    const body = document.body;

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
            // Trigger window resize so apexcharts can adjust
            setTimeout(() => window.dispatchEvent(new Event('resize')), 200);
        });
    }
});
</script>