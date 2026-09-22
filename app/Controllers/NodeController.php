<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\Response;
use App\Core\View;
use App\Services\NodeService;

class NodeController
{
    private NodeService $nodeService;

    public function __construct(?NodeService $nodeService = null)
    {
        $this->nodeService = $nodeService ?: new NodeService();
    }

    public function index(): void
    {
        Middleware::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'create') {
                    $this->nodeService->create([
                        'name' => $_POST['name'] ?? '',
                        'address' => $_POST['address'] ?? '',
                        'latitude' => !empty($_POST['latitude']) ? $_POST['latitude'] : null,
                        'longitude' => !empty($_POST['longitude']) ? $_POST['longitude'] : null,
                        'owner_name' => $_POST['owner_name'] ?? '',
                        'owner_contact' => $_POST['owner_contact'] ?? '',
                        'description' => $_POST['description'] ?? '',
                    ]);
                    $_SESSION['flash_msg'] = 'success:Node berhasil ditambahkan.';
                } elseif ($action === 'update') {
                    $id = (int)($_POST['id'] ?? 0);
                    $this->nodeService->update($id, [
                        'name' => $_POST['name'] ?? '',
                        'address' => $_POST['address'] ?? '',
                        'latitude' => !empty($_POST['latitude']) ? $_POST['latitude'] : null,
                        'longitude' => !empty($_POST['longitude']) ? $_POST['longitude'] : null,
                        'owner_name' => $_POST['owner_name'] ?? '',
                        'owner_contact' => $_POST['owner_contact'] ?? '',
                        'description' => $_POST['description'] ?? '',
                    ]);
                    $_SESSION['flash_msg'] = 'success:Node berhasil diperbarui.';
                } elseif ($action === 'delete') {
                    $id = (int)($_POST['id'] ?? 0);
                    $this->nodeService->delete($id);
                    $_SESSION['flash_msg'] = 'success:Node berhasil dihapus.';
                }
            } catch (\Throwable $e) {
                $_SESSION['flash_msg'] = 'danger:Terjadi kesalahan database: ' . $e->getMessage();
            }
            Response::redirect('/nodes');
            return;
        }

        $msg = $_SESSION['flash_msg'] ?? ($_GET['msg'] ?? '');
        unset($_SESSION['flash_msg']);

        $nodes = $this->nodeService->getAll();
        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        View::render('nodes/index', [
            'nodes' => $nodes,
            'msg' => $msg,
            'msg_type' => $msg_type,
            'msg_text' => $msg_text,
        ]);
    }
}

