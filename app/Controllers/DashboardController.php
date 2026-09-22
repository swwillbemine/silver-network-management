<?php

namespace App\Controllers;

use App\Auth\Middleware;
use App\Core\View;
use App\Services\DashboardService;

class DashboardController
{
    private ?DashboardService $dashboardService;

    public function __construct(?DashboardService $dashboardService = null)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index(): void
    {
        Middleware::requireLogin();

        View::render('dashboard/index');
    }
}

