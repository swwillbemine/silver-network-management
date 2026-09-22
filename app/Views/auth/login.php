<?php
$token = $token ?? \App\Helpers\Security::generateCsrfToken();
$isp_name = $isp_name ?? ($GLOBALS['isp_name'] ?? 'SilverNet');
$app_name = $app_name ?? ($GLOBALS['app_name'] ?? 'Silver Network Management');
$full_title = $full_title ?? ($GLOBALS['full_title'] ?? 'Silver Network Management');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/css/tabler.min.css" rel="stylesheet"/>
    <style>
      @import url('https://rsms.me/inter/inter.css');
      :root { --tblr-font-sans-serif: 'Inter Var', -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; }
      body { font-feature-settings: "cv03", "cv04", "cv11"; }
    </style>
    <title>Login - <?php echo htmlspecialchars($full_title); ?></title>
</head>
<body class="d-flex flex-column">
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="<?= BASE_URL ?>/" class="navbar-brand navbar-brand-autodark">
                <div style="font-size: 1rem; color: #6c757d;"><?php echo htmlspecialchars($app_name); ?></div>
            </a>
        </div>
        
        <div class="card card-md">
            <div class="card-body">
                <h2 class="h2 text-center mb-4">Login ke Dashboard<br><div style="color: #6f42c1;"><?php echo htmlspecialchars($isp_name); ?></div></h2>

                <?php if(isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
                    <div class="alert alert-danger" role="alert">
                        Sesi login kedaluwarsa. Silakan muat ulang halaman dan coba lagi.
                    </div>
                <?php elseif(isset($_GET['error']) && $_GET['error'] === 'session_taken'): ?>
                    <div class="alert alert-danger" role="alert">
                        <strong>Sesi dihentikan.</strong> Akun ini baru saja login dari perangkat lain.
                    </div>
                <?php elseif(isset($_GET['error'])): ?>
                    <div class="alert alert-danger" role="alert">
                        Username atau Password salah!
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['timeout'])): ?>
                    <div class="alert alert-warning" role="alert">
                        Sesi habis, silakan login kembali.
                    </div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>/login" method="POST" autocomplete="off" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="mb-3">
                        <label class="form-label">Username atau Email</label>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Password</label>
                        <div class="input-group input-group-flat">
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                        </div>
                    </div>
                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary w-100">Masuk Sistem</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center text-muted mt-3">
            Belum punya akses? Hubungi Administrator.
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/js/tabler.min.js"></script>
</body>
</html>

