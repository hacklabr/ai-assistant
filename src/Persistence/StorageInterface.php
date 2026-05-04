<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Persistence;

interface StorageInterface
{
    public function save(string $namespace, string $key, array $data): void;

    public function load(string $namespace, string $key): ?array;

    public function delete(string $namespace, string $key): bool;

    public function exists(string $namespace, string $key): bool;

    /**
     * @return string[]
     */
    public function list(string $namespace, string $pattern = '*'): array;

    /**
     * @return array{data: array, score: float}[]
     */
    public function search(string $namespace, string $query, int $limit = 10): array;

    /**
     * @param array{max_age_days?: int, max_per_namespace?: int} $criteria
     * @return int Number of entries removed
     */
    public function cleanup(string $namespace, array $criteria = []): int;
}
