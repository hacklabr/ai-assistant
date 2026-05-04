<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Persistence;

class ConversationStore
{
    private const string NAMESPACE = 'conversations';

    public function __construct(
        private readonly StorageInterface $storage,
    ) {}

    public function saveThread(string $threadId, array $messages): void
    {
        $this->storage->save(self::NAMESPACE, $threadId, [
            'thread_id' => $threadId,
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'messages' => $messages,
        ]);
    }

    public function loadThread(string $threadId): array
    {
        $data = $this->storage->load(self::NAMESPACE, $threadId);
        return $data['messages'] ?? [];
    }

    public function appendToThread(string $threadId, array $messages): void
    {
        $data = $this->storage->load(self::NAMESPACE, $threadId);
        $existingMessages = $data['messages'] ?? [];
        $allMessages = array_merge($existingMessages, $messages);

        $this->storage->save(self::NAMESPACE, $threadId, [
            'thread_id' => $threadId,
            'created_at' => $data['created_at'] ?? date('c'),
            'updated_at' => date('c'),
            'messages' => $allMessages,
        ]);
    }

    public function listThreads(): array
    {
        return $this->storage->list(self::NAMESPACE);
    }

    public function deleteThread(string $threadId): bool
    {
        return $this->storage->delete(self::NAMESPACE, $threadId);
    }
}
