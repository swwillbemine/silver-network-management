<?php
require_once __DIR__ . '/config/bootstrap.php';
$token = generateCsrfToken();
?>
<!doctype html>
<html lang="id">
<head>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/css/tabler.min.css" rel="stylesheet"/>
    <style>
      @import url('https://rsms.me/inter/inter.css');
      :root { --tblr-font-sans-serif: 'Inter Var', -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; }
      body { font-feature-settings: "cv03", "cv04", "cv11"; }
    </style>
    <title>Login - <?php echo $full_title; ?></title>
    </head>
<body class=" d-flex flex-column">
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4 ">
            <a href="." class="navbar-brand navbar-brand-autodark">
                <!-- <div style="font-size: 1.5rem; font-weight: 800; color: #6f42c1;"><?php echo $isp_name; ?></div> -->
                <div style="font-size: 1rem; color: #6c757d;"><?php echo $app_name; ?></div>
            </a>
        </div>
        
        <div class="card card-md">
            <div class="card-body">
                <h2 class="h2 text-center mb-4">Login ke Dashboard</br><div style="color: #6f42c1; "><?php echo $isp_name; ?></div></h2>

                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-danger" role="alert">
                        Username atau Password salah!
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['timeout'])): ?>
                    <div class="alert alert-warning" role="alert">
                        Sesi habis, silakan login kembali.
                    </div>
                <?php endif; ?>

                <?php if(($_GET['error'] ?? '') === 'session_taken'): ?>
                    <div class="alert alert-danger" role="alert">
                        <strong>Sesi dihentikan.</strong> Akun ini baru saja login dari perangkat lain.
                    </div>
                <?php endif; ?>

                <form action="auth/login_process.php" method="POST" autocomplete="off" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">

                    <div class="mb-3">
                        <label class="form-label">Username atau Email</label>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">
                            Password
                        </label>
                        <div class="input-group input-group-flat">
                            <input type="password" name="password" class="form-control"  placeholder="Password" required>
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
<?php require_once __DIR__ . '/layout/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/js/tabler.min.js"></script>
</body>
</html>