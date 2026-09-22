<?php

namespace App\Services;

use App\Repositories\ConnectionRepository;
use App\Repositories\NodeRepository;

/**
 * Connection Links Service
 */
class ConnectionService
{
    private ConnectionRepository $connRepo;
    private NodeRepository $nodeRepo;

    public function __construct(
        ?ConnectionRepository $connRepo = null,
        ?NodeRepository $nodeRepo = null
    ) {
        $this->connRepo = $connRepo ?: new ConnectionRepository();
        $this->nodeRepo = $nodeRepo ?: new NodeRepository();
    }

    public function getAll(): array
    {
        return $this->connRepo->all();
    }

    public function getById(int $id): ?array
    {
        return $this->connRepo->find($id);
    }

    public function create(array $data): int
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $startNode = $this->nodeRepo->find((int)$data['start_node_id']);
            $endNode   = $this->nodeRepo->find((int)$data['end_node_id']);

            $sname = $startNode['name'] ?? 'Node';
            $ename = $endNode['name']   ?? 'Node';

            $sa = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $sname), 0, 6));
            $ea = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $ename), 0, 6));
            $rand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

            $data['name'] = "{$sa}-{$ea}-{$rand}";
        }

        return $this->connRepo->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->connRepo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->connRepo->delete($id);
    }
}

