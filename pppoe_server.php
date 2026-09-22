<?php
// pppoe_server.php — Backward Compatibility Bridge
require_once __DIR__ . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    (new \App\Controllers\RouterController())->pppoeServer();
    exit;
}

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
\App\Core\Response::redirect('/pppoe-server' . $query);