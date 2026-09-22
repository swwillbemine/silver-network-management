<?php

use App\Core\Router;
use App\Core\Response;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\CustomerController;
use App\Controllers\PackageController;
use App\Controllers\NodeController;
use App\Controllers\PopController;
use App\Controllers\ConnectionController;
use App\Controllers\RouterController;
use App\Controllers\BillingController;
use App\Controllers\SettingController;
use App\Controllers\UserController;
use App\Controllers\ImportController;

/**
 * Web Route Definitions
 * Silver Network Management
 */

// Dashboard
Router::get('/', [DashboardController::class, 'index']);

// Authentication
Router::get('/login', [AuthController::class, 'login']);
Router::post('/login', [AuthController::class, 'authenticate']);
Router::get('/logout', [AuthController::class, 'logout']);

// Customers
Router::get('/customers', [CustomerController::class, 'index']);
Router::post('/customers', [CustomerController::class, 'index']);
Router::get('/customers/create', [CustomerController::class, 'form']);
Router::post('/customers/create', [CustomerController::class, 'form']);
Router::get('/customers/{id}/edit', [CustomerController::class, 'form']);
Router::post('/customers/{id}/edit', [CustomerController::class, 'form']);
Router::get('/customers/{id}', [CustomerController::class, 'detail']);

// Packages
Router::get('/packages', [PackageController::class, 'index']);
Router::post('/packages', [PackageController::class, 'index']);
Router::get('/packages/create', [PackageController::class, 'form']);
Router::post('/packages/create', [PackageController::class, 'form']);
Router::get('/packages/import', [PackageController::class, 'import']);
Router::post('/packages/import', [PackageController::class, 'import']);
Router::get('/packages/{id}/edit', [PackageController::class, 'form']);
Router::post('/packages/{id}/edit', [PackageController::class, 'form']);

// Infrastructure (Nodes, PoPs, Connections)
Router::get('/nodes', [NodeController::class, 'index']);
Router::post('/nodes', [NodeController::class, 'index']);

Router::get('/pops', [PopController::class, 'index']);
Router::post('/pops', [PopController::class, 'index']);

Router::get('/connections', [ConnectionController::class, 'index']);
Router::post('/connections', [ConnectionController::class, 'index']);

// MikroTik Routers & Network
Router::get('/routers', [RouterController::class, 'index']);
Router::post('/routers', [RouterController::class, 'index']);

Router::get('/hotspot', [RouterController::class, 'hotspot']);
Router::post('/hotspot', [RouterController::class, 'hotspot']);

Router::get('/ip-pool', [RouterController::class, 'ipPool']);
Router::post('/ip-pool', [RouterController::class, 'ipPool']);

Router::get('/pppoe-server', [RouterController::class, 'pppoeServer']);
Router::post('/pppoe-server', [RouterController::class, 'pppoeServer']);

// Billing
Router::get('/billing', [BillingController::class, 'index']);
Router::post('/billing', [BillingController::class, 'index']);

// Users & Settings
Router::get('/users', [UserController::class, 'index']);
Router::post('/users', [UserController::class, 'index']);

Router::get('/settings', [SettingController::class, 'index']);
Router::post('/settings', [SettingController::class, 'index']);

// Import
Router::get('/import/pppoe', [ImportController::class, 'pppoe']);
Router::post('/import/pppoe', [ImportController::class, 'pppoe']);

// Router Proxy
Router::any('/router-proxy/{cid}/{token}', function($cid, $token) {
    require_once BASE_PATH . '/router_proxy.php';
});
Router::any('/router-proxy/{cid}/{token}/{subpath:*}', function($cid, $token) {
    require_once BASE_PATH . '/router_proxy.php';
});
