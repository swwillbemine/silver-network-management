<?php
// customers.php — Backward Compatibility Bridge
require_once __DIR__ . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    (new \App\Controllers\CustomerController())->index();
    exit;
}

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
\App\Core\Response::redirect('/customers' . $query);