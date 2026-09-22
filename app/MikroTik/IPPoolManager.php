<?php

namespace App\MikroTik;

/**
 * MikroTik IP Pool Manager
 */
class IPPoolManager
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getPools(): array
    {
        return $this->connection->query('/ip/pool', 'print');
    }

    public function addPool(string $name, string $ranges): array
    {
        $data = [
            'name'   => $name,
            'ranges' => $ranges,
        ];
        return $this->connection->query('/ip/pool', 'add', $data);
    }

    public function deletePool(string $id): array
    {
        return $this->connection->query('/ip/pool', 'remove', [], $id);
    }
}

