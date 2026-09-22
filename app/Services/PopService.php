<?php

namespace App\Services;

use App\Repositories\PopRepository;

/**
 * POP (Point of Presence) Service
 */
class PopService
{
    private PopRepository $popRepo;

    public function __construct(?PopRepository $popRepo = null)
    {
        $this->popRepo = $popRepo ?: new PopRepository();
    }

    public function getAll(): array
    {
        return $this->popRepo->all();
    }

    public function getById(int $id): ?array
    {
        return $this->popRepo->find($id);
    }

    public function getByNode(int $nodeId): array
    {
        return $this->popRepo->findByNode($nodeId);
    }

    public function create(array $data): int
    {
        return $this->popRepo->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->popRepo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->popRepo->delete($id);
    }
}

