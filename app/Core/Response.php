<?php

namespace App\Core;

/**
 * Standardized Response Helper
 */
class Response
{
    /**
     * Return JSON response
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $key => $val) {
            header("{$key}: {$val}");
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Standard success API response
     */
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200, array $extra = []): never
    {
        $payload = array_merge([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $extra);

        self::json($payload, $statusCode);
    }

    /**
     * Standard error API response
     */
    public static function error(string $message = 'An error occurred', int $statusCode = 400, mixed $data = null, array $extra = []): never
    {
        $payload = array_merge([
            'success' => false,
            'message' => $message,
            'data'    => $data,
        ], $extra);

        self::json($payload, $statusCode);
    }

    /**
     * Redirect to URL
     */
    public static function redirect(string $url, ?string $flashMessage = null, ?string $flashType = 'success'): never
    {
        if ($flashMessage !== null) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash_msg'] = "{$flashType}:{$flashMessage}";
        }

        // Prepend BASE_URL if internal path starting with /
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
            if ($baseUrl !== '') {
                if ($url === '/' || $url === '') {
                    $url = $baseUrl . '/';
                } elseif (!str_starts_with($url, $baseUrl . '/') && $url !== $baseUrl) {
                    $url = $baseUrl . '/' . ltrim($url, '/');
                }
            }
        }

        header("Location: {$url}");
        exit;
    }
}

