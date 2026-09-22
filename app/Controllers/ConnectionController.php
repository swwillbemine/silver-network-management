<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\Response;
use App\Core\View;
use App\Services\ConnectionService;
use App\Repositories\NodeRepository;

class ConnectionController
{
    private ConnectionService $connService;
    private NodeRepository $nodeRepo;

    public function __construct(
        ?ConnectionService $connService = null,
        ?NodeRepository $nodeRepo = null
    ) {
        $this->connService = $connService ?: new ConnectionService();
        $this->nodeRepo = $nodeRepo ?: new NodeRepository();
    }

    public function index(): void
    {
        Middleware::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'create') {
                    $this->connService->create([
                        'name' => $_POST['name'] ?? '',
                        'start_node_id' => (int)($_POST['start_node_id'] ?? 0),
                        'end_node_id' => (int)($_POST['end_node_id'] ?? 0),
                        'type' => $_POST['type'] ?? '',
                        'length_estimated' => !empty($_POST['length_estimated']) ? (int)$_POST['length_estimated'] : 0,
                        'core_color' => $_POST['core_color'] ?? '',
                        'loss_db' => !empty($_POST['loss_db']) ? (float)$_POST['loss_db'] : null,
                        'wireless_frequency' => $_POST['wireless_frequency'] ?? '',
                        'wireless_ssid' => $_POST['wireless_ssid'] ?? '',
                        'wireless_password' => $_POST['wireless_password'] ?? '',
                        'wireless_ip_radio_ap' => $_POST['wireless_ip_radio_ap'] ?? '',
                        'wireless_ip_radio_station' => $_POST['wireless_ip_radio_station'] ?? '',
                        'status' => $_POST['status'] ?? 'connected',
                    ]);
                    $_SESSION['flash_msg'] = 'success:Jalur berhasil ditambahkan.';
                } elseif ($action === 'update') {
                    $id = (int)($_POST['id'] ?? 0);
                    $this->connService->update($id, [
                        'name' => $_POST['name'] ?? '',
                        'start_node_id' => (int)($_POST['start_node_id'] ?? 0),
                        'end_node_id' => (int)($_POST['end_node_id'] ?? 0),
                        'type' => $_POST['type'] ?? '',
                        'length_estimated' => !empty($_POST['length_estimated']) ? (int)$_POST['length_estimated'] : 0,
                        'core_color' => $_POST['core_color'] ?? '',
                        'loss_db' => !empty($_POST['loss_db']) ? (float)$_POST['loss_db'] : null,
                        'wireless_frequency' => $_POST['wireless_frequency'] ?? '',
                        'wireless_ssid' => $_POST['wireless_ssid'] ?? '',
                        'wireless_password' => $_POST['wireless_password'] ?? '',
                        'wireless_ip_radio_ap' => $_POST['wireless_ip_radio_ap'] ?? '',
                        'wireless_ip_radio_station' => $_POST['wireless_ip_radio_station'] ?? '',
                        'status' => $_POST['status'] ?? 'connected',
                    ]);
                    $_SESSION['flash_msg'] = 'success:Jalur berhasil diperbarui.';
                } elseif ($action === 'delete') {
                    $id = (int)($_POST['id'] ?? 0);
                    $this->connService->delete($id);
                    $_SESSION['flash_msg'] = 'success:Jalur berhasil dihapus.';
                }
            } catch (\Throwable $e) {
                $_SESSION['flash_msg'] = 'danger:Terjadi kesalahan database: ' . $e->getMessage();
            }
            Response::redirect('/connections');
            return;
        }

        $msg = $_SESSION['flash_msg'] ?? ($_GET['msg'] ?? '');
        unset($_SESSION['flash_msg']);

        $connections = $this->connService->getAll();
        $nodes = $this->nodeRepo->all();
        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
        $status_labels = ['connected' => 'Terhubung', 'broken' => 'Putus', 'maintenance' => 'Maintenance'];

        View::render('connections/index', [
            'connections' => $connections,
            'nodes' => $nodes,
            'msg' => $msg,
            'msg_type' => $msg_type,
            'msg_text' => $msg_text,
            'status_labels' => $status_labels,
        ]);
    }
}

