<?php
/**
 * Front Controller
 * Silver Network Management
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/routes/web.php';

\App\Core\Router::dispatch();

