<?php
// auth/logout.php — Backward Compatibility Bridge
require_once __DIR__ . '/../config/bootstrap.php';

(new \App\Controllers\AuthController())->logout();