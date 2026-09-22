<?php
/**
 * router_proxy.php — SNM SilverProxy
 * URL : /router_proxy.php/CUSTOMER_ID/TOKEN[/path][?query]
 *
 * Keamanan:
 * 1. Wajib login (session PHP)
 * 2. HMAC-SHA256 token terikat ke customer_id + wan_ip + port + window 1 jam
 * 3. WAN IP diambil live dari MikroTik API, bukan dari input user
 * 4. IP & port divalidasi, blokir SSRF
 * 5. Log akses
 */

if (!defined('PROXY_ENTRY')) {
    define('PROXY_ENTRY', true);
}
require_once __DIR__ . '/config/bootstrap.php';
if (!defined('SNM_TEST_MODE')) {
    \App\Auth\Middleware::requireLogin();
}

// ── SECRET KEY ────────────────────────────────────────────────────────────────
function proxySecret(): string {
    global $pdo;
    static $s = null;
    if ($s) return $s;
    try {
        $r = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='proxy_secret' LIMIT 1")->fetch();
        if ($r && strlen($r['setting_value'] ?? '') >= 32) { $s = $r['setting_value']; return $s; }
    } catch (Exception $e) {}
    $generated = bin2hex(random_bytes(32));
    try {
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('proxy_secret',?)
                       ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
            ->execute([$generated]);
    } catch (Exception $e) {}
    $s = $generated;
    return $s;
}

function verifyProxyToken(string $token, int $cid, string $ip, int $port): bool {
    $now = floor(time() / 3600);
    foreach ([$now, $now - 1] as $bucket) {
        if (hash_equals(hash_hmac('sha256', "{$cid}|{$ip}|{$port}|{$bucket}", proxySecret()), $token))
            return true;
    }
    return false;
}

function isSafeTargetIp(string $ip): bool {
    $long = ip2long($ip);
    if ($long === false) return false;
    foreach ([['127.0.0.0','127.255.255.255'],['169.254.0.0','169.254.255.255']] as [$lo,$hi])
        if ($long >= ip2long($lo) && $long <= ip2long($hi)) return false;
    if (($srv = $_SERVER['SERVER_ADDR'] ?? '') && $ip === $srv) return false;
    return true;
}

function isSafePort(int $port): bool {
    return $port >= 1 && $port <= 65535
        && !in_array($port, [22,23,25,110,143,3306,5432,6379,27017]);
}

function proxyLog(string $lvl, string $msg): void {
    $dir = BASE_PATH . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    @file_put_contents($dir . '/proxy.log',
        date('Y-m-d H:i:s') . " [$lvl] [uid:{$_SESSION['user_id']}] $msg\n",
        FILE_APPEND | LOCK_EX);
}

if (!defined('SNM_TEST_MODE')) {
// ── PARSE URL ─────────────────────────────────────────────────────────────────
$uri       = $_SERVER['REQUEST_URI'] ?? '';
$parsed    = parse_url($uri);
$uri_path  = $parsed['path'] ?? $uri;
$query_str = isset($parsed['query']) ? '?' . $parsed['query'] : '';

if (preg_match('#/(?:router_proxy\.php|router-proxy)/([0-9]+)/([0-9a-f]{64})(/.*)?$#', $uri_path, $m)) {
    $customer_id = (int)$m[1];
    $token       = $m[2];
    $target_path = ($m[3] ?? '') !== '' ? $m[3] : '/';
} else {
    http_response_code(403);
    die(_proxyError('Format URL tidak valid.', 403));
}

// ── AMBIL DATA PELANGGAN ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT c.id, c.remote_mgmt_port, c.pppoe_username, c.name AS cname,
           mk.host, mk.username, mk.password, mk.port AS api_port, mk.api_ssl
    FROM customers c
    LEFT JOIN packages p   ON p.id  = c.package_id
    LEFT JOIN mikrotiks mk ON mk.id = p.mikrotik_id
    WHERE c.id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    http_response_code(404);
    die(_proxyError('Pelanggan tidak ditemukan.', 404));
}

// ── WAN IP DARI MIKROTIK (live) ───────────────────────────────────────────────
$wan_ip   = null;
$wan_port = (int)($customer['remote_mgmt_port'] ?? 80) ?: 80;

if ($customer['host'] && $customer['pppoe_username']) {
    $conn = new \App\MikroTik\Connection(
        $customer['host'], $customer['username'], $customer['password'],
        (int)$customer['api_port']
    );
    if ($conn->isConnected()) {
        foreach ($conn->query('/ppp/active', 'print') as $s) {
            if (($s['name'] ?? '') === $customer['pppoe_username']) {
                $wan_ip = $s['address'] ?? null;
                break;
            }
        }
    }
}

