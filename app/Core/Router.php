<?php

namespace App\Core;

use ReflectionMethod;
use ReflectionFunction;
use Closure;

/**
 * Native Lightweight Router for PHP Native
 */
class Router
{
    private static array $routes = [];
    private static ?string $currentRoute = null;

    /**
     * Register a GET route
     */
    public static function get(string $path, callable|string|array $handler): void
    {
        self::add('GET', $path, $handler);
    }

    /**
     * Register a POST route
     */
    public static function post(string $path, callable|string|array $handler): void
    {
        self::add('POST', $path, $handler);
    }

    /**
     * Register a PUT route
     */
    public static function put(string $path, callable|string|array $handler): void
    {
        self::add('PUT', $path, $handler);
    }

    /**
     * Register a DELETE route
     */
    public static function delete(string $path, callable|string|array $handler): void
    {
        self::add('DELETE', $path, $handler);
    }

    /**
     * Register a route responding to both GET and POST
     */
    public static function match(array $methods, string $path, callable|string|array $handler): void
    {
        foreach ($methods as $method) {
            self::add(strtoupper($method), $path, $handler);
        }
    }

    /**
     * Register a route responding to any HTTP method
     */
    public static function any(string $path, callable|string|array $handler): void
    {
        self::match(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $path, $handler);
    }

