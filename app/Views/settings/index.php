<?php
if (!function_exists('get_setting')) {
    function get_setting($pdo, $key, $default = '') {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['setting_value'] : $default;
    }
}
?>
<!doctype html>
<html lang="id">
<?php \App\Core\View::header(); ?>
<body>
<div class="page">
  <?php \App\Core\View::sidebar(); ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle">Sistem</div>
            <h2 class="page-title">Pengaturan</h2>
          </div>
        </div>
      </div>
    </div>
    <div class="page-body">
      <div class="container-xl">
        <?php if (!empty($msg_text)): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible mb-3" role="alert">
          <?= htmlspecialchars($msg_text) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row g-3">
          <!-- NAV -->
          <div class="col-12 col-md-3">
            <div class="card">
              <div class="list-group list-group-flush">
                <a href="#general"      class="list-group-item list-group-item-action active" data-bs-toggle="list">Umum</a>
                <a href="#billing-cfg"  class="list-group-item list-group-item-action"        data-bs-toggle="list">Tagihan &amp; Pembayaran</a>
                <a href="#notification" class="list-group-item list-group-item-action"        data-bs-toggle="list">Notifikasi</a>
                <a href="#apikeys"      class="list-group-item list-group-item-action"        data-bs-toggle="list">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                  API Keys
                </a>
              </div>
            </div>
          </div>

          <!-- CONTENT -->
          <div class="col-12 col-md-9">
            <div class="tab-content">

              <!-- ── GENERAL ───────────────────────────────────────────── -->
              <div class="tab-pane active" id="general">
                <div class="card">
                  <div class="card-header"><h3 class="card-title">Pengaturan Umum</h3></div>
                  <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_general">
                    <input type="hidden" name="isp_logo_path" id="isp_logo_path" value="">
                    <div class="card-body">
                      <div class="row g-3">
                        <div class="col-md-6">
                          <label class="form-label">Nama ISP / Perusahaan</label>
                          <input type="text" name="isp_name" class="form-control" value="<?= htmlspecialchars(get_setting($pdo,'isp_name',$isp_name??'')) ?>">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Nama Aplikasi</label>
                          <input type="text" name="app_name" class="form-control" value="<?= htmlspecialchars(get_setting($pdo,'app_name','Silver Network Management')) ?>">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Telepon Admin</label>
                          <input type="text" name="admin_phone" class="form-control" value="<?= htmlspecialchars(get_setting($pdo,'admin_phone')) ?>">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Email Admin</label>
                          <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars(get_setting($pdo,'admin_email')) ?>">
                        </div>
                        <div class="col-12">
                          <label class="form-label">Alamat Kantor</label>
                          <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars(get_setting($pdo,'address')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">NPWP</label>
                          <input type="text" name="tax_number" class="form-control" value="<?= htmlspecialchars(get_setting($pdo,'tax_number')) ?>">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Zona Waktu</label>
                          <?php
                          $cur_tz = get_setting($pdo, 'timezone', 'Asia/Jakarta');
                          $tz_groups = [
                            'Indonesia' => [
                              'Asia/Jakarta'    => 'WIB — Waktu Indonesia Barat (UTC+7)',
                              'Asia/Makassar'   => 'WITA — Waktu Indonesia Tengah (UTC+8)',
                              'Asia/Jayapura'   => 'WIT — Waktu Indonesia Timur (UTC+9)',
                            ],
                            'Asia Tenggara' => [
                              'Asia/Singapore'  => 'Singapura (UTC+8)',
                              'Asia/Kuala_Lumpur'=> 'Malaysia (UTC+8)',
                              'Asia/Bangkok'    => 'Thailand (UTC+7)',
                              'Asia/Manila'     => 'Filipina (UTC+8)',
                            ],
                            'Asia Timur' => [
                              'Asia/Tokyo'      => 'Jepang (UTC+9)',
                              'Asia/Shanghai'   => 'China (UTC+8)',
                            ],
                            'Lainnya' => [
                              'UTC'             => 'UTC (UTC+0)',
                              'Europe/London'   => 'London (UTC+0/+1)',
                              'America/New_York'=> 'New York (UTC-5/-4)',
                            ],
                          ];
                          ?>
                          <select name="timezone" class="form-select">
                            <?php foreach ($tz_groups as $group => $tzs): ?>
                            <optgroup label="<?= htmlspecialchars($group) ?>">
                              <?php foreach ($tzs as $tz_val => $tz_label): ?>
                              <option value="<?= $tz_val ?>" <?= $cur_tz === $tz_val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tz_label) ?>
                              </option>
                              <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                          </select>
                          <div class="form-hint">
                            Waktu server sekarang: <strong id="server-time"><?= date('d M Y H:i:s T') ?></strong>
                          </div>
                        </div>

                        <!-- ISP Logo upload -->
                        <div class="col-12">
                          <label class="form-label">Logo ISP</label>
                          <div class="d-flex align-items-center gap-3 flex-wrap">
                            <?php if ($cur_isp_logo): ?>
                            <div id="isp-logo-preview" class="border rounded p-2 bg-light d-flex align-items-center gap-2">
                              <img src="<?= htmlspecialchars($cur_isp_logo) ?>" id="isp-logo-img" style="height:40px;object-fit:contain;" onerror="this.style.display='none'">
                              <span class="text-muted small" id="isp-logo-filename"><?= htmlspecialchars(basename($cur_isp_logo)) ?></span>
                            </div>
                            <?php else: ?>
                            <div id="isp-logo-preview" class="text-muted small fst-italic" style="display:none;"></div>
                            <?php endif; ?>
                            <label class="btn btn-outline-secondary btn-sm mb-0">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" class="me-1"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                              Upload Logo ISP
                              <input type="file" accept="image/*" style="display:none;" onchange="uploadImage(this,'isp_logo_path','isp-logo-preview','isp-logo-img','isp-logo-filename','isp_logo')">
                            </label>
                          </div>
                          <div class="form-hint">PNG transparan disarankan. Maks 2MB. Tampil di header invoice &amp; kwitansi.</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <button type="submit" class="btn btn-primary">Simpan Pengaturan Umum</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- ── BILLING ────────────────────────────────────────────── -->
              <div class="tab-pane" id="billing-cfg">
                <div class="card">
                  <div class="card-header"><h3 class="card-title">Tagihan &amp; Pembayaran</h3></div>
                  <form method="POST" id="billing-form">
                    <input type="hidden" name="action" value="save_billing">
                    <input type="hidden" name="payment_methods" id="payment_methods_json" value="<?= htmlspecialchars($cur_pay_methods) ?>">
                    <input type="hidden" name="reseller_logo_path" id="reseller_logo_path" value="">
                    <div class="card-body">
                      <div class="row g-3">
                        <div class="col-md-6">
                          <label class="form-label">Batas Hari Jatuh Tempo Default</label>
                          <input type="number" name="billing_due_days" class="form-control" value="<?= get_setting($pdo,'billing_due_days',10) ?>">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Batas Hari Sebelum Isolir</label>
                          <input type="number" name="billing_isolation_days" class="form-control" value="<?= get_setting($pdo,'billing_isolation_days',5) ?>">
                        </div>

                        <!-- Payment methods -->
                        <div class="col-12 mt-2">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-uppercase text-muted">Metode Pembayaran</span>
                            <div class="d-flex gap-1">
                              <button type="button" class="btn btn-sm btn-outline-primary"   onclick="addMethod('bank')">+ Bank</button>
                              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addMethod('ewallet')">+ E-Wallet</button>
                              <button type="button" class="btn btn-sm btn-outline-success"   onclick="addMethod('qris')">+ QRIS</button>
                            </div>
                          </div>
                          <div id="methods-list" class="d-flex flex-column gap-2"></div>
                          <div class="form-hint mt-1">Metode yang diaktifkan tampil di invoice, kwitansi, dan modal pembayaran.</div>
                        </div>

                        <!-- Reseller branding -->
                        <div class="col-12 mt-2"><hr class="my-1"><span class="fw-bold small text-uppercase text-muted">Branding ISP Upstream (Provider yang Anda Resell)</span></div>
                        <div class="col-md-6">
                          <label class="form-label">Nama ISP Upstream</label>
                          <input type="text" name="reseller_name" class="form-control" value="<?= htmlspecialchars(get_setting($pdo,'reseller_name')) ?>" placeholder="Kosongkan jika bukan reseller">
                          <div class="form-hint">Tampil sebagai "Supported by ..." di footer invoice &amp; kwitansi pelanggan.</div>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Logo ISP Upstream</label>
                          <div class="d-flex align-items-center gap-3 flex-wrap">
                            <?php if ($cur_reseller_logo): ?>
                            <div id="reseller-logo-preview" class="border rounded p-2 bg-light d-flex align-items-center gap-2">
                              <img src="<?= htmlspecialchars($cur_reseller_logo) ?>" id="reseller-logo-img" style="height:32px;object-fit:contain;" onerror="this.style.display='none'">
                              <span class="text-muted small" id="reseller-logo-filename"><?= htmlspecialchars(basename($cur_reseller_logo)) ?></span>
                              <button type="button" class="btn btn-sm btn-ghost-danger" onclick="clearResellerLogo()" title="Hapus logo">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                              </button>
                            </div>
                            <?php else: ?>
                            <div id="reseller-logo-preview" style="display:none;" class="border rounded p-2 bg-light d-flex align-items-center gap-2">
                              <img id="reseller-logo-img" style="height:32px;object-fit:contain;">
                              <span class="text-muted small" id="reseller-logo-filename"></span>
                              <button type="button" class="btn btn-sm btn-ghost-danger" onclick="clearResellerLogo()" title="Hapus logo">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                              </button>
                            </div>
                            <?php endif; ?>
                            <label class="btn btn-outline-secondary btn-sm mb-0">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" class="me-1"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                              Upload Logo ISP Upstream
                              <input type="file" accept="image/*" style="display:none;" onchange="uploadImage(this,'reseller_logo_path','reseller-logo-preview','reseller-logo-img','reseller-logo-filename','reseller_logo')">
                            </label>
                            <input type="hidden" name="reseller_logo_clear" id="reseller_logo_clear" value="">
                            <span class="text-muted small fst-italic">Kosongkan jika bukan reseller</span>
                          </div>
                          <div class="form-hint mt-1">Maks 2MB. PNG transparan disarankan.</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <button type="submit" class="btn btn-primary">Simpan Pengaturan Tagihan</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- ── NOTIFICATION ──────────────────────────────────────── -->
              <div class="tab-pane" id="notification">
                <div class="card">
                  <div class="card-header"><h3 class="card-title">Notifikasi Telegram</h3></div>
                  <form method="POST">
                    <input type="hidden" name="action" value="save_notification">
                    <div class="card-body">
                      <div class="row g-3">
                        <div class="col-12">
                          <div class="alert alert-info">Buat bot Telegram melalui @BotFather, lalu isi token dan Chat ID tujuan notifikasi.</div>
                        </div>
                        <div class="col-12">
                          <label class="form-label">Bot Token</label>
                          <input type="text" name="telegram_bot_token" class="form-control" value="<?= htmlspecialchars(get_setting($pdo,'telegram_bot_token')) ?>" placeholder="1234567890:AAAA...">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Chat ID / Group ID</label>
                          <input type="text" name="telegram_chat_id" class="form-control" value="<?= htmlspecialchars(get_setting($pdo,'telegram_chat_id')) ?>" placeholder="-100xxxxxxxxx">
                        </div>
                        <div class="col-12">
                          <label class="form-label">Kirim Notifikasi Untuk:</label>
                          <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="notify_new_payment" id="notif-pay" value="1" <?= get_setting($pdo,'notify_new_payment')==='1'?'checked':'' ?>>
                            <label class="form-check-label" for="notif-pay">Pembayaran baru masuk</label>
                          </div>
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="notify_offline_router" id="notif-rt" value="1" <?= get_setting($pdo,'notify_offline_router')==='1'?'checked':'' ?>>
                            <label class="form-check-label" for="notif-rt">Router offline terdeteksi</label>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <button type="submit" class="btn btn-primary">Simpan Notifikasi</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- ── ACCOUNT ────────────────────────────────────────────── -->
              <!-- ── API KEYS ──────────────────────────────────────────── -->
              <div class="tab-pane" id="apikeys">

                <?php if ($new_key_value): ?>
                <div class="alert alert-success alert-dismissible mb-3" role="alert">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon text-success flex-shrink-0" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M5 12l5 5l10-10"/></svg>
                    <div>
                      <strong>API key berhasil dibuat! Salin sekarang — tidak akan ditampilkan lagi.</strong><br>
                      <code id="new-key-val" class="user-select-all"><?= htmlspecialchars($new_key_value) ?></code>
                    </div>
                    <button class="btn btn-sm btn-success ms-auto" onclick="copyNewKey()">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" class="me-1"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                      Salin Key
                    </button>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Buat API Key Baru -->
                <div class="card mb-3">
                  <div class="card-header">
                    <h3 class="card-title">Buat API Key Baru</h3>
                  </div>
                  <form method="POST">
                    <input type="hidden" name="action" value="create_api_key">
                    <div class="card-body">
                      <div class="row g-3">
                        <div class="col-12 col-md-5">
                          <label class="form-label required">Nama Key <span class="text-muted small">(untuk identifikasi)</span></label>
                          <input type="text" name="key_name" class="form-control" placeholder="misal: Monitoring Dashboard" required maxlength="100">
                        </div>
                        <div class="col-12 col-md-7">
                          <label class="form-label required">Permissions</label>
                          <div class="d-flex flex-wrap gap-3 mt-1">
                            <label class="form-check">
                              <input class="form-check-input" type="checkbox" name="permissions[]" value="read_customers" checked>
                              <span class="form-check-label">
                                <strong>read_customers</strong>
                                <span class="text-muted d-block" style="font-size:11px">Data pelanggan dari database</span>
                              </span>
                            </label>
                            <label class="form-check">
                              <input class="form-check-input" type="checkbox" name="permissions[]" value="read_live" checked>
                              <span class="form-check-label">
                                <strong>read_live</strong>
                                <span class="text-muted d-block" style="font-size:11px">WAN IP &amp; status live dari MikroTik</span>
                              </span>
                            </label>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 5v14m-7-7h14"/></svg>
                        Generate API Key
                      </button>
                    </div>
                  </form>
                </div>

                <!-- Daftar API Keys -->
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">API Keys Anda</h3>
                    <div class="card-options text-muted small">Total: <?= count($api_keys) ?> key</div>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter table-hover card-table">
                      <thead>
                        <tr>
                          <th>Nama</th>
                          <th>API Key</th>
                          <th>Permissions</th>
                          <th>Terakhir Digunakan</th>
                          <th>Status</th>
                          <th class="text-end">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($api_keys)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada API key. Buat yang pertama di atas.</td></tr>
                        <?php else: foreach ($api_keys as $k): ?>
                        <tr>
                          <td class="fw-semibold"><?= htmlspecialchars($k['name']) ?></td>
                          <td>
                            <div class="input-group input-group-sm" style="max-width:280px;">
                              <input type="password" class="form-control form-control-sm font-monospace key-input"
                                     value="<?= htmlspecialchars($k['api_key']) ?>"
                                     readonly style="font-size:11px;">
                              <button class="btn btn-outline-secondary btn-sm toggle-key-vis" type="button" title="Tampilkan/Sembunyikan">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                              </button>
                              <button class="btn btn-outline-secondary btn-sm copy-key-btn" type="button" data-key="<?= htmlspecialchars($k['api_key']) ?>" title="Salin">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                              </button>
                            </div>
                            <div class="text-muted" style="font-size:10px;margin-top:2px;">Dibuat: <?= date('d M Y', strtotime($k['created_at'])) ?></div>
                          </td>
                          <td>
                            <?php foreach (explode(',', $k['permissions']) as $p): ?>
                            <span class="badge bg-blue-lt me-1"><?= htmlspecialchars($p) ?></span>
                            <?php endforeach; ?>
                          </td>
                          <td class="text-muted small">
                            <?= $k['last_used_at'] ? date('d M Y H:i', strtotime($k['last_used_at'])) : '<em>Belum pernah</em>' ?>
                          </td>
                          <td>
                            <?php if ($k['is_active']): ?>
                            <span class="badge bg-success-lt">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-danger-lt">Nonaktif</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-end">
                            <form method="POST" class="d-inline">
                              <input type="hidden" name="action" value="toggle_api_key">
                              <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                              <button type="submit" class="btn btn-sm <?= $k['is_active'] ? 'btn-ghost-warning' : 'btn-ghost-success' ?>" title="<?= $k['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                <?= $k['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                              </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus API key \'<?= addslashes($k['name']) ?>\'? Tidak bisa dibatalkan.')">
                              <input type="hidden" name="action" value="delete_api_key">
                              <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-ghost-danger">Hapus</button>
                            </form>
                          </td>
                        </tr>
                        <?php endforeach; endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Dokumentasi singkat -->
                <div class="card mt-3">
                  <div class="card-header">
                    <h3 class="card-title">Cara Penggunaan API</h3>
                  </div>
                  <div class="card-body">
                    <!-- Endpoint 1: Status (Monitoring) -->
                    <div class="mb-4">
                      <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-green text-white">GET</span>
                        <code><?= BASE_URL ?>/api/v1/status.php</code>
                        <span class="badge bg-blue-lt">Monitoring</span>
                      </div>
                      <p class="text-muted small mb-2">Khusus monitoring. Mengembalikan semua pelanggan sekaligus dengan status online/offline &amp; WAN IP live dari MikroTik. Identifier stabil: <code>customer_number</code>.</p>
                      <table class="table table-sm table-bordered mb-2">
                        <thead class="table-light"><tr><th>Parameter</th><th>Keterangan</th></tr></thead>
                        <tbody>
                          <tr><td><code>api_key</code></td><td>API key (atau header <code>Authorization: Bearer &lt;key&gt;</code>). Butuh permission <strong>read_live</strong>.</td></tr>
                          <tr><td><code>node_id</code></td><td>Filter per node (opsional)</td></tr>
                          <tr><td><code>router_id</code></td><td>Filter per MikroTik (opsional)</td></tr>
                          <tr><td><code>status</code></td><td>Filter status DB: <code>active</code> / <code>isolated</code> / <code>terminated</code> / <code>free</code></td></tr>
                        </tbody>
                      </table>
                      <pre class="bg-dark text-white rounded p-3 mb-0" style="font-size:11px;"><code># Contoh response:
{
  "success": true,
  "fetched_at": "2025-03-09T14:00:00+07:00",
  "elapsed_ms": 312,
  "summary": { "total": 150, "online": 128, "offline": 22 },
  "customers": [
    {
      "customer_number": "2323",   <span style="color:#86efac">// ← gunakan ini sebagai key di monitoring app</span>
      "id": 45,
      "name": "Budi Santoso",
      "pppoe_username": "budi-santoso",
      "service_status": "active",  <span style="color:#86efac">// status di DB (active/isolated/dll)</span>
      "is_online": true,            <span style="color:#86efac">// status sesi PPPoE saat ini</span>
      "wan_ip": "10.25.0.187",      <span style="color:#86efac">// null jika offline → ping target</span>
      "uptime": "1d2h3m45s",
      "node": "Node Timur",
      "package": "20Mbps Home",
      "router_host": "192.168.88.1"
    }
  ]
}</code></pre>
                    </div>

                    <hr class="my-3">

                    <!-- Endpoint 2: Customers (Detail) -->
                    <div>
                      <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-green text-white">GET</span>
                        <code><?= BASE_URL ?>/api/v1/customers.php</code>
                        <span class="badge bg-azure-lt">Detail</span>
                      </div>
                      <p class="text-muted small mb-2">Data lengkap pelanggan (paket, router, billing, dll). Cocok untuk sinkronisasi data.</p>
                      <table class="table table-sm table-bordered mb-2">
                        <thead class="table-light"><tr><th>Parameter</th><th>Keterangan</th></tr></thead>
                        <tbody>
                          <tr><td><code>api_key</code></td><td>API key</td></tr>
                          <tr><td><code>id</code></td><td>ID pelanggan spesifik</td></tr>
                          <tr><td><code>search</code></td><td>Cari nama / PPPoE username / nomor / telepon</td></tr>
                          <tr><td><code>status</code></td><td><code>active</code> / <code>isolated</code> / <code>terminated</code> / <code>free</code></td></tr>
                          <tr><td><code>node_id</code></td><td>Filter per node</td></tr>
                          <tr><td><code>live</code></td><td><code>1</code> sertakan WAN IP live · <code>0</code> skip MikroTik (lebih cepat)</td></tr>
                        </tbody>
                      </table>
                      <pre class="bg-dark text-white rounded p-3 mb-0" style="font-size:11px;"><code>curl -H "Authorization: Bearer &lt;api_key&gt;" \
  "<?= BASE_URL ?>/api/v1/customers.php?status=active&live=1"</code></pre>
                    </div>
                  </div>
                </div>

              </div><!-- /apikeys pane -->

            </div><!-- /tab-content -->
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Upload progress toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
  <div id="upload-toast" class="toast align-items-center text-bg-primary border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="upload-toast-msg">Mengupload...</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script>
