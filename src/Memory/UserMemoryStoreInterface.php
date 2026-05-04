<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Memory;

interface UserMemoryStoreInterface
{
    public function save(UserMemory $memory): void;

    public function get(string $userId, string $memoryId): ?UserMemory;

    /**
     * @return UserMemory[]
     */
    public function listForUser(string $userId, ?string $category = null): array;

    /**
     * @return UserMemory[]
     */
    public function search(string $userId, string $query): array;

    public function delete(string $userId, string $memoryId): bool;

    public function exists(string $userId, string $memoryId): bool;
}
