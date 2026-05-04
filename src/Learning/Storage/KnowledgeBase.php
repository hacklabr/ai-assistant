<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Learning\Storage;

use HackLab\AIAssistant\Persistence\StorageInterface;

class KnowledgeBase
{
    private const int MAX_ENTRIES_PER_CONTEXT = 100;
    private const int MAX_BUG_AGE_DAYS = 90;

    public function __construct(
        private readonly StorageInterface $storage,
    ) {}

    public function saveToolPattern(ToolPattern $pattern): void
    {
        $key = $this->sanitizeKey($pattern->toolName);
        $this->storage->save('learning/patterns', $key, $pattern->toArray());
    }

    public function getToolPatterns(string $toolName): array
    {
        $key = $this->sanitizeKey($toolName);
        $data = $this->storage->load('learning/patterns', $key);

        if ($data === null) {
            return [];
        }

        return [ToolPattern::fromArray($data)];
    }

    public function saveBug(BugReport $bug): string
    {
        $this->storage->save('learning/bugs', $this->sanitizeKey($bug->id), $bug->toArray());
        return $bug->id;
    }

    public function getBug(string $id): ?BugReport
    {
        $data = $this->storage->load('learning/bugs', $this->sanitizeKey($id));

        if ($data === null) {
            return null;
        }

        return BugReport::fromArray($data);
    }

    public function searchBugs(array $criteria): array
    {
        $keys = $this->storage->list('learning/bugs');
        $bugs = [];

        foreach ($keys as $key) {
            $data = $this->storage->load('learning/bugs', $key);
            if ($data === null) {
                continue;
            }

            $bug = BugReport::fromArray($data);

            if (isset($criteria['resolved']) && $bug->resolved !== $criteria['resolved']) {
                continue;
            }

            if (isset($criteria['error_type']) && $bug->errorType !== $criteria['error_type']) {
                continue;
            }

            if (isset($criteria['context'])) {
                $contextValues = $bug->context;
                $contextMatch = false;
                foreach ($contextValues as $value) {
                    if (is_string($value) && str_contains(strtolower((string) $value), strtolower((string) $criteria['context']))) {
                        $contextMatch = true;
                        break;
                    }
                }
                if (!$contextMatch && !str_contains(strtolower($bug->errorType), strtolower((string) $criteria['context']))) {
                    continue;
                }
            }

            $bugs[] = $bug;
        }

        return $bugs;
    }

    public function searchPatterns(string $query): array
    {
        $results = $this->storage->search('learning/patterns', $query);

        return array_map(
            fn(array $result) => ToolPattern::fromArray($result['data']),
            $results,
        );
    }

    public function getToolNames(): array
    {
        $keys = $this->storage->list('learning/patterns');
        return array_map(fn(string $key) => str_replace('_', '::', $key), $keys);
    }

    public function saveLearning(LearningEntry $entry): void
    {
        $id = $entry->id ?? ('learn-' . date('Y-m-d') . '-' . uniqid());
        $contextKey = $this->sanitizeKey($entry->context);

        $data = $entry->toArray();
        $data['id'] = $id;

        $this->storage->save("learning/entries/{$contextKey}", $this->sanitizeKey($id), $data);
    }

    public function getLearnings(string $context): array
    {
        $contextKey = $this->sanitizeKey($context);
        $keys = $this->storage->list("learning/entries/{$contextKey}");

        $entries = [];
        foreach ($keys as $key) {
            $data = $this->storage->load("learning/entries/{$contextKey}", $key);
            if ($data !== null) {
                $entries[] = LearningEntry::fromArray($data);
            }
        }

        return $entries;
    }

    public function searchLearnings(string $query): array
    {
        $contexts = $this->getContexts();
        $entries = [];

        foreach ($contexts as $context) {
            $contextKey = $this->sanitizeKey($context);
            $keys = $this->storage->list("learning/entries/{$contextKey}");

            foreach ($keys as $key) {
                $data = $this->storage->load("learning/entries/{$contextKey}", $key);
                if ($data === null) {
                    continue;
                }

                $entry = LearningEntry::fromArray($data);
                if ($entry->matches($query) > 0.3) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    public function getContexts(): array
    {
        $allKeys = $this->storage->list('learning/entries');
        return array_map(fn(string $key) => str_replace('_', '::', $key), $allKeys);
    }

    public function deleteLearning(string $context, string $id): bool
    {
        $contextKey = $this->sanitizeKey($context);
        return $this->storage->delete("learning/entries/{$contextKey}", $this->sanitizeKey($id));
    }

    public function cleanup(): int
    {
        $removed = 0;

        $removed += $this->storage->cleanup('learning/bugs', [
            'max_age_days' => self::MAX_BUG_AGE_DAYS,
        ]);

        $contexts = $this->getContexts();
        foreach ($contexts as $context) {
            $contextKey = $this->sanitizeKey($context);
            $removed += $this->storage->cleanup("learning/entries/{$contextKey}", [
                'max_per_namespace' => self::MAX_ENTRIES_PER_CONTEXT,
            ]);
        }

        return $removed;
    }

    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    }
}
