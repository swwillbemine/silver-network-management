<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Repositories\SettingRepository;
use PDO;

class SettingController
{
    private PDO $pdo;
    private SettingRepository $settingRepo;

    public function __construct(?SettingRepository $settingRepo = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
        $this->settingRepo = $settingRepo ?: new SettingRepository($this->pdo);
    }

    public function index(): void
    {
        Middleware::requireLogin();

        $msg = '';

        $save_setting = function (string $key, string $value): void {
            $this->settingRepo->set($key, $value);
        };

        // ── POST handlers ─────────────────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'save_general') {
                $save_setting('isp_name',    $_POST['isp_name'] ?? '');
                $save_setting('app_name',    $_POST['app_name'] ?? '');
                $save_setting('admin_phone', $_POST['admin_phone'] ?? '');
                $save_setting('admin_email', $_POST['admin_email'] ?? '');
                $save_setting('address',     $_POST['address'] ?? '');
                $save_setting('tax_number',  $_POST['tax_number'] ?? '');

                $new_logo = trim($_POST['isp_logo_path'] ?? '');
                if ($new_logo !== '') {
                    $save_setting('isp_logo', $new_logo);
                }
                $save_setting('timezone', trim($_POST['timezone'] ?? 'Asia/Jakarta'));
                $msg = 'success:Pengaturan umum berhasil disimpan.';

            } elseif ($action === 'save_billing') {
                $save_setting('billing_due_days',       $_POST['billing_due_days'] ?? '10');
                $save_setting('billing_isolation_days', $_POST['billing_isolation_days'] ?? '5');

                $methods = json_decode($_POST['payment_methods'] ?? '[]', true);
                if (!is_array($methods)) $methods = [];
                $cleaned = [];
                foreach ($methods as $m) {
                    if (empty($m['name'])) continue;
                    $cleaned[] = [
                        'type'    => in_array($m['type'] ?? '', ['bank', 'ewallet', 'qris']) ? $m['type'] : 'bank',
                        'name'    => trim($m['name']),
                        'account' => trim($m['account'] ?? ''),
                        'holder'  => trim($m['holder']  ?? ''),
                        'image'   => trim($m['image']   ?? ''),
                        'enabled' => !empty($m['enabled']),
                    ];
                }
                $save_setting('payment_methods', json_encode($cleaned));

                $save_setting('reseller_name', trim($_POST['reseller_name'] ?? ''));
                $new_rl = trim($_POST['reseller_logo_path'] ?? '');
                if ($new_rl !== '') {
                    $save_setting('reseller_logo', $new_rl);
                }
                if (isset($_POST['reseller_logo_clear'])) {
                    $save_setting('reseller_logo', '');
                }
                $msg = 'success:Pengaturan tagihan berhasil disimpan.';

            } elseif ($action === 'save_notification') {
                $save_setting('telegram_bot_token',    $_POST['telegram_bot_token'] ?? '');
                $save_setting('telegram_chat_id',      $_POST['telegram_chat_id'] ?? '');
                $save_setting('notify_new_payment',    isset($_POST['notify_new_payment'])    ? '1' : '0');
                $save_setting('notify_offline_router', isset($_POST['notify_offline_router']) ? '1' : '0');
                $msg = 'success:Pengaturan notifikasi disimpan.';

            } elseif ($action === 'create_api_key') {
                $name  = trim($_POST['key_name'] ?? '');
                $perms = implode(',', array_intersect($_POST['permissions'] ?? [], ['read_customers', 'read_live']));
                if (!$name) {
                    $msg = 'danger:Nama API key wajib diisi.';
                } elseif (!$perms) {
                    $msg = 'danger:Pilih minimal satu permission.';
                } else {
                    $new_key = bin2hex(random_bytes(32));
                    $this->settingRepo->createApiKey([
                        'user_id'     => $_SESSION['user_id'],
                        'name'        => $name,
                        'api_key'     => $new_key,
                        'permissions' => $perms,
                    ]);
                    $msg = 'success:API key berhasil dibuat.|' . $new_key;
                }

            } elseif ($action === 'toggle_api_key') {
                $kid = (int)($_POST['key_id'] ?? 0);
                $this->settingRepo->toggleApiKey($kid, (int)$_SESSION['user_id']);
                $msg = 'success:Status API key diperbarui.';

            } elseif ($action === 'delete_api_key') {
                $kid = (int)($_POST['key_id'] ?? 0);
                $this->settingRepo->deleteApiKey($kid, (int)$_SESSION['user_id']);
                $msg = 'success:API key dihapus.';
            }
        }

        $new_key_value = '';
        if (str_starts_with($msg, 'success:') && str_contains($msg, '|')) {
            [$msg, $new_key_value] = explode('|', $msg, 2);
        }

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        $api_keys = $this->settingRepo->getApiKeysByUser((int)$_SESSION['user_id']);

        $cur_isp_logo      = $this->settingRepo->get('isp_logo', '');
        $cur_reseller_logo = $this->settingRepo->get('reseller_logo', '');
        $cur_pay_methods   = $this->settingRepo->get('payment_methods', '[]');

        View::render('settings/index', [
            'msg'               => $msg,
            'msg_type'          => $msg_type,
            'msg_text'          => $msg_text,
            'new_key_value'     => $new_key_value,
            'api_keys'          => $api_keys,
            'cur_isp_logo'      => $cur_isp_logo,
            'cur_reseller_logo' => $cur_reseller_logo,
            'cur_pay_methods'   => $cur_pay_methods,
            'settingRepo'       => $this->settingRepo,
            'pdo'               => $this->pdo,
        ]);
    }
}

