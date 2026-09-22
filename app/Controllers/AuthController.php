<?php

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Helpers\Security;
use Exception;

/**
 * Authentication Controller
 * Handles login, authentication verification, and logout
 */
class AuthController
{
    /**
     * Show login form (GET /login)
     */
    public function login(): void
    {
        if (Auth::check()) {
            Response::redirect('/');
        }

        $token = Security::generateCsrfToken();
        View::render('auth/login', [
            'token' => $token,
        ]);
    }

    /**
     * Authenticate credentials (POST /login)
     */
    public function authenticate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::redirect('/login');
        }

        // CSRF check
        if (!Security::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            Response::redirect('/login?error=csrf');
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            Response::redirect('/login?error=1');
        }

        if (Auth::attempt($username, $password)) {
            Response::redirect('/');
        }

        Response::redirect('/login?error=1');
    }

    /**
     * Terminate session (GET /logout)
     */
    public function logout(): void
    {
        if (Auth::check()) {
            try {
                $pdo = Database::getConnection();
                $pdo->prepare("UPDATE users SET session_token = NULL WHERE id = ?")
                    ->execute([Auth::id()]);
            } catch (Exception $e) {
                // Ignore DB error during logout
            }
        }

        Auth::logout();
        Response::redirect('/login');
    }
}

