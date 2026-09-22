<?php

namespace App\MikroTik;

/**
 * MikroTik Interface Manager
 */
class InterfaceManager
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getInterfaces(): array
    {
        return $this->connection->query('/interface', 'print');
    }

    public function getInterfaceByName(string $name): array
    {
        return $this->connection->query('/interface', 'print', ['name' => $name]);
    }

    public function setInterfaceComment(string $id, string $comment): array
    {
        return $this->connection->query('/interface', 'set', ['comment' => $comment], $id);
    }
}

