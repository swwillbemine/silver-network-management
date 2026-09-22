<?php

namespace App\Services;

use App\Repositories\NodeRepository;

/**
 * Node Infrastructure Service
 */
class NodeService
{
    private NodeRepository $nodeRepo;

    public function __construct(?NodeRepository $nodeRepo = null)
    {
        $this->nodeRepo = $nodeRepo ?: new NodeRepository();
    }

    public function getAll(): array
    {
        return $this->nodeRepo->all();
    }

    public function getById(int $id): ?array
    {
        return $this->nodeRepo->find($id);
    }

    public function create(array $data): int
    {
        return $this->nodeRepo->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->nodeRepo->update($id, $data);
    }

    public function syncOwnerContact(int $id, string $phone): bool
    {
        return $this->nodeRepo->updateContact($id, $phone);
    }

    public function delete(int $id): bool
    {
        return $this->nodeRepo->delete($id);
    }
}