    /**
     * Internal method to store routes
     */
    public static function add(string $method, string $path, callable|string|array $handler): void
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        self::$routes[strtoupper($method)][$path] = $handler;
    }

    /**
     * Get all registered routes
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Reset all registered routes (useful for testing)
     */
    public static function clear(): void
    {
        self::$routes = [];
        self::$currentRoute = null;
    }

    /**
     * Get currently active routed path
     */
    public static function currentRoute(): string
    {
        if (self::$currentRoute !== null) {
            return self::$currentRoute;
        }

        return self::resolveUri();
    }

    /**
     * Check if current route matches given pattern(s)
     * Useful for navigation active states
     *
     * @param string|array $patterns e.g. 'customers', 'customers/*', 'customers.php'
     */
    public static function is(string|array $patterns): bool
    {
        $current = trim(self::currentRoute(), '/');
        if ($current === '') {
            $current = '/';
        }

        $patterns = (array)$patterns;
        foreach ($patterns as $pattern) {
            // Strip .php extension if checked against legacy names
            $p = preg_replace('/\.php$/', '', $pattern);
            $p = trim($p, '/');
            if ($p === '' || $p === 'index') {
                $p = '/';
            }

            if ($p === $current) {
                return true;
            }

            // Handle wildcard (e.g. 'customers*')
            if (str_ends_with($p, '*')) {
                $prefix = rtrim($p, '*');
                if ($prefix === '' || str_starts_with($current, $prefix)) {
                    return true;
                }
            }

            // Sub-path match: 'customers' matches 'customers/123'
            if ($p !== '/' && str_starts_with($current, $p . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate application URL relative to BASE_URL
     */
    public static function url(string $path = ''): string
    {
        $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $path = '/' . ltrim($path, '/');
        if ($path === '/') {
            return $baseUrl !== '' ? $baseUrl : '/';
        }
        return $baseUrl . $path;
    }

    /**
     * Resolve request URI path relative to BASE_URL
     */
    public static function resolveUri(?string $uri = null): string
    {
        if ($uri === null) {
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
        }

        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Strip BASE_URL prefix if present
        if (defined('BASE_URL') && BASE_URL !== '') {
            $baseUrl = rtrim(BASE_URL, '/');
            if ($baseUrl !== '' && str_starts_with($path, $baseUrl)) {
                $path = substr($path, strlen($baseUrl));
            }
        }

        // Strip index.php or public/index.php if present in URI
        $path = preg_replace('#^/(?:public/)?index\.php#', '', $path);

        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    /**
     * Dispatch the current or provided request
     */
    public static function dispatch(?string $uri = null, ?string $method = null): mixed
    {
        $uri = self::resolveUri($uri);
        self::$currentRoute = $uri;

        $method = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        // Check for _method override in POST forms (e.g. PUT / DELETE)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper(trim($_POST['_method']));
            if (in_array($override, ['PUT', 'DELETE', 'PATCH'])) {
                $method = $override;
            }
        }

        // 1. Direct exact match
        if (isset(self::$routes[$method][$uri])) {
            return self::executeHandler(self::$routes[$method][$uri], []);
        }

        // 2. Dynamic regex pattern match
        $allowedMethods = [];
        foreach (self::$routes as $httpMethod => $routes) {
            foreach ($routes as $routePattern => $handler) {
                // Convert {param:*} to named wildcard (?P<param>.*)
                // Convert {param} to named group (?P<param>[^/]+)
                $patternRegex = preg_replace('#\{([a-zA-Z0-9_]+):\*\}#', '(?P<$1>.*)', $routePattern);
                $patternRegex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $patternRegex);
                $patternRegex = '#^' . $patternRegex . '$#';

                if (preg_match($patternRegex, $uri, $matches)) {
                    if ($httpMethod === $method) {
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                        // Populate $_GET with route params for seamless backward compatibility
                        foreach ($params as $key => $val) {
                            if (!isset($_GET[$key])) {
                                $_GET[$key] = $val;
                            }
                        }

                        return self::executeHandler($handler, $params);
                    }
                    $allowedMethods[] = $httpMethod;
                }
            }
        }

        // 3. Method Not Allowed (405)
        if (!empty($allowedMethods)) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            if (self::isJsonRequest()) {
                Response::json(['error' => 'Method Not Allowed', 'allowed' => array_unique($allowedMethods)], 405);
            }
            echo "<h1>405 Method Not Allowed</h1>";
            exit;
        }

        // 4. Not Found (404)
        return self::handleNotFound($uri);
    }

    /**
     * Execute route handler (Closure, [Class, method], or 'Class@method')
     */
    private static function executeHandler(callable|string|array $handler, array $params = []): mixed
    {
        if ($handler instanceof Closure || (is_object($handler) && is_callable($handler))) {
            return self::callCallable($handler, $params);
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = is_object($class) ? $class : self::resolveClass($class);
            return self::callMethod($instance, $method, $params);
        }

        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                [$class, $method] = explode('@', $handler, 2);
                $instance = self::resolveClass($class);
                return self::callMethod($instance, $method, $params);
            }

            if (is_callable($handler)) {
                return self::callCallable($handler, $params);
            }
        }

        throw new \RuntimeException("Invalid route handler specified.");
    }

    /**
     * Instantiate controller class
     */
    private static function resolveClass(string $class): object
    {
        if (!str_contains($class, '\\')) {
            $class = 'App\\Controllers\\' . $class;
        }

        if (!class_exists($class)) {
            throw new \RuntimeException("Controller class not found: {$class}");
        }

        return new $class();
    }

    /**
     * Call controller method with reflection to safely handle parameter counts
     */
    private static function callMethod(object $instance, string $method, array $params): mixed
    {
        if (!method_exists($instance, $method)) {
            throw new \RuntimeException("Method '{$method}' not found on " . get_class($instance));
        }

        $ref = new ReflectionMethod($instance, $method);
        if ($ref->getNumberOfParameters() === 0) {
            return $instance->$method();
        }

        $args = self::resolveMethodArgs($ref, $params);
        return $instance->$method(...$args);
    }

    /**
     * Call callable with reflection
     */
    private static function callCallable(callable $callable, array $params): mixed
    {
        $ref = new ReflectionFunction(Closure::fromCallable($callable));
        if ($ref->getNumberOfParameters() === 0) {
            return $callable();
        }

        $args = self::resolveMethodArgs($ref, $params);
        return $callable(...$args);
    }

    /**
     * Resolve method argument values by name or position
     */
    private static function resolveMethodArgs(ReflectionMethod|ReflectionFunction $ref, array $params): array
    {
        $args = [];
        $values = array_values($params);
        $i = 0;

        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            } elseif (isset($values[$i])) {
                $args[] = $values[$i];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
            $i++;
        }

        return $args;
    }

    /**
     * Check if request expects JSON
     */
    private static function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || str_contains($uri, '/api/');
    }

    /**
     * Render clean 404 page
     */
    private static function handleNotFound(string $uri): void
    {
        http_response_code(404);

        if (self::isJsonRequest()) {
            Response::json(['error' => 'Not Found', 'path' => $uri, 'status' => 404], 404);
            return;
        }

        $homeUrl = defined('BASE_URL') ? BASE_URL . '/' : '/';
        ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title>404 - Halaman Tidak Ditemukan</title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/css/tabler.min.css" rel="stylesheet"/>
</head>
<body class="border-top-wide border-primary d-flex flex-column">
    <div class="page page-center">
      <div class="container-tight py-4">
        <div class="empty">
          <div class="empty-header">404</div>
          <p class="empty-title">Oops… Halaman tidak ditemukan</p>
          <p class="empty-subtitle text-muted">
            Halaman yang Anda minta <code><?= htmlspecialchars($uri) ?></code> tidak tersedia di server.
          </p>
          <div class="empty-action">
            <a href="<?= htmlspecialchars($homeUrl) ?>" class="btn btn-primary">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l-2 0l9 -9l9 9l-2 0" /><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" /><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" /></svg>
              Kembali ke Dashboard
            </a>
          </div>
        </div>
      </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}