if (!$wan_ip) {
    http_response_code(503);
    die(_proxyError('Sesi PPPoE pelanggan tidak aktif.', 503));
}

// ── VALIDASI ──────────────────────────────────────────────────────────────────
if (!verifyProxyToken($token, $customer_id, $wan_ip, $wan_port)) {
    http_response_code(403);
    proxyLog('WARN', "Token invalid: cid=$customer_id ip=$wan_ip:$wan_port");
    die(_proxyError('Token akses tidak valid atau kedaluwarsa. Kembali ke halaman detail pelanggan.', 403));
}
if (!isSafeTargetIp($wan_ip)) { http_response_code(403); die(_proxyError('IP target tidak diizinkan.', 403)); }
if (!isSafePort($wan_port))   { http_response_code(403); die(_proxyError('Port target tidak diizinkan.', 403)); }

// ── TARGET & PREFIX ───────────────────────────────────────────────────────────
$target_host  = $wan_ip . ':' . $wan_port;
$proxy_prefix = $script . '/' . $customer_id . '/' . $token;
$url          = 'http://' . $target_host . $target_path . $query_str;

proxyLog('INFO', "PROXY $url (cid=$customer_id {$customer['cname']})");

// ── CURL ──────────────────────────────────────────────────────────────────────
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_TIMEOUT,        15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);

$blocked_req = ['host','accept-encoding','origin','referer',
                'sec-fetch-site','sec-fetch-mode','sec-fetch-dest','connection',
                'x-forwarded-for','x-real-ip'];
$req_headers = [];
foreach ($_SERVER as $key => $val) {
    if (strpos($key, 'HTTP_') === 0) {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
        if (!in_array(strtolower($name), $blocked_req)) $req_headers[] = "$name: $val";
    } elseif (in_array($key, ['CONTENT_TYPE','CONTENT_LENGTH'])) {
        $req_headers[] = str_replace('_', '-', ucwords(strtolower($key), '_')) . ": $val";
    }
}
$req_headers[] = "Host: $target_host";
$req_headers[] = "Origin: http://$target_host";
$req_headers[] = "Referer: http://$target_host/";
$req_headers[] = "Connection: keep-alive";

if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']))
    curl_setopt($ch, CURLOPT_USERPWD, $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
curl_setopt($ch, CURLOPT_HTTPHEADER, $req_headers);

$body = file_get_contents('php://input');
if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

$resp_headers = [];
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$resp_headers) {
    $len   = strlen($header);
    $parts = explode(':', $header, 2);
    if (count($parts) === 2) {
        $name = strtolower(trim($parts[0]));
        if (in_array($name, ['set-cookie','location','www-authenticate','content-disposition']))
            $resp_headers[] = ['name' => trim($parts[0]), 'value' => trim($parts[1])];
    }
    return $len;
});

$response     = curl_exec($ch);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
$http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err     = curl_errno($ch) ? curl_error($ch) : null;
curl_close($ch);

if ($curl_err || $response === false) {
    http_response_code(502);
    die(_proxyError("Tidak bisa terhubung ke router. ($curl_err)", 502));
}

http_response_code($http_code);

// ── RESPONSE HEADERS ──────────────────────────────────────────────────────────
foreach ($resp_headers as $hdr) {
    $name = trim($hdr['name']);
    $val  = trim($hdr['value']);
    if (in_array(strtolower($name), ['x-frame-options','content-security-policy','x-xss-protection'])) continue;
    if (strtolower($name) === 'location') {
        $val = str_replace("http://$target_host", '', $val);
        $val = str_starts_with($val, '/') ? $proxy_prefix . $val : $proxy_prefix . '/' . $val;
    }
    if (strtolower($name) === 'set-cookie') {
        $val = preg_replace('/path=\/(.*?)(;|$)/i', 'path=' . $proxy_prefix . '/$1$2', $val);
        if (!isset($_SERVER['HTTPS'])) $val = preg_replace('/;\s*Secure/i', '', $val);
    }
    header("$name: $val", false);
}
if ($content_type) header('Content-Type: ' . $content_type);

