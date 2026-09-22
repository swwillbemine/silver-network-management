<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\Database;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Services\UserService;
use PDO;

class UserController
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private UserService $userService;

    public function __construct(
        ?UserRepository $userRepo = null,
        ?UserService $userService = null,
        ?PDO $pdo = null
    ) {
        $this->pdo         = $pdo ?: Database::getConnection();
        $this->userRepo    = $userRepo ?: new UserRepository($this->pdo);
        $this->userService = $userService ?: new UserService($this->userRepo, $this->pdo);
    }

    public function index(): void
    {
        Middleware::requireRole(['superadmin', 'admin']);

        $current_user_id   = $_SESSION['user_id'] ?? 0;
        $current_user_role = $_SESSION['user_role'] ?? '';
        $is_superadmin     = $current_user_role === 'superadmin';

        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'create') {
                $result = $this->userService->handleCreate($_POST, $is_superadmin);
                $msg    = $result['message'];
            } elseif ($action === 'update') {
                $targetId = (int)($_POST['id'] ?? 0);
                $result   = $this->userService->handleUpdate($targetId, $_POST, $is_superadmin, $current_user_id);
                $msg      = $result['message'];
            } elseif ($action === 'reset_password') {
                $targetId = (int)($_POST['id'] ?? 0);
                $result   = $this->userService->handleResetPassword($targetId, $_POST, $is_superadmin, $current_user_id);
                $msg      = $result['message'];
            } elseif ($action === 'toggle_active') {
                $targetId = (int)($_POST['id'] ?? 0);
                $result   = $this->userService->handleToggleActive($targetId, $is_superadmin, $current_user_id);
                $msg      = $result['message'];
            } elseif ($action === 'delete') {
                $targetId = (int)($_POST['id'] ?? 0);
                $result   = $this->userService->handleDelete($targetId, $is_superadmin, $current_user_id);
                $msg      = $result['message'];
            }
        }

        $users = $this->userService->getAllUsers();

        [$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

        $role_labels = ['superadmin' => 'Super Admin', 'admin' => 'Admin', 'teknisi' => 'Teknisi', 'kasir' => 'Kasir'];
        $role_colors = ['superadmin' => 'purple', 'admin' => 'blue', 'teknisi' => 'cyan', 'kasir' => 'orange'];

        View::render('users/index', [
            'users'             => $users,
            'current_user_id'   => $current_user_id,
            'current_user_role' => $current_user_role,
            'is_superadmin'     => $is_superadmin,
            'role_labels'       => $role_labels,
            'role_colors'       => $role_colors,
            'msg'               => $msg,
            'msg_type'          => $msg_type,
            'msg_text'          => $msg_text,
        ]);
    }
}
