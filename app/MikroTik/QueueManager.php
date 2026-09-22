<?php

namespace App\MikroTik;

/**
 * MikroTik Simple & Tree Queue Manager
 */
class QueueManager
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getQueues(array $filter = []): array
    {
        return $this->connection->query('/queue/simple', 'print', $filter);
    }

    public function getTreeQueues(array $filter = []): array
    {
        return $this->connection->query('/queue/tree', 'print', $filter);
    }

    public function addQueue(string $name, string $targetIp, string $maxUpload, string $maxDownload, string $comment = ''): array
    {
        $maxLimit = $maxUpload . '/' . $maxDownload;
        $data = [
            'name'      => $name,
            'target'    => $targetIp,
            'max-limit' => $maxLimit,
            'comment'   => $comment,
        ];
        return $this->connection->query('/queue/simple', 'add', $data);
    }

    public function updateQueueLimit(string $id, string $maxUpload, string $maxDownload): array
    {
        $maxLimit = $maxUpload . '/' . $maxDownload;
        return $this->connection->query('/queue/simple', 'set', ['max-limit' => $maxLimit], $id);
    }

    public function deleteQueue(string $id): array
    {
        return $this->connection->query('/queue/simple', 'remove', [], $id);
    }

    public function getQueuesByPrefix(string $contains): array
    {
        $all = $this->connection->query('/queue/simple', 'print');
        $filtered = [];
        foreach ($all as $q) {
            if (isset($q['name']) && str_contains($q['name'], $contains)) {
                $filtered[] = $q;
            }
        }
        return $filtered;
    }

    /**
     * Get all queues (tree + simple) formatted for UI dropdowns
     */
    public function getAllQueuesFormatted(): array
    {
        $queues = [];

        // 1. /queue/tree
        $trees = $this->getTreeQueues();
        foreach ($trees as $q) {
            $name = $q['name'] ?? '';
            if (!$name) continue;
            $queues[] = [
                'name'   => $name,
                'type'   => 'tree',
                'parent' => $q['parent'] ?? '',
                'rate'   => $q['max-limit'] ?? '-',
            ];
        }

        // 2. /queue/simple
        $simples = $this->getQueues();
        foreach ($simples as $q) {
            $name = $q['name'] ?? '';
            if (!$name) continue;
            $queues[] = [
                'name'   => $name,
                'type'   => 'simple',
                'parent' => $q['parent'] ?? '',
                'rate'   => $q['max-limit'] ?? '-',
            ];
        }

        return $queues;
    }
}
