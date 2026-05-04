<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Memory;

use HackLab\AIAssistant\Persistence\StorageInterface;

class UserMemoryStore
{
    public function __construct(
        private readonly StorageInterface $storage,
    ) {}

    public function save(UserMemory $memory): void
    {
        $namespace = "memories/{$this->sanitize($memory->userId)}";
        $this->storage->save($namespace, $this->sanitize($memory->id), $memory->toArray());
    }

    public function get(string $userId, string $memoryId): ?UserMemory
    {
        $namespace = "memories/{$this->sanitize($userId)}";
        $data = $this->storage->load($namespace, $this->sanitize($memoryId));

        if ($data === null) {
            return null;
        }

        return UserMemory::fromArray($data);
    }

    public function listForUser(string $userId, ?string $category = null): array
    {
        $namespace = "memories/{$this->sanitize($userId)}";
        $keys = $this->storage->list($namespace);

        $memories = [];
        foreach ($keys as $key) {
            $data = $this->storage->load($namespace, $key);
            if ($data === null) {
                continue;
            }

            $memory = UserMemory::fromArray($data);

            if ($category !== null && $memory->category !== $category) {
                continue;
            }

            $memories[] = $memory;
        }

        usort($memories, fn(UserMemory $a, UserMemory $b) => $b->createdAt <=> $a->createdAt);

        return $memories;
    }

    public function search(string $userId, string $query): array
    {
        $all = $this->listForUser($userId);

        $results = [];
        foreach ($all as $memory) {
            $score = $memory->matches($query);
            if ($score > 0.2) {
                $results[] = ['memory' => $memory, 'score' => $score];
            }
        }

        usort($results, fn(array $a, array $b) => $b['score'] <=> $a['score']);

        return array_column($results, 'memory');
    }

    public function delete(string $userId, string $memoryId): bool
    {
        $namespace = "memories/{$this->sanitize($userId)}";
        return $this->storage->delete($namespace, $this->sanitize($memoryId));
    }

    public function exists(string $userId, string $memoryId): bool
    {
        $namespace = "memories/{$this->sanitize($userId)}";
        return $this->storage->exists($namespace, $this->sanitize($memoryId));
    }

    private function sanitize(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $value);
    }
}
