<?php
/**
 * SilverNet Management — Web Installer
 * Tempatkan di: /install/index.php
 * Akses via browser: http://localhost/silver-network-management/install/
 *
 * HAPUS folder /install/ setelah instalasi selesai!
 */

define('INSTALLER_VERSION', '1.0.0');
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config/database.php');

session_start();

// ── HELPERS ──────────────────────────────────────────────────────────────────

function check_requirements(): array {
    $checks = [];

    $checks[] = [
        'label' => 'PHP >= 7.4',
        'ok'    => version_compare(PHP_VERSION, '7.4.0', '>='),
        'info'  => 'PHP ' . PHP_VERSION,
    ];
    $checks[] = [
        'label' => 'Ekstensi PDO',
        'ok'    => extension_loaded('pdo'),
        'info'  => extension_loaded('pdo') ? 'Tersedia' : 'Tidak tersedia',
    ];
    $checks[] = [
        'label' => 'Ekstensi PDO MySQL',
        'ok'    => extension_loaded('pdo_mysql'),
        'info'  => extension_loaded('pdo_mysql') ? 'Tersedia' : 'Tidak tersedia',
    ];
    $checks[] = [
        'label' => 'Ekstensi OpenSSL',
        'ok'    => extension_loaded('openssl'),
        'info'  => extension_loaded('openssl') ? 'Tersedia' : 'Tidak tersedia',
    ];
    $checks[] = [
        'label' => 'Ekstensi mbstring',
        'ok'    => extension_loaded('mbstring'),
        'info'  => extension_loaded('mbstring') ? 'Tersedia' : 'Tidak tersedia',
    ];
    $checks[] = [
        'label' => 'Folder config/ dapat ditulis',
        'ok'    => is_writable(ROOT_PATH . '/config'),
        'info'  => is_writable(ROOT_PATH . '/config') ? 'Writable' : 'Tidak writable — chmod 755',
    ];

    return $checks;
}

function all_ok(array $checks): bool {
    foreach ($checks as $c) { if (!$c['ok']) return false; }
    return true;
}

