<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Persistence;

/**
 * File-based storage implementation using JSON for structured data.
 */
class FileStorage implements StorageInterface, ConversationStorageInterface
{
    public function __construct(
        private readonly string $basePath,
    ) {
        $this->ensureDirectoryExists($basePath);
    }

    public function save(string $key, array $data): void
    {
        $path = $this->getPath($key);
        $this->ensureDirectoryExists(dirname($path));

        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        file_put_contents($path, $content, LOCK_EX);
    }

    public function load(string $key): ?array
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    public function delete(string $key): void
    {
        $path = $this->getPath($key);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function list(string $pattern = '*'): array
    {
        $pattern = str_replace(['*', '/'], ['', DIRECTORY_SEPARATOR], $pattern);
        $searchPath = $this->basePath . DIRECTORY_SEPARATOR . $pattern;

        $files = glob($searchPath . '*.json');
        if ($files === false) {
            return [];
        }

        return array_map(fn (string $file) => basename($file, '.json'), $files);
    }

    public function exists(string $key): bool
    {
        return file_exists($this->getPath($key));
    }

    public function saveThread(string $threadId, array $messages): void
    {
        $this->save("conversations/{$threadId}", [
            'thread_id' => $threadId,
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'messages' => $messages,
        ]);
    }

    public function loadThread(string $threadId): array
    {
        $data = $this->load("conversations/{$threadId}");
        return $data['messages'] ?? [];
    }

    public function appendToThread(string $threadId, array $messages): void
    {
        $data = $this->load("conversations/{$threadId}");
        $existingMessages = $data['messages'] ?? [];
        $allMessages = array_merge($existingMessages, $messages);

        $this->save("conversations/{$threadId}", [
            'thread_id' => $threadId,
            'created_at' => $data['created_at'] ?? date('c'),
            'updated_at' => date('c'),
            'messages' => $allMessages,
        ]);
    }

    public function listThreads(): array
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'conversations';
        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . DIRECTORY_SEPARATOR . '*.json');
        if ($files === false) {
            return [];
        }

        return array_map(fn (string $file) => basename($file, '.json'), $files);
    }

    public function deleteThread(string $threadId): void
    {
        $this->delete("conversations/{$threadId}");
    }

    private function getPath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->basePath . DIRECTORY_SEPARATOR . $safeKey . '.json';
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
