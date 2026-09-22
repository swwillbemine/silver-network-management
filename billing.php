<?php
// billing.php — Backward Compatibility Bridge
require_once __DIR__ . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    (new \App\Controllers\BillingController())->index();
    exit;
}

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
\App\Core\Response::redirect('/billing' . $query);