// ── REWRITE KONTEN ────────────────────────────────────────────────────────────
$ext     = strtolower(pathinfo(parse_url($target_path, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
$is_text = (bool)preg_match('/text|javascript|json|xml/i', $content_type);
if (!$is_text) {
    if (in_array($ext, ['html','htm','ghtml','gch','js','css','xml','json','asp','aspx','cgi']))
        $is_text = true;
    elseif (is_string($response) && preg_match('/<(!DOCTYPE|html|head|body)\b/i', substr($response, 0, 512))) {
        $is_text = true;
        header('Content-Type: text/html; charset=utf-8', true);
    }
}

if ($is_text && $response !== false) {
    // Netralkan Script Frame-Busting yang umum di ZTE/Huawei
    $response = preg_replace('/if\s*\(\s*window\.top\s*!==?\s*window\.self\s*\)/i', 'if(false)', $response);
    $response = preg_replace('/if\s*\(\s*top\s*!==?\s*self\s*\)/i', 'if(false)', $response);
    $response = preg_replace('/top\.location(\.href)?\s*=/i', 'window.location$1 =', $response);

    // Ganti URL absolut router
    $response = preg_replace('/https?:\/\/localhost(:\d+)?/i', $proxy_prefix, $response);
    $response = preg_replace('/\/\/localhost/i',               $proxy_prefix, $response);
    $response = str_replace("http://$target_host",             $proxy_prefix, $response);

    // ── PERBAIKAN PATH GAMBAR & BACKGROUND ZTE ──

    // --- LOGIKA RESOLUSI PATH RELATIF (ANTI-404 UNTUK TP-LINK) ---
    $current_dir = str_replace('\\', '/', dirname($target_path));
    $resolve_rel = function($base, $rel) {
        $path = rtrim($base, '/') . '/' . ltrim($rel, '/');
        $parts = explode('/', $path);
        $out = [];
        foreach ($parts as $p) {
            if ($p === '' || $p === '.') continue;
            if ($p === '..') array_pop($out);
            else $out[] = $p;
        }
        return '/' . implode('/', $out);
    };

    // 1. Atribut HTML absolut (/path)
    $response = preg_replace(
        '/(src|href|action|background)\s*=\s*(["\'])\/(.*?)\2/i',
        '$1=$2' . $proxy_prefix . '/$3$2',
        $response
    );

    // 2. Atribut HTML path naik (../path) -> Dihitung dinamis sesuai lokasi saat ini
    $response = preg_replace_callback(
        '/(src|href|action|background)\s*=\s*(["\'])\.\.\/(.*?)\2/i',
        function($m) use ($proxy_prefix, $current_dir, $resolve_rel) {
            return $m[1] . '=' . $m[2] . $proxy_prefix . $resolve_rel($current_dir, '../' . $m[3]) . $m[2];
        },
        $response
    );

    // 3. String generik ../ di dalam JavaScript
    $response = preg_replace_callback(
        '/(["\'])\.\.\/([^"\'<>]+)\1/i',
        function($m) use ($proxy_prefix, $current_dir, $resolve_rel) {
            return $m[1] . $proxy_prefix . $resolve_rel($current_dir, '../' . $m[2]) . $m[1];
        },
        $response
    );

    // 4. CSS url(/path)
    $response = preg_replace(
        '/url\(\s*(["\']?)\/(.*?)\1\s*\)/i',
        'url($1' . $proxy_prefix . '/$2$1)',
        $response
    );

    // 5. CSS url(../path) -> Dihitung dinamis untuk TP-Link sprite images
    $response = preg_replace_callback(
        '/url\(\s*(["\']?)\.\.\/(.*?)\1\s*\)/i',
        function($m) use ($proxy_prefix, $current_dir, $resolve_rel) {
            return 'url(' . $m[1] . $proxy_prefix . $resolve_rel($current_dir, '../' . $m[2]) . $m[1] . ')';
        },
        $response
    );

    // API path berbagai vendor
    // $response = preg_replace(
    //     '/(["\'])\/(boafrm|goform|cgi-bin|api|form|setup|luci|stok|wlan|lan|wan|login|webfig|rest)(.*?)\1/i',
    //     '$1' . $proxy_prefix . '/$2$3$1',
    //     $response
    // );

    // API path berbagai vendor dan Folder Asset Absolut (JS/Vue)
    // 6. API path berbagai vendor dan Folder Asset Absolut (DITAMBAH 'icons')
    $response = preg_replace(
        '/(["\'])\/(boafrm|goform|cgi-bin|api|form|setup|luci|stok|wlan|lan|wan|login|webfig|rest|static|plugin|images|img|icons|css|js|theme|pic|web)(.*?)\1/i',
        '$1' . $proxy_prefix . '/$2$3$1',
        $response
    );

    // HTML: base tag + banner
    $is_html = stripos($content_type, 'text/html') !== false
            || in_array($ext, ['html','htm','ghtml','gch']);
            
    // CEGAH INJEKSI PADA AJAX / SCRIPT NAKAL:
    // Pastikan ini benar-benar halaman web (punya tag html, head, body, atau DOCTYPE)
    $is_real_html_page = preg_match('/<(html|head|body|!DOCTYPE)\b/i', substr($response, 0, 2048));

    if ($is_html && $is_real_html_page) {
        $base_tag = '<base href="' . $proxy_prefix . '/">';
        if (stripos($response, '<base') !== false) {
            $response = preg_replace('/<base[^>]*>/i', $base_tag, $response, 1);
        } else {
            $response = preg_replace('/(<head[^>]*>)/i', '$1' . "\n    " . $base_tag, $response, 1);
        }

        $banner   = _proxyBanner($customer, $wan_ip, $wan_port, $customer_id);
        if (preg_match('/(<body[^>]*>)/i', $response)) {
            $response = preg_replace('/(<body[^>]*>)/i', '$1' . $banner, $response, 1);
        } elseif (preg_match('/(<\/head>)/i', $response)) {
            $response = preg_replace('/(<\/head>)/i', $banner . '$1', $response, 1);
        } else {
            $response = $banner . $response;
        }
    }
}

echo $response;
}

function _proxyError(string $msg, int $code): string {
    $labels = [400=>'Bad Request',403=>'Akses Ditolak',404=>'Tidak Ditemukan',
               502=>'Bad Gateway',503=>'Service Unavailable'];
    $back = defined('BASE_URL') ? BASE_URL . '/customers' : '/customers';
    
    // Perbaikan: Evaluasi variabel di luar Heredoc
    $title = $labels[$code] ?? $code; 

    return <<<HTML
<!doctype html><html lang="id"><head><meta charset="utf-8">
<title>SilverProxy — Error $code</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/css/tabler.min.css">
</head><body class="antialiased"><div class="page page-center">
  <div class="container-tight py-5 text-center">
    <div class="mb-3 text-red">
      <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none">
        <path d="M12 9v4m0 3v.01"/><path d="M5.07 19H19a2 2 0 0 0 1.75-2.75L13.75 4a2 2 0 0 0-3.5 0L3.25 16.25A2 2 0 0 0 5.07 19z"/>
      </svg>
    </div>
    <h1>{$title}</h1>
    <p class="text-muted">$msg</p>
    <a href="javascript:history.back()" class="btn btn-secondary me-2">Kembali</a>
    <a href="$back" class="btn btn-primary">Daftar Pelanggan</a>
  </div>
</div></body></html>
HTML;
}

function _proxyBanner(array $c, string $ip, int $port, int $cid): string {
    $name       = htmlspecialchars($c['cname'] ?? '');
    $detail_url = BASE_URL . '/customers/' . $cid;
    $ts         = date('H:i:s');
    return <<<'BANNEREOF'
<style>
  #snm-bar-root { all: initial; }
  #snm-bar-root *{box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif}
  #snm-bar-root{position:fixed;top:0;left:0;right:0;z-index:2147483647;height:42px;
    background:linear-gradient(90deg,#0f172a 0%,#1e3a5f 55%,#0f172a 100%);
    border-bottom:1px solid rgba(99,179,237,.25);
    box-shadow:0 2px 16px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.04) inset;
    display:flex;align-items:center;padding:0 14px;gap:0;user-select:none}
  #snm-bar-left{display:flex;align-items:center;gap:10px;padding-right:14px;border-right:1px solid rgba(255,255,255,.1)}
  #snm-bar-logo{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;letter-spacing:.04em;color:#e2e8f0}
  #snm-bar-badge{font-size:10px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;
    background:rgba(56,189,248,.15);color:#38bdf8;border:1px solid rgba(56,189,248,.3);border-radius:3px;padding:1px 6px}
  #snm-bar-mid{display:flex;align-items:center;gap:16px;padding:0 16px;flex:1}
  .snm-chip{display:flex;align-items:center;gap:5px;font-size:11px;color:rgba(226,232,240,.6)}
  .snm-chip strong{color:#e2e8f0;font-weight:600}
  .snm-chip code{font-family:'Consolas','Courier New',monospace;font-size:10.5px;
    background:rgba(255,255,255,.07);color:#7dd3fc;padding:1px 6px;border-radius:3px;
    border:1px solid rgba(125,211,252,.15)}
  .snm-dot{width:6px;height:6px;border-radius:50%;background:#4ade80;
    box-shadow:0 0 6px #4ade80;animation:snm-pulse 2s ease-in-out infinite;flex-shrink:0}
  @keyframes snm-pulse{0%,100%{opacity:1}50%{opacity:.4}}
  #snm-bar-right{display:flex;align-items:center;gap:10px;padding-left:14px;border-left:1px solid rgba(255,255,255,.1)}
  #snm-watermark{font-size:10px;color:rgba(226,232,240,.3);letter-spacing:.02em;white-space:nowrap}
  #snm-watermark span{color:#f87171}
  .snm-btn{font-size:11px;font-weight:500;border-radius:4px;padding:3px 9px;cursor:pointer;
    border:none;text-decoration:none;white-space:nowrap;transition:background .15s,color .15s}
  .snm-btn-ghost{background:rgba(255,255,255,.07);color:#94a3b8;border:1px solid rgba(255,255,255,.1)}
  .snm-btn-ghost:hover{background:rgba(255,255,255,.13);color:#e2e8f0}
  .snm-btn-primary{background:rgba(56,189,248,.15);color:#38bdf8;border:1px solid rgba(56,189,248,.25)}
  .snm-btn-primary:hover{background:rgba(56,189,248,.25)}
  #snm-frame{position:fixed;top:42px;left:0;right:0;bottom:0;z-index:2147483646;
    pointer-events:none;border-left:2px solid rgba(56,189,248,.18);
    border-right:2px solid rgba(56,189,248,.18);border-bottom:2px solid rgba(56,189,248,.18);
    border-radius:0 0 4px 4px}
  #snm-frame-corner-bl,#snm-frame-corner-br{position:absolute;bottom:6px;
    font-size:10px;color:rgba(56,189,248,.3);letter-spacing:.04em}
  #snm-frame-corner-bl{left:10px}
  #snm-frame-corner-br{right:10px}
</style>
BANNEREOF
    . "\n"
    . '<div id="snm-bar-root">'
    . '<div id="snm-bar-left">'
    . '<div id="snm-bar-logo">SNM</div>'
    . '<span id="snm-bar-badge">SilverProxy</span>'
    . '</div>'
    . '<div id="snm-bar-mid">'
    . '<div class="snm-chip"><span class="snm-dot"></span><strong>Live</strong></div>'
    . '<div class="snm-chip"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12h.01"/><path d="M17 12h.01"/><path d="M7 12h.01"/></svg><code>' . $ip . ':' . $port . '</code></div>'
    . '<div class="snm-chip"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/></svg><strong>' . $name . '</strong></div>'
    . '<div class="snm-chip" id="snm-clock">' . $ts . '</div>'
    . '</div>'
    . '<div id="snm-bar-right">'
    . '<div id="snm-watermark">Created with <span>&#10084;&#65039;</span> for Silver Wolf</div>'
    . '<a href="' . $detail_url . '" class="snm-btn snm-btn-primary">&#8592; Detail Pelanggan</a>'
    . '<button class="snm-btn snm-btn-ghost" onclick="document.getElementById(\'snm-bar-root\').remove();document.getElementById(\'snm-frame\').remove();document.documentElement.style.paddingTop=\'0\';">&#10005;</button>'
    . '</div></div>'
    . '<div id="snm-frame"><div id="snm-frame-corner-bl">SNM &middot; SilverProxy</div><div id="snm-frame-corner-br">' . $ip . ':' . $port . '</div></div>'
    . '<script>
        (function(){
            // Jika script ini jalan di dalam iframe milik router (misal top.gch), hancurkan banner!
            if (window.top !== window.self) {
                var root = document.getElementById("snm-bar-root");
                var frame = document.getElementById("snm-frame");
                if (root) root.parentNode.removeChild(root);
                if (frame) frame.parentNode.removeChild(frame);
                return; // Stop eksekusi agar tidak mengubah padding
            }
            
            // Terapkan padding HANYA ke halaman paling induk
            document.documentElement.style.setProperty("padding-top", "42px", "important");
            document.documentElement.style.setProperty("box-sizing", "border-box", "important");
            document.body.style.setProperty("margin-top", "0", "important");

            var cl=document.getElementById("snm-clock");
            if(cl) setInterval(function(){
                var d=new Date();
                cl.textContent=d.toLocaleTimeString("id-ID",{hour:"2-digit",minute:"2-digit",second:"2-digit"});
            },1000);
            document.title="SNM \u00B7 SilverProxy \u00B7 ' . $name . '";
        })();
       </script>';
}