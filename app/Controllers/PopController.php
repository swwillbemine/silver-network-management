<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\Response;
use App\Core\View;
use App\Services\PopService;
use App\Repositories\NodeRepository;

class PopController
{
    private PopService $popService;
    private NodeRepository $nodeRepo;

    public function __construct(?PopService $popService = null, ?NodeRepository $nodeRepo = null)
    {
        $this->popService = $popService ?: new PopService();
        $this->nodeRepo = $nodeRepo ?: new NodeRepository();
    }

    public function index(): void
    {
        Middleware::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'create') {
                    $this->popService->create([
                        'node_id' => (int)($_POST['node_id'] ?? 0),
                        'name' => $_POST['name'] ?? '',
                        'backup_power' => $_POST['backup_power'] ?? 'none',
                        'battery_capacity' => $_POST['battery_capacity'] ?? '',
                        'installation_date' => !empty($_POST['installation_date']) ? $_POST['installation_date'] : null,
                    ]);
                    $_SESSION['flash_msg'] = 'success:POP berhasil ditambahkan.';
                } elseif ($action === 'update') {
                    $id = (int)($_POST['id'] ?? 0);
                    $this->popService->update($id, [
                        'node_id' => (int)($_POST['node_id'] ?? 0),
                        'name' => $_POST['name'] ?? '',
                        'backup_power' => $_POST['backup_power'] ?? 'none',
                        'battery_capacity' => $_POST['battery_capacity'] ?? '',
                        'installation_date' => !empty($_POST['installation_date']) ? $_POST['installation_date'] : null,
                    ]);
                    $_SESSION['flash_msg'] = 'success:POP berhasil diperbarui.';
                } elseif ($action === 'delete') {
                    $id = (int)($_POST['id'] ?? 0);
                    $this->popService->delete($id);
                    $_SESSION['flash_msg'] = 'success:POP berhasil dihapus.';
                }
            } catch (\Throwable $e) {
                $_SESSION['flash_msg'] = 'danger:Terjadi kesalahan database: ' . $e->getMessage();
            }
            Response::redirect('/pops');
            return;
        }

        $msg = $_SESSION['flash_msg'] ?? ($_GET['msg'] ?? '');
        unset($_SESSION['flash_msg']);

        $pops = $this->popService->getAll();
        $nodes = $this->nodeRepo->all();
        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
        $backup_labels = ['none' => 'Tidak Ada', 'ups' => 'UPS', 'genset' => 'Genset', 'solar' => 'Solar Panel'];

        View::render('pops/index', [
            'pops' => $pops,
            'nodes' => $nodes,
            'msg' => $msg,
            'msg_type' => $msg_type,
            'msg_text' => $msg_text,
            'backup_labels' => $backup_labels,
        ]);
    }
}