function try_connect(string $host, string $db, string $user, string $pass, int $port): array {
    try {
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        return ['ok' => true, 'pdo' => $pdo];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function get_schema_sql(): string {
    // SQL schema lengkap — diambil dari struktur database
    return <<<'SQL'
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `permissions` set('read_customers','read_live') NOT NULL DEFAULT 'read_customers,read_live',
  `last_used_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_api_key` (`api_key`),
  KEY `fk_apikey_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `billings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `period` varchar(7) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('unpaid','paid','cancelled') DEFAULT 'unpaid',
  `generated_at` datetime DEFAULT current_timestamp(),
  `due_date` date NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `discount_amount` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_bill_cust` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `start_node_id` int(11) NOT NULL,
  `end_node_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `length_estimated` int(11) DEFAULT 0,
  `core_color` varchar(50) DEFAULT NULL,
  `loss_db` decimal(5,2) DEFAULT NULL,
  `wireless_frequency` varchar(10) DEFAULT NULL,
  `wireless_ssid` varchar(100) DEFAULT NULL,
  `wireless_password` varchar(100) DEFAULT NULL,
  `wireless_ip_radio_ap` varchar(50) DEFAULT NULL,
  `wireless_ip_radio_station` varchar(50) DEFAULT NULL,
  `status` enum('connected','broken','maintenance') DEFAULT 'connected',
  PRIMARY KEY (`id`),
  KEY `fk_conn_start` (`start_node_id`),
  KEY `fk_conn_end` (`end_node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `node_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `identity_number` varchar(50) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `pppoe_username` varchar(100) NOT NULL,
  `pppoe_password` varchar(100) NOT NULL,
  `installation_date` date NOT NULL,
  `billing_cycle_date` int(2) DEFAULT 20,
  `billing_due_date` int(2) DEFAULT 30,
  `isolation_date` int(2) DEFAULT 1,
  `status` enum('active','isolated','terminated','free') DEFAULT 'active',
  `router_brand` varchar(50) DEFAULT NULL,
  `router_type` varchar(50) DEFAULT NULL,
  `router_mac` varchar(17) DEFAULT NULL,
  `wifi_ssid` varchar(100) DEFAULT NULL,
  `wifi_password` varchar(100) DEFAULT NULL,
  `remote_mgmt_port` int(5) DEFAULT 1866,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `discount` int(11) NOT NULL DEFAULT 0,
  `customer_number` varchar(20) NOT NULL DEFAULT '',
  `billing_type` varchar(20) NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`id`),
  UNIQUE KEY `pppoe_username` (`pppoe_username`),
  UNIQUE KEY `idx_cust_number` (`customer_number`),
  KEY `fk_cust_node` (`node_id`),
  KEY `fk_cust_package` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `hotspots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `node_id` int(11) NOT NULL,
  `mikrotik_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `ssid` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_hs_node` (`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `mikrotiks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pop_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `host` varchar(50) NOT NULL,
  `port` int(11) DEFAULT 8728,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `api_ssl` tinyint(1) DEFAULT 0,
  `status` enum('online','offline') DEFAULT 'offline',
  `last_seen` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_mikrotik_pop` (`pop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `nodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `owner_name` varchar(100) DEFAULT NULL,
  `owner_contact` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `node_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `node_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `category` enum('installation','maintenance','environment','other') DEFAULT 'installation',
  `description` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_photos_node` (`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mikrotik_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `tx_max_limit` bigint(18) NOT NULL,
  `rx_max_limit` bigint(18) NOT NULL,
  `tx_burst_limit` bigint(18) DEFAULT 0,
  `rx_burst_limit` bigint(18) DEFAULT 0,
  `tx_burst_threshold` bigint(18) DEFAULT 0,
  `rx_burst_threshold` bigint(18) DEFAULT 0,
  `tx_burst_time` int(5) DEFAULT 0,
  `rx_burst_time` int(5) DEFAULT 0,
  `tx_priority` int(2) DEFAULT 8,
  `rx_priority` int(2) DEFAULT 8,
  `queue_type` varchar(100) DEFAULT 'default',
  `parent_queue` varchar(100) DEFAULT NULL,
  `queue_insert_before` varchar(100) DEFAULT NULL,
  `local_address` varchar(100) DEFAULT NULL,
  `remote_pool` varchar(100) DEFAULT NULL,
  `mikrotik_profile_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` varchar(255) DEFAULT NULL,
  `dns_server1` varchar(45) DEFAULT NULL,
  `dns_server2` varchar(45) DEFAULT NULL,
  `only_one` enum('default','yes','no') NOT NULL DEFAULT 'default',
  PRIMARY KEY (`id`),
  KEY `fk_package_mikrotik` (`mikrotik_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `billing_id` int(11) NOT NULL,
  `processed_by_user_id` int(11) DEFAULT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `method` enum('cash','transfer','qris','va') DEFAULT 'cash',
  `proof_file` varchar(255) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pay_bill` (`billing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `pops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `node_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `backup_power` enum('none','ups','genset','solar') DEFAULT 'none',
  `battery_capacity` varchar(50) DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pop_node` (`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('superadmin','admin','teknisi','kasir') DEFAULT 'admin',
  `telegram_id` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `session_token` varchar(64) DEFAULT NULL,
  `session_started_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `api_keys`
  ADD CONSTRAINT `fk_apikey_user` FOREIGN KEY IF NOT EXISTS (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `billings`
  ADD CONSTRAINT `fk_bill_cust` FOREIGN KEY IF NOT EXISTS (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;
ALTER TABLE `connections`
  ADD CONSTRAINT `fk_conn_end` FOREIGN KEY IF NOT EXISTS (`end_node_id`) REFERENCES `nodes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conn_start` FOREIGN KEY IF NOT EXISTS (`start_node_id`) REFERENCES `nodes` (`id`) ON DELETE CASCADE;
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_cust_node` FOREIGN KEY IF NOT EXISTS (`node_id`) REFERENCES `nodes` (`id`),
  ADD CONSTRAINT `fk_cust_package` FOREIGN KEY IF NOT EXISTS (`package_id`) REFERENCES `packages` (`id`);
ALTER TABLE `hotspots`
  ADD CONSTRAINT `fk_hs_node` FOREIGN KEY IF NOT EXISTS (`node_id`) REFERENCES `nodes` (`id`) ON DELETE CASCADE;
ALTER TABLE `mikrotiks`
  ADD CONSTRAINT `fk_mikrotik_pop` FOREIGN KEY IF NOT EXISTS (`pop_id`) REFERENCES `pops` (`id`);
ALTER TABLE `node_photos`
  ADD CONSTRAINT `fk_photos_node` FOREIGN KEY IF NOT EXISTS (`node_id`) REFERENCES `nodes` (`id`) ON DELETE CASCADE;
ALTER TABLE `packages`
  ADD CONSTRAINT `fk_package_mikrotik` FOREIGN KEY IF NOT EXISTS (`mikrotik_id`) REFERENCES `mikrotiks` (`id`) ON DELETE CASCADE;
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_pay_bill` FOREIGN KEY IF NOT EXISTS (`billing_id`) REFERENCES `billings` (`id`);
ALTER TABLE `pops`
  ADD CONSTRAINT `fk_pop_node` FOREIGN KEY IF NOT EXISTS (`node_id`) REFERENCES `nodes` (`id`) ON DELETE CASCADE;
SQL;
}

function get_default_settings(): array {
    return [
        'isp_name'            => 'SilverNet',
        'address'             => '',
        'admin_phone'         => '',
        'isp_logo'            => '',
        'payment_methods'     => '[]',
        'billing_due_days'    => '10',
        'billing_isolation_days' => '5',
        'timezone'            => 'Asia/Jakarta',
        'proxy_secret'        => bin2hex(random_bytes(16)),
    ];
}

function write_database_config(string $host, string $db, string $user, string $pass, int $port): bool {
    $pass_escaped = addslashes($pass);
    $content = <<<PHP
<?php
// config/database.php
// Dibuat otomatis oleh installer SilverNet pada: {$_SERVER['REQUEST_TIME']}

\$host    = '$host';
\$db      = '$db';
\$user    = '$user';
\$pass    = '$pass_escaped';
\$port    = $port;
\$charset = 'utf8mb4';

\$dsn = "mysql:host=\$host;port=\$port;dbname=\$db;charset=\$charset";
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    \$pdo = new PDO(\$dsn, \$user, \$pass, \$options);
} catch (\\PDOException \$e) {
    throw new \\PDOException(\$e->getMessage(), (int)\$e->getCode());
}
PHP;
    // Replace literal timestamp
    $content = str_replace('{' . '$_SERVER[\'REQUEST_TIME\']' . '}', date('Y-m-d H:i:s'), $content);
    return file_put_contents(CONFIG_PATH, $content) !== false;
}

// ── STEP HANDLING ─────────────────────────────────────────────────────────────
$step   = (int)($_GET['step'] ?? 1);
$errors = [];
$info   = [];

// Prevent going past step 5 if already installed
if (file_exists(ROOT_PATH . '/install/.installed')) {
    $step = 5;
}

// ── POST: Step 2 — Test DB connection ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? 'silvernet-management');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = $_POST['db_pass'] ?? '';
    $db_port = (int)($_POST['db_port'] ?? 3306);

    $result = try_connect($db_host, $db_name, $db_user, $db_pass, $db_port);
    if ($result['ok']) {
        $_SESSION['install_db'] = compact('db_host','db_name','db_user','db_pass','db_port');
        header('Location: ?step=3'); exit;
    } else {
        $errors[] = 'Koneksi gagal: ' . htmlspecialchars($result['error']);
    }
}

// ── POST: Step 3 — Create DB & tables ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    $cfg = $_SESSION['install_db'] ?? null;
    if (!$cfg) { header('Location: ?step=2'); exit; }

    $r = try_connect($cfg['db_host'], '', $cfg['db_user'], $cfg['db_pass'], $cfg['db_port']);
    if (!$r['ok']) { $errors[] = 'Koneksi gagal: ' . $r['error']; goto render; }

    $pdo = $r['pdo'];
    $create_new = ($_POST['db_action'] ?? '') === 'create';

    try {
        if ($create_new) {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $info[] = "Database `{$cfg['db_name']}` berhasil dibuat.";
        }
        $pdo->exec("USE `{$cfg['db_name']}`");

        // Run schema — split by semicolon but only on statement boundaries
        $statements = array_filter(
            array_map('trim', explode(';', get_schema_sql())),
            fn($s) => strlen($s) > 5
        );
        $table_count = 0;
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
            if (stripos($stmt, 'CREATE TABLE') !== false) $table_count++;
        }
        $info[] = "$table_count tabel berhasil dibuat.";

        // Insert default settings
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        foreach (get_default_settings() as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        $info[] = 'Pengaturan default berhasil disimpan.';

        $_SESSION['install_db_done'] = true;
        header('Location: ?step=4'); exit;

    } catch (PDOException $e) {
        $errors[] = 'Error SQL: ' . htmlspecialchars($e->getMessage());
    }
}

// ── POST: Step 4 — Create admin user & write config ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 4) {
    $cfg = $_SESSION['install_db'] ?? null;
    if (!$cfg) { header('Location: ?step=2'); exit; }

    $admin_name = trim($_POST['admin_name'] ?? '');
    $admin_user = trim($_POST['admin_username'] ?? '');
    $admin_pass = $_POST['admin_password'] ?? '';
    $admin_conf = $_POST['admin_confirm'] ?? '';
    $isp_name   = trim($_POST['isp_name'] ?? 'SilverNet');

    if (!$admin_name)                     $errors[] = 'Nama admin wajib diisi.';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $admin_user)) $errors[] = 'Username hanya boleh huruf, angka, dan underscore.';
    if (strlen($admin_pass) < 8)           $errors[] = 'Password minimal 8 karakter.';
    if ($admin_pass !== $admin_conf)       $errors[] = 'Konfirmasi password tidak cocok.';

    if (!$errors) {
        try {
            $r = try_connect($cfg['db_host'], $cfg['db_name'], $cfg['db_user'], $cfg['db_pass'], $cfg['db_port']);
            if (!$r['ok']) throw new Exception($r['error']);
            $pdo = $r['pdo'];

            // Create superadmin
            $pdo->prepare("INSERT INTO users (name, username, password, role, is_active) VALUES (?,?,?,'superadmin',1)")
                ->execute([$admin_name, $admin_user, password_hash($admin_pass, PASSWORD_DEFAULT)]);

            // Update ISP name in settings
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('isp_name',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
                ->execute([$isp_name]);

            // Write database.php config
            if (!write_database_config($cfg['db_host'], $cfg['db_name'], $cfg['db_user'], $cfg['db_pass'], $cfg['db_port'])) {
                $errors[] = 'Gagal menulis config/database.php — pastikan folder config/ dapat ditulis (chmod 755).';
            } else {
                // Mark as installed
                file_put_contents(ROOT_PATH . '/install/.installed', date('Y-m-d H:i:s'));
                $_SESSION['install_done'] = true;
                header('Location: ?step=5'); exit;
            }
        } catch (Exception $e) {
            $errors[] = 'Error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

render:
$checks = check_requirements();
$req_ok = all_ok($checks);
$db_cfg = $_SESSION['install_db'] ?? ['db_host'=>'localhost','db_name'=>'silvernet-management','db_user'=>'root','db_pass'=>'','db_port'=>3306];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Installer — SilverNet Management</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --brand:        #206bc4;
      --brand-dk:     #1a56a0;
      --brand-lt:     #e8f0fb;
      --success:      #2fb344;
      --danger:       #d63939;
      --warning:      #f76707;
      --muted:        #626976;
      --border:       #dce1e7;
      --bg:           #f0f4f8;
      --card:         #ffffff;
      --text:         #1a2332;
      --sidebar-w:    280px;
      --radius:       12px;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      font-size: 14px;
      line-height: 1.6;
    }

    /* ── SIDEBAR ── */
    .sidebar {
      width: var(--sidebar-w);
      min-height: 100vh;
      background: linear-gradient(160deg, #0f1f3d 0%, #1a3a6e 100%);
      padding: 40px 24px;
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      position: sticky;
      top: 0;
      height: 100vh;
    }

    .sidebar-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 40px;
    }

    .sidebar-logo-icon {
      width: 40px; height: 40px;
      background: var(--brand);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .sidebar-logo-icon svg { color: #fff; }

    .sidebar-logo-text {
      font-size: 16px;
      font-weight: 800;
      color: #fff;
      line-height: 1.2;
    }

    .sidebar-logo-text span {
      display: block;
      font-size: 11px;
      font-weight: 500;
      color: rgba(255,255,255,.5);
      letter-spacing: .05em;
    }

    .sidebar-steps { list-style: none; flex: 1; }

    .step-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 10px 0;
      position: relative;
    }

    .step-item:not(:last-child)::after {
      content: '';
      position: absolute;
      left: 15px;
      top: 34px;
      width: 2px;
      height: calc(100% - 14px);
      background: rgba(255,255,255,.1);
    }

    .step-item.done::after { background: rgba(47,179,68,.4); }

    .step-num {
      width: 30px; height: 30px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700;
      flex-shrink: 0;
      border: 2px solid rgba(255,255,255,.15);
      color: rgba(255,255,255,.4);
      background: rgba(255,255,255,.05);
      transition: all .2s;
    }

    .step-item.done   .step-num { background: var(--success); border-color: var(--success); color: #fff; }
    .step-item.active .step-num { background: var(--brand); border-color: var(--brand); color: #fff; box-shadow: 0 0 0 4px rgba(32,107,196,.3); }

    .step-label {
      padding-top: 4px;
    }
    .step-label strong {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: rgba(255,255,255,.4);
    }
    .step-label small {
      font-size: 11px;
      color: rgba(255,255,255,.25);
    }
    .step-item.active .step-label strong { color: #fff; }
    .step-item.active .step-label small  { color: rgba(255,255,255,.6); }
    .step-item.done   .step-label strong { color: rgba(255,255,255,.7); }

    .sidebar-footer {
      font-size: 11px;
      color: rgba(255,255,255,.25);
      margin-top: 24px;
    }

    /* ── MAIN CONTENT ── */
    .main {
      flex: 1;
      padding: 40px;
      max-width: 700px;
    }

    .page-title {
      font-size: 26px;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 4px;
    }

    .page-subtitle {
      color: var(--muted);
      font-size: 14px;
      margin-bottom: 28px;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 28px;
      margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,.04);
    }

    .card-title {
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* ── FORMS ── */
    .form-group { margin-bottom: 16px; }

    label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 5px;
      color: var(--text);
    }

    label .req { color: var(--danger); }
    label .hint { font-weight: 400; color: var(--muted); font-size: 11px; }

    input[type=text], input[type=password], input[type=number], input[type=email], select {
      width: 100%;
      padding: 9px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      color: var(--text);
      background: #fff;
      transition: border-color .15s, box-shadow .15s;
      outline: none;
    }

    input:focus, select:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(32,107,196,.12);
    }

    input.mono, .mono { font-family: 'JetBrains Mono', monospace; }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .form-row-3 {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr;
      gap: 12px;
    }

    .form-hint {
      font-size: 11px;
      color: var(--muted);
      margin-top: 4px;
    }

    /* ── BUTTONS ── */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 20px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      border: none;
      text-decoration: none;
      transition: all .15s;
    }

    .btn-primary { background: var(--brand); color: #fff; }
    .btn-primary:hover { background: var(--brand-dk); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(32,107,196,.3); }

    .btn-outline { background: transparent; color: var(--brand); border: 1.5px solid var(--brand); }
    .btn-outline:hover { background: var(--brand-lt); }

    .btn-success { background: var(--success); color: #fff; }
    .btn-success:hover { background: #27a03c; }

    .btn-lg { padding: 12px 28px; font-size: 15px; }

    .btn-group { display: flex; gap: 10px; margin-top: 24px; }

    /* ── ALERTS ── */
    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 16px;
      font-size: 13px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }

    .alert-danger  { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
    .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
    .alert-info    { background: #eff6ff; border: 1px solid #93c5fd; color: #1e40af; }
    .alert-warning { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }

    /* ── CHECKS ── */
    .check-list { list-style: none; }
    .check-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid var(--border);
      font-size: 13px;
    }
    .check-item:last-child { border-bottom: none; }

    .check-left { display: flex; align-items: center; gap: 10px; }

    .badge-ok   { background: #dcfce7; color: #166534; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600; }
    .badge-fail { background: #fee2e2; color: #991b1b; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600; }
    .badge-info { background: var(--brand-lt); color: var(--brand); font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600; font-family: 'JetBrains Mono', monospace; }

    /* ── RADIO CARDS ── */
    .radio-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
    .radio-card input[type=radio] { position: absolute; opacity: 0; pointer-events: none; }
    .radio-card label {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 14px 16px;
      border: 2px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      transition: all .15s;
      font-weight: 400;
      margin-bottom: 0;
    }
    .radio-card input:checked + label {
      border-color: var(--brand);
      background: var(--brand-lt);
    }
    .radio-card label strong { display: block; font-weight: 600; font-size: 13px; color: var(--text); }
    .radio-card label small  { font-size: 11px; color: var(--muted); }
    .radio-icon { width: 34px; height: 34px; border-radius: 8px; background: var(--bg); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

    /* ── SUCCESS ── */
    .success-hero {
      text-align: center;
      padding: 20px 0 32px;
    }
    .success-circle {
      width: 80px; height: 80px;
      background: linear-gradient(135deg, #2fb344 0%, #27a03c 100%);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 20px;
      box-shadow: 0 8px 24px rgba(47,179,68,.35);
      animation: popIn .4s cubic-bezier(.34,1.56,.64,1) forwards;
    }
    @keyframes popIn {
      from { transform: scale(.5); opacity: 0; }
      to   { transform: scale(1);  opacity: 1; }
    }
    .success-circle svg { color: #fff; }
    .success-title { font-size: 24px; font-weight: 800; margin-bottom: 8px; }
    .success-sub   { color: var(--muted); max-width: 400px; margin: 0 auto 24px; }

    .warning-box {
      background: #fffbeb;
      border: 2px dashed var(--warning);
      border-radius: 10px;
      padding: 16px 20px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      font-size: 13px;
      color: #92400e;
    }
    .warning-box strong { display: block; margin-bottom: 4px; font-size: 14px; }

    /* ── PASSWORD STRENGTH ── */
    .strength-bar { height: 4px; border-radius: 2px; background: var(--border); margin-top: 6px; overflow: hidden; }
    .strength-fill { height: 100%; border-radius: 2px; transition: width .3s, background .3s; }

    /* ── DIVIDER ── */
    .divider { border: none; border-top: 1px solid var(--border); margin: 20px 0; }

    @media (max-width: 700px) {
      .sidebar { display: none; }
      .main { padding: 24px 16px; max-width: 100%; }
      .form-row, .form-row-3, .radio-cards { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- ── SIDEBAR ─────────────────────────────────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
        <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
      </svg>
    </div>
    <div class="sidebar-logo-text">
      SilverNet
      <span>Management Installer</span>
    </div>
  </div>

  <ul class="sidebar-steps">
    <?php
    $steps_def = [
      1 => ['label' => 'Persyaratan', 'sub' => 'Cek kompatibilitas server'],
      2 => ['label' => 'Database',    'sub' => 'Konfigurasi koneksi MySQL'],
      3 => ['label' => 'Instalasi',   'sub' => 'Buat tabel & skema database'],
      4 => ['label' => 'Akun Admin',  'sub' => 'Buat akun superadmin'],
      5 => ['label' => 'Selesai',     'sub' => 'Instalasi berhasil'],
    ];
    foreach ($steps_def as $n => $s):
      $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
    ?>
    <li class="step-item <?= $cls ?>">
      <div class="step-num">
        <?php if ($n < $step): ?>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" fill="none"><polyline points="20 6 9 17 4 12"/></svg>
        <?php else: echo $n; endif; ?>
      </div>
      <div class="step-label">
        <strong><?= $s['label'] ?></strong>
        <small><?= $s['sub'] ?></small>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>

  <div class="sidebar-footer">
    SilverNet Management v<?= INSTALLER_VERSION ?><br>
    PHP <?= PHP_VERSION ?>
  </div>
</aside>

<!-- ── MAIN ────────────────────────────────────────────────────────────────── -->
<main class="main">

<?php if ($errors): ?>
  <?php foreach ($errors as $e): ?>
  <div class="alert alert-danger">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= $e ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($info): ?>
  <?php foreach ($info as $i): ?>
  <div class="alert alert-success">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" style="flex-shrink:0;margin-top:1px"><polyline points="20 6 9 17 4 12"/></svg>
    <?= htmlspecialchars($i) ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>


<!-- ════════════════════════════════════════════════════════════════════════ -->
<?php if ($step === 1): ?>
<!-- STEP 1: REQUIREMENTS -->

<div class="page-title">Persyaratan Sistem</div>
<div class="page-subtitle">Pastikan semua persyaratan terpenuhi sebelum melanjutkan instalasi.</div>

<div class="card">
  <div class="card-title">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
    Cek Kompatibilitas Server
  </div>
  <ul class="check-list">
    <?php foreach ($checks as $c): ?>
    <li class="check-item">
      <div class="check-left">
        <?php if ($c['ok']): ?>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2.5" stroke="#2fb344" fill="none"><polyline points="20 6 9 17 4 12"/></svg>
        <?php else: ?>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2.5" stroke="#d63939" fill="none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        <?php endif; ?>
        <span><?= htmlspecialchars($c['label']) ?></span>
      </div>
      <span class="<?= $c['ok'] ? 'badge-ok' : 'badge-fail' ?>"><?= htmlspecialchars($c['info']) ?></span>
    </li>
    <?php endforeach; ?>
  </ul>
</div>

<?php if (!$req_ok): ?>
<div class="alert alert-danger">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  Beberapa persyaratan belum terpenuhi. Perbaiki terlebih dahulu sebelum melanjutkan.
</div>
<?php else: ?>
<div class="alert alert-success">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" style="flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>
  Semua persyaratan terpenuhi! Lanjutkan ke konfigurasi database.
</div>

<div class="btn-group">
  <a href="?step=2" class="btn btn-primary btn-lg">
    Lanjut — Konfigurasi Database
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="9 18 15 12 9 6"/></svg>
  </a>
</div>
<?php endif; ?>


<!-- ════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($step === 2): ?>
<!-- STEP 2: DATABASE CONFIG -->

<div class="page-title">Konfigurasi Database</div>
<div class="page-subtitle">Masukkan kredensial MySQL. File <code>config/database.php</code> akan ditulis otomatis.</div>

<form method="POST" action="?step=2">
  <div class="card">
    <div class="card-title">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg>
      Koneksi MySQL
    </div>

    <div class="form-row-3">
      <div class="form-group">
        <label>Host <span class="req">*</span></label>
        <input type="text" name="db_host" value="<?= htmlspecialchars($db_cfg['db_host']) ?>" placeholder="localhost" required>
      </div>
      <div class="form-group">
        <label>Port</label>
        <input type="number" name="db_port" value="<?= htmlspecialchars($db_cfg['db_port']) ?>" placeholder="3306" class="mono">
      </div>
      <div></div>
    </div>

    <div class="form-group">
      <label>Nama Database <span class="req">*</span> <span class="hint">— akan dibuat jika belum ada</span></label>
      <input type="text" name="db_name" value="<?= htmlspecialchars($db_cfg['db_name']) ?>" placeholder="silvernet-management" required class="mono">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Username MySQL <span class="req">*</span></label>
        <input type="text" name="db_user" value="<?= htmlspecialchars($db_cfg['db_user']) ?>" placeholder="root" required class="mono" autocomplete="username">
      </div>
      <div class="form-group">
        <label>Password MySQL <span class="hint">— kosongkan jika tidak ada</span></label>
        <input type="password" name="db_pass" value="" placeholder="(kosong)" class="mono" autocomplete="current-password">
        <div class="form-hint">Untuk XAMPP default biasanya kosong.</div>
      </div>
    </div>
  </div>

  <div class="alert alert-info" style="font-size:12px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    Installer hanya akan <strong>menguji koneksi</strong> di langkah ini. Database dan tabel dibuat di langkah berikutnya.
  </div>

  <div class="btn-group">
    <a href="?step=1" class="btn btn-outline">← Kembali</a>
    <button type="submit" class="btn btn-primary btn-lg">
      Test & Lanjutkan
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
  </div>
</form>


<!-- ════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($step === 3): ?>
<!-- STEP 3: INSTALL DATABASE -->

<div class="page-title">Instalasi Database</div>
<div class="page-subtitle">
  Akan membuat database <code class="mono"><?= htmlspecialchars($db_cfg['db_name']) ?></code>
  dan semua tabel yang diperlukan.
</div>

<form method="POST" action="?step=3">
  <div class="card">
    <div class="card-title">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      Pilih Aksi Database
    </div>

    <div class="radio-cards">
      <div class="radio-card">
        <input type="radio" name="db_action" id="act_create" value="create" checked>
        <label for="act_create">
          <div class="radio-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="var(--brand)" fill="none"><path d="M12 5v14M5 12h14"/></svg>
          </div>
          <div>
            <strong>Buat Database Baru</strong>
            <small>Buat database jika belum ada, lalu buat semua tabel</small>
          </div>
        </label>
      </div>
      <div class="radio-card">
        <input type="radio" name="db_action" id="act_existing" value="existing">
        <label for="act_existing">
          <div class="radio-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="var(--brand)" fill="none"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/></svg>
          </div>
          <div>
            <strong>Database Sudah Ada</strong>
            <small>Hanya buat tabel yang belum ada (aman, tidak menghapus data)</small>
          </div>
        </label>
      </div>
    </div>

    <div class="alert alert-warning" style="font-size:12px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" style="flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Tabel dibuat dengan <code>CREATE TABLE IF NOT EXISTS</code> — data yang sudah ada <strong>tidak akan dihapus</strong>.
    </div>

    <div class="card-title" style="margin-top:8px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
      Tabel yang akan dibuat (13 tabel)
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
      <?php foreach (['api_keys','billings','connections','customers','hotspots','mikrotiks','nodes','node_photos','packages','payments','pops','settings','users'] as $t): ?>
      <span class="badge-info"><?= $t ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="btn-group">
    <a href="?step=2" class="btn btn-outline">← Kembali</a>
    <button type="submit" class="btn btn-primary btn-lg" onclick="this.textContent='Menginstal...';this.disabled=true;this.form.submit();">
      Mulai Instalasi
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
  </div>
</form>


<!-- ════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($step === 4): ?>
<!-- STEP 4: CREATE ADMIN -->

<div class="page-title">Buat Akun Superadmin</div>
<div class="page-subtitle">Akun ini akan memiliki akses penuh ke seluruh sistem.</div>

<form method="POST" action="?step=4" id="admin-form">
  <div class="card">
    <div class="card-title">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Informasi Admin
    </div>

    <div class="form-group">
      <label>Nama ISP / Perusahaan <span class="req">*</span></label>
      <input type="text" name="isp_name" value="SilverNet" placeholder="Nama ISP Anda" required>
      <div class="form-hint">Tampil di header, invoice, dan kwitansi.</div>
    </div>

    <hr class="divider">

    <div class="form-row">
      <div class="form-group">
        <label>Nama Lengkap Admin <span class="req">*</span></label>
        <input type="text" name="admin_name" placeholder="John Doe" required>
      </div>
      <div class="form-group">
        <label>Username <span class="req">*</span></label>
        <input type="text" name="admin_username" placeholder="admin" required
               pattern="[a-zA-Z0-9_]+" title="Hanya huruf, angka, underscore"
               class="mono">
        <div class="form-hint">Hanya huruf, angka, underscore. Tidak bisa diubah.</div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Password <span class="req">*</span></label>
        <input type="password" name="admin_password" id="pwd" placeholder="Min. 8 karakter"
               required minlength="8" oninput="checkStrength(this.value)">
        <div class="strength-bar"><div class="strength-fill" id="strength-fill" style="width:0"></div></div>
        <div class="form-hint" id="strength-text">Masukkan password</div>
      </div>
      <div class="form-group">
        <label>Konfirmasi Password <span class="req">*</span></label>
        <input type="password" name="admin_confirm" id="pwd2" placeholder="Ulangi password"
               required oninput="checkMatch()">
        <div class="form-hint" id="match-text"></div>
      </div>
    </div>
  </div>

  <div class="alert alert-info" style="font-size:12px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    File <strong>config/database.php</strong> akan ditulis otomatis dengan kredensial yang dimasukkan di langkah 2.
  </div>

  <div class="btn-group">
    <a href="?step=3" class="btn btn-outline">← Kembali</a>
    <button type="submit" class="btn btn-primary btn-lg" id="finish-btn">
      Selesaikan Instalasi
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
  </div>
</form>


<!-- ════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($step === 5): ?>
<!-- STEP 5: DONE -->

<div class="success-hero">
  <div class="success-circle">
    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" fill="none"><polyline points="20 6 9 17 4 12"/></svg>
  </div>
  <div class="success-title">Instalasi Berhasil!</div>
  <div class="success-sub">
    SilverNet Management telah berhasil diinstal dan siap digunakan.
  </div>
  <a href="../index.php" class="btn btn-success btn-lg">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    Buka Aplikasi
  </a>
</div>

<div class="warning-box">
  <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="var(--warning)" fill="none" style="flex-shrink:0;margin-top:2px">
    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
  </svg>
  <div>
    <strong>⚠ Hapus Folder /install/ Sekarang!</strong>
    Folder <code>/install/</code> harus dihapus dari server setelah instalasi selesai untuk mencegah
    akses tidak sah dan menjalankan ulang installer. Jangan lewatkan langkah ini.
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <div class="card-title">Ringkasan Instalasi</div>
  <?php
    $cfg = $_SESSION['install_db'] ?? [];
    $rows = [
      'Host Database'  => htmlspecialchars($cfg['db_host'] ?? '-') . ':' . ($cfg['db_port'] ?? 3306),
      'Nama Database'  => '<code>' . htmlspecialchars($cfg['db_name'] ?? '-') . '</code>',
      'Tabel Dibuat'   => '13 tabel',
      'Config Ditulis' => '<code>config/database.php</code>',
      'Login URL'      => '<a href="../index.php">../index.php</a>',
    ];
  ?>
  <ul class="check-list">
    <?php foreach ($rows as $k => $v): ?>
    <li class="check-item">
      <span style="color:var(--muted);font-size:12px;"><?= $k ?></span>
      <span><?= $v ?></span>
    </li>
    <?php endforeach; ?>
  </ul>
</div>

<?php endif; ?>

</main>

<script>
// Password strength meter
function checkStrength(v) {
  let score = 0;
  if (v.length >= 8)  score++;
  if (v.length >= 12) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^a-zA-Z0-9]/.test(v)) score++;

  const fill  = document.getElementById('strength-fill');
  const text  = document.getElementById('strength-text');
  const colors = ['#d63939','#f76707','#fbbf24','#2fb344','#2fb344'];
  const labels = ['Sangat Lemah','Lemah','Cukup','Kuat','Sangat Kuat'];
  fill.style.width      = (score * 20) + '%';
  fill.style.background = colors[Math.max(0,score-1)] || '#e5e7eb';
  text.textContent      = v ? labels[Math.max(0,score-1)] : 'Masukkan password';
  text.style.color      = v ? colors[Math.max(0,score-1)] : 'var(--muted)';
}

function checkMatch() {
  const p1 = document.getElementById('pwd').value;
  const p2 = document.getElementById('pwd2').value;
  const el = document.getElementById('match-text');
  if (!p2) { el.textContent = ''; return; }
  if (p1 === p2) {
    el.textContent = '✓ Password cocok';
    el.style.color = 'var(--success)';
  } else {
    el.textContent = '✗ Password tidak cocok';
    el.style.color = 'var(--danger)';
  }
}

// Prevent double submit
const af = document.getElementById('admin-form');
if (af) {
  af.addEventListener('submit', function() {
    const btn = document.getElementById('finish-btn');
    btn.textContent = 'Memproses...';
    btn.disabled = true;
  });
}
</script>

</body>
</html>