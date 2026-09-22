<?php

namespace App\MikroTik;

use RouterOS\Client;
use RouterOS\Query;
use Exception;

/**
 * MikroTik RouterOS API Connection Manager
 */
class Connection
{
    private ?Client $client = null;
    private string $host;
    private string $user;
    private string $pass;
    private int $port;
    private int $timeout;

    public function __construct(string $host, string $user, string $pass, int $port = 8728, int $timeout = 3)
    {
        $this->host    = $host;
        $this->user    = $user;
        $this->pass    = $pass;
        $this->port    = $port ?: 8728;
        $this->timeout = $timeout ?: 3;
    }

    /**
     * Create connection instance from a router DB row
     */
    public static function fromRouter(array $router): self
    {
        $host = $router['ip_address'] ?? ($router['host'] ?? '');
        $user = $router['username']   ?? ($router['user'] ?? '');
        $pass = $router['password']   ?? ($router['pass'] ?? '');
        $port = (int)($router['port'] ?? 8728);

        return new self($host, $user, $pass, $port);
    }

    /**
     * Get active Client or connect lazily
     */
    public function getClient(): ?Client
    {
        if ($this->client === null) {
            $this->connect();
        }

        return $this->client;
    }

    public function isConnected(): bool
    {
        return $this->getClient() !== null;
    }

    private function connect(): void
    {
        try {
            $this->client = new Client([
                'host'     => $this->host,
                'user'     => $this->user,
                'pass'     => $this->pass,
                'port'     => $this->port,
                'timeout'  => $this->timeout,
                'attempts' => 1,
            ]);
        } catch (Exception $e) {
            $this->client = null;
        }
    }

    /**
     * Execute a RouterOS API query
     */
    public function query(string $endpoint, string $action = 'print', array $data = [], ?string $id = null): array
    {
        $client = $this->getClient();
        if (!$client) {
            return [];
        }

        $path = rtrim($endpoint, '/') . '/' . ltrim($action, '/');
        $query = new Query($path);

        if ($id !== null) {
            $query->equal('.id', $id);
        }

        foreach ($data as $key => $value) {
            if ($action === 'print') {
                $query->where($key, (string)$value);
            } else {
                $query->equal($key, (string)$value);
            }
        }

        try {
            return $client->query($query)->read();
        } catch (Exception $e) {
            return [];
        }
    }
}

