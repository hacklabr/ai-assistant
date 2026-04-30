<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Persistence;

interface StorageInterface
{
    /**
     * Save data to storage.
     */
    public function save(string $key, array $data): void;

    /**
     * Load data from storage.
     */
    public function load(string $key): ?array;

    /**
     * Delete data from storage.
     */
    public function delete(string $key): void;

    /**
     * List keys matching a pattern.
     *
     * @return string[]
     */
    public function list(string $pattern = '*'): array;

    /**
     * Check if key exists.
     */
    public function exists(string $key): bool;
}
