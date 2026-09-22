<?php

namespace App\MikroTik;

/**
 * MikroTik System & Resource Manager
 */
class SystemManager
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getResource(): array
    {
        $res = $this->connection->query('/system/resource', 'print');
        return $res[0] ?? [];
    }

    public function getRouterboard(): array
    {
        $res = $this->connection->query('/system/routerboard', 'print');
        return $res[0] ?? [];
    }
}