// ── Upload helper ─────────────────────────────────────────────────────────
function showToast(msg, ok) {
    const t = document.getElementById('upload-toast');
    t.className = 'toast align-items-center border-0 text-bg-' + (ok ? 'success' : 'danger');
    document.getElementById('upload-toast-msg').textContent = msg;
    bootstrap.Toast.getOrCreateInstance(t, {delay: 2500}).show();
}

async function uploadImage(input, pathFieldId, previewId, imgId, filenameId, context) {
    if (!input.files.length) return;
    const file = input.files[0];
    showToast('Mengupload ' + file.name + '...', true);

    const fd = new FormData();
    fd.append('image', file);
    fd.append('context', context);

    try {
        const res  = await fetch('api/upload_image.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.error) { showToast('Gagal: ' + data.error, false); return; }

        // Update hidden path field
        document.getElementById(pathFieldId).value = data.path;

        // Update preview
        const preview = document.getElementById(previewId);
        const img     = document.getElementById(imgId);
        const fname   = document.getElementById(filenameId);
        if (img)     { img.src = data.path; img.style.display = ''; }
        if (fname)   { fname.textContent = file.name; }
        if (preview) { preview.style.display = ''; }

        showToast('Upload berhasil: ' + file.name, true);
    } catch(e) {
        showToast('Error upload: ' + e.message, false);
    }
    input.value = '';
}

// ── Reseller logo clear ───────────────────────────────────────────────────
function clearResellerLogo() {
    document.getElementById('reseller_logo_path').value = '';
    document.getElementById('reseller_logo_clear').value = '1';
    document.getElementById('reseller-logo-preview').style.display = 'none';
    const img = document.getElementById('reseller-logo-img');
    if (img) img.src = '';
}

// ══════════════════════════════════════════════════════════════════════════
// PAYMENT METHODS MANAGER
// ══════════════════════════════════════════════════════════════════════════
const TYPE_LABELS = {bank:'🏦 Rekening Bank', ewallet:'📱 E-Wallet', qris:'📲 QRIS'};
const TYPE_COLORS = {bank:'primary', ewallet:'secondary', qris:'success'};

let methods = [];
try { methods = JSON.parse(document.getElementById('payment_methods_json').value || '[]'); } catch(e) {}

function escH(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function syncJSON() {
    const el = document.getElementById('payment_methods_json');
    if (el) el.value = JSON.stringify(methods);
}

function render() {
    const list = document.getElementById('methods-list');
    if (!list) return;
    list.innerHTML = '';
    if (!methods.length) {
        list.innerHTML = '<div class="text-muted small fst-italic py-2">Belum ada metode pembayaran. Klik + Bank, + E-Wallet, atau + QRIS di atas.</div>';
        return;
    }
    methods.forEach((m, i) => {
        const isQris = m.type === 'qris';
        const card   = document.createElement('div');
        card.className = 'card card-sm border';
        card.innerHTML = `
          <div class="card-body py-2 px-3">
            <div class="d-flex align-items-start gap-3 flex-wrap">
              <!-- Toggle + Type badge -->
              <div class="d-flex flex-column align-items-center gap-1 pt-1 flex-shrink-0">
                <label class="form-check form-switch mb-0" title="Aktifkan/nonaktifkan">
                  <input class="form-check-input" type="checkbox" ${m.enabled?'checked':''} onchange="toggleMethod(${i},this.checked)">
                </label>
                <span class="badge bg-${TYPE_COLORS[m.type]}-lt" style="font-size:10px;">${TYPE_LABELS[m.type]}</span>
              </div>
              <!-- Fields -->
              <div class="flex-grow-1">
                <div class="row g-2 align-items-end">
                  <div class="col-auto">
                    <label class="form-label mb-1 small">Nama</label>
                    <input type="text" class="form-control form-control-sm" style="min-width:120px;max-width:160px;"
                           value="${escH(m.name)}" placeholder="${isQris?'QRIS':'cth: BRI / GoPay'}"
                           oninput="methods[${i}].name=this.value;syncJSON()">
                  </div>
                  ${!isQris ? `
                  <div class="col-auto">
                    <label class="form-label mb-1 small">No. Rekening / Akun</label>
                    <input type="text" class="form-control form-control-sm" style="min-width:130px;"
                           value="${escH(m.account||'')}" placeholder="08xx / 1234567"
                           oninput="methods[${i}].account=this.value;syncJSON()">
                  </div>
                  <div class="col-auto">
                    <label class="form-label mb-1 small">Nama Pemilik</label>
                    <input type="text" class="form-control form-control-sm" style="min-width:130px;"
                           value="${escH(m.holder||'')}" placeholder="a.n. Nama Anda"
                           oninput="methods[${i}].holder=this.value;syncJSON()">
                  </div>` : `
                  <!-- QRIS image upload -->
                  <div class="col-auto">
                    <label class="form-label mb-1 small">Gambar QR Code</label>
                    <div class="d-flex align-items-center gap-2">
                      ${m.image ? `<img src="${escH(m.image)}" id="qris-img-${i}" style="height:52px;width:52px;object-fit:contain;border:1px solid #dee2e6;border-radius:4px;" onerror="this.style.display='none'">` : `<div id="qris-img-${i}-wrap" class="text-muted small fst-italic">Belum ada gambar</div>`}
                      <label class="btn btn-sm btn-outline-secondary mb-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" class="me-1"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Upload QR
                        <input type="file" accept="image/*" style="display:none;" onchange="uploadQris(this,${i})">
                      </label>
                      ${m.image ? `<button type="button" class="btn btn-sm btn-ghost-danger" onclick="clearQris(${i})" title="Hapus gambar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                      </button>` : ''}
                    </div>
                  </div>`}
                </div>
              </div>
              <!-- Delete -->
              <button type="button" class="btn btn-sm btn-ghost-danger flex-shrink-0" onclick="removeMethod(${i})" title="Hapus">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6m4-6v6"/><path d="M9 6V4h6v2"/></svg>
              </button>
            </div>
          </div>`;
        list.appendChild(card);
    });
    syncJSON();
}

window.addMethod = function(type) {
    methods.push({type, name: type==='qris'?'QRIS':'', account:'', holder:'', image:'', enabled:true});
    render();
};
window.removeMethod = function(i) { methods.splice(i,1); render(); };
window.toggleMethod = function(i,v) { methods[i].enabled=v; syncJSON(); };

window.uploadQris = async function(input, idx) {
    if (!input.files.length) return;
    const file = input.files[0];
    showToast('Mengupload gambar QRIS...', true);
    const fd = new FormData();
    fd.append('image', file);
    fd.append('context', 'qris');
    try {
        const res  = await fetch('api/upload_image.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.error) { showToast('Gagal: '+data.error, false); return; }
        methods[idx].image = data.path;
        syncJSON();
        render();
        showToast('QR Code berhasil diupload', true);
    } catch(e) { showToast('Error: '+e.message, false); }
    input.value = '';
};

window.clearQris = function(idx) {
    methods[idx].image = '';
    syncJSON();
    render();
};

render();

// ── API Keys UI ────────────────────────────────────────────────────────────
function copyNewKey() {
    const k = document.getElementById('new-key-val')?.textContent.trim();
    if (!k) return;
    navigator.clipboard.writeText(k).then(() => {
        const btn = event.target.closest('button');
        const orig = btn.innerHTML;
        btn.textContent = '✓ Tersalin!';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}

document.addEventListener('click', function(e) {
    const vis = e.target.closest('.toggle-key-vis');
    if (vis) {
        const inp = vis.closest('.input-group').querySelector('.key-input');
        inp.type = inp.type === 'password' ? 'text' : 'password';
    }
    const cp = e.target.closest('.copy-key-btn');
    if (cp) {
        navigator.clipboard.writeText(cp.dataset.key).then(() => {
            const orig = cp.innerHTML;
            cp.textContent = '✓';
            setTimeout(() => cp.innerHTML = orig, 1500);
        });
    }
});

<?php if ($new_key_value): ?>
document.addEventListener('DOMContentLoaded', function() {
    const tab = document.querySelector('a[href="#apikeys"]');
    if (tab) tab.click();
});
<?php endif; ?>
</script>
</body>
</html>

