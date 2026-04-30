<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Persistence;

interface ConversationStorageInterface extends StorageInterface
{
    /**
     * Save a conversation thread.
     *
     * @param array<int, array<string, mixed>> $messages
     */
    public function saveThread(string $threadId, array $messages): void;

    /**
     * Load a conversation thread.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadThread(string $threadId): array;

    /**
     * Append messages to a thread.
     *
     * @param array<int, array<string, mixed>> $messages
     */
    public function appendToThread(string $threadId, array $messages): void;

    /**
     * List all thread IDs.
     *
     * @return string[]
     */
    public function listThreads(): array;

    /**
     * Delete a thread.
     */
    public function deleteThread(string $threadId): void;
}
