<?php
// mikrotik/connection.php

require_once __DIR__ . '/../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Query;

/**
 * Membuat koneksi ke Mikrotik Spesifik
 * @param string $host IP Address
 * @param string $user Username
 * @param string $pass Password
 * @param int $port Port API (Default 8728)
 */
function get_mikrotik_client($host, $user, $pass, $port = 8728) {
    try {
        return new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => (int)$port,
            'timeout' => 3,
            'attempts' => 1
        ]);
    } catch (Exception $e) {
        return null; 
    }
}

/**
 * Fungsi Helper untuk menyederhanakan CRUD
 */
function mikrotik_query($client, $endpoint, $action = 'print', $data = [], $id = null) {
    if (!$client) return [];

    $path = $endpoint . '/' . $action;
    $query = new Query($path);

    if ($id) {
        $query->equal('.id', $id);
    }

    foreach ($data as $key => $value) {
        if ($action === 'print') {
            $query->where($key, $value);
        } else {
            $query->equal($key, $value);
        }
    }

    try {
        return $client->query($query)->read();
    } catch (Exception $e) {
        return [];
    }
}
?>