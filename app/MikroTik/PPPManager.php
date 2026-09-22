<?php

namespace App\MikroTik;

/**
 * MikroTik PPP Secrets, Profiles, and Active Sessions Manager
 */
class PPPManager
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getSecrets(array $filter = []): array
    {
        return $this->connection->query('/ppp/secret', 'print', $filter);
    }

    public function addSecret(string $name, string $password, string $profile, string $service = 'pppoe', string $comment = ''): array
    {
        $data = [
            'name'     => $name,
            'password' => $password,
            'profile'  => $profile,
            'service'  => $service,
            'comment'  => $comment,
        ];
        return $this->connection->query('/ppp/secret', 'add', $data);
    }

    public function updateSecret(string $id, array $data): array
    {
        return $this->connection->query('/ppp/secret', 'set', $data, $id);
    }

    public function deleteSecret(string $id): array
    {
        return $this->connection->query('/ppp/secret', 'remove', [], $id);
    }

    public function getProfiles(): array
    {
        return $this->connection->query('/ppp/profile', 'print');
    }

    public function addProfile(string $name, ?string $localAddr, ?string $remoteAddr, ?string $rateLimit = null): array
    {
        $data = [
            'name'           => $name,
            'local-address'  => (string)$localAddr,
            'remote-address' => (string)$remoteAddr,
        ];
        if ($rateLimit) {
            $data['rate-limit'] = $rateLimit;
        }

        return $this->connection->query('/ppp/profile', 'add', $data);
    }

    public function getActive(): array
    {
        return $this->connection->query('/ppp/active', 'print');
    }

    public function kickActive(string $id): array
    {
        return $this->connection->query('/ppp/active', 'remove', [], $id);
    }

    public function getActivePPPoE(): array
    {
        return $this->connection->query('/ppp/active', 'print', ['service' => 'pppoe']);
    }

    public function getAllPPPoE(): array
    {
        return $this->connection->query('/ppp/secret', 'print', ['service' => 'pppoe']);
    }

    public static function createComment(string $name, string $username): string
    {
        $date = date('Y-m-d');
        return "[SNM-SECRET] {$name} | via Silver Network Management | {$date}";
    }
}

