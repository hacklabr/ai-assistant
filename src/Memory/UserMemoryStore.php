<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Memory;

class UserMemoryStore implements UserMemoryStoreInterface
{
    public function __construct(
        private readonly string $basePath,
    ) {
        $this->ensureDirectoryExists($basePath);
    }

    public function save(UserMemory $memory): void
    {
        $userDir = $this->basePath . '/' . $this->sanitize($memory->userId);
        $this->ensureDirectoryExists($userDir);

        $filepath = $userDir . '/' . $this->sanitize($memory->id) . '.json';

        $data = [
            'id' => $memory->id,
            'userId' => $memory->userId,
            'category' => $memory->category,
            'content' => $memory->content,
            'tags' => $memory->tags,
            'createdAt' => $memory->createdAt->format('c'),
            'updatedAt' => $memory->updatedAt?->format('c'),
        ];

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        chmod($filepath, 0600);
    }

    public function get(string $userId, string $memoryId): ?UserMemory
    {
        $filepath = $this->getUserFile($userId, $memoryId);

        if (!file_exists($filepath)) {
            return null;
        }

        return $this->readFile($filepath);
    }

    public function listForUser(string $userId, ?string $category = null): array
    {
        $userDir = $this->basePath . '/' . $this->sanitize($userId);

        if (!is_dir($userDir)) {
            return [];
        }

        $files = glob($userDir . '/*.json');
        if ($files === false) {
            return [];
        }

        $memories = [];
        foreach ($files as $file) {
            $memory = $this->readFile($file);
            if ($memory === null) {
                continue;
            }

            if ($category !== null && $memory->category !== $category) {
                continue;
            }

            $memories[] = $memory;
        }

        usort($memories, fn (UserMemory $a, UserMemory $b) => $b->createdAt <=> $a->createdAt);

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

        usort($results, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_column($results, 'memory');
    }

    public function delete(string $userId, string $memoryId): bool
    {
        $filepath = $this->getUserFile($userId, $memoryId);

        if (!file_exists($filepath)) {
            return false;
        }

        unlink($filepath);
        return true;
    }

    public function exists(string $userId, string $memoryId): bool
    {
        return file_exists($this->getUserFile($userId, $memoryId));
    }

    private function getUserFile(string $userId, string $memoryId): string
    {
        return $this->basePath . '/' . $this->sanitize($userId) . '/' . $this->sanitize($memoryId) . '.json';
    }

    private function readFile(string $filepath): ?UserMemory
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            return null;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        return new UserMemory(
            id: $data['id'] ?? basename($filepath, '.json'),
            userId: $data['userId'] ?? '',
            category: $data['category'] ?? 'note',
            content: $data['content'] ?? '',
            tags: $data['tags'] ?? [],
            createdAt: new \DateTimeImmutable($data['createdAt'] ?? 'now'),
            updatedAt: isset($data['updatedAt']) ? new \DateTimeImmutable($data['updatedAt']) : null,
        );
    }

    private function sanitize(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $value);
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0750, true);
        }
    }
}
