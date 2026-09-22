<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Repositories\BillingRepository;
use App\Repositories\SettingRepository;
use App\Services\BillingService;
use PDO;

class BillingController
{
    private PDO $pdo;
    private BillingRepository $billingRepo;
    private BillingService $billingService;
    private SettingRepository $settingRepo;

    public function __construct(
        ?BillingRepository $billingRepo = null,
        ?BillingService $billingService = null,
        ?SettingRepository $settingRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?: Database::getConnection();
        $this->billingRepo = $billingRepo ?: new BillingRepository($this->pdo);
        $this->billingService = $billingService ?: new BillingService($this->billingRepo, null, $this->pdo);
        $this->settingRepo = $settingRepo ?: new SettingRepository($this->pdo);
    }

    public function index(): void
    {
        Middleware::requireLogin();

        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'generate') {
                $period = $_POST['period'] ?? date('Y-m');
                $cnt = $this->billingService->generateMonthlyBills($period);
                $msg = "success:Berhasil membuat $cnt tagihan untuk periode $period.";
            } elseif ($action === 'pay') {
                $billingId = (int)($_POST['billing_id'] ?? 0);
                $amountPaid = (float)($_POST['amount_paid'] ?? 0);
                $method = $_POST['method'] ?? 'cash';
                $notes = $_POST['notes'] ?? '';
                $userId = (int)($_SESSION['user_id'] ?? 0);

                $this->billingService->recordPayment($billingId, $amountPaid, $method, $notes, $userId);
                $msg = 'success:Pembayaran berhasil dicatat.';
            } elseif ($action === 'cancel') {
                $billingId = (int)($_POST['billing_id'] ?? 0);
                $this->billingService->cancelBill($billingId);
                $msg = 'success:Tagihan dibatalkan.';
            }
        }

        $period_filter = $_GET['period'] ?? date('Y-m');
        $status_filter = $_GET['status'] ?? '';

        $filters = ['period' => $period_filter];
        if ($status_filter) {
            $filters['status'] = $status_filter;
        }

        // ── PAGINATION ───────────────────────────────────────────────────────────
        $per_page = 25;
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $offset   = ($page - 1) * $per_page;

        $total_rows  = $this->billingRepo->count($filters);
        $total_pages = max(1, (int)ceil($total_rows / $per_page));
        $page        = min($page, $total_pages);
        $offset      = ($page - 1) * $per_page;

        $billings = $this->billingRepo->all($filters, $per_page, $offset);
        $stats = $this->billingRepo->getSummary($period_filter);

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        // Settings
        global $_settings;
        $_s = $_settings ?? $this->settingRepo->getAll();

        $isp_name      = $_s['isp_name']      ?? 'ISP';
        $isp_address   = $_s['address']       ?? '';
        $isp_phone     = $_s['admin_phone']   ?? '';
        $isp_logo      = $_s['isp_logo']      ?? '';
        $reseller_name = $_s['reseller_name'] ?? '';
        $reseller_logo = $_s['reseller_logo'] ?? '';
        $pay_methods   = json_decode($_s['payment_methods'] ?? '[]', true) ?: [];

        if (empty($pay_methods) && !empty($_s['billing_bank_name'])) {
            $pay_methods[] = [
                'type'    => 'bank',
                'name'    => $_s['billing_bank_name'],
                'account' => $_s['billing_bank_account'] ?? '',
                'holder'  => $_s['billing_bank_holder'] ?? '',
                'enabled' => true,
            ];
        }
        $enabled_methods = array_values(array_filter($pay_methods, fn($m) => !empty($m['enabled'])));

        $status_labels = ['unpaid' => 'Belum Bayar', 'paid' => 'Lunas', 'cancelled' => 'Dibatalkan'];
        $status_colors = ['unpaid' => 'warning', 'paid' => 'success', 'cancelled' => 'secondary'];

        View::render('billing/index', [
            'billings'        => $billings,
            'stats'           => $stats,
            'period_filter'   => $period_filter,
            'status_filter'   => $status_filter,
            'total_rows'      => $total_rows,
            'total_pages'     => $total_pages,
            'page'            => $page,
            'per_page'        => $per_page,
            'msg'             => $msg,
            'msg_type'        => $msg_type,
            'msg_text'        => $msg_text,
            'isp_name'        => $isp_name,
            'isp_address'     => $isp_address,
            'isp_phone'       => $isp_phone,
            'isp_logo'        => $isp_logo,
            'reseller_name'   => $reseller_name,
            'reseller_logo'   => $reseller_logo,
            'enabled_methods' => $enabled_methods,
            'status_labels'   => $status_labels,
            'status_colors'   => $status_colors,
        ]);
    }
}

