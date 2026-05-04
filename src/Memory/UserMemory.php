<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Memory;

class UserMemory
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $category,
        public readonly string $content,
        public readonly array $tags = [],
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public readonly ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function matches(string $query): float
    {
        $queryLower = strtolower($query);
        $text = strtolower($this->category . ' ' . $this->content . ' ' . implode(' ', $this->tags));

        if (str_contains($text, $queryLower)) {
            return 1.0;
        }

        $queryWords = str_word_count($queryLower, 1);
        $textWords = str_word_count($text, 1);

        if (empty($queryWords)) {
            return 0.0;
        }

        $matches = count(array_intersect($queryWords, $textWords));
        return $matches / count($queryWords);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'category' => $this->category,
            'content' => $this->content,
            'tags' => $this->tags,
            'createdAt' => $this->createdAt->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            userId: $data['userId'] ?? '',
            category: $data['category'] ?? 'note',
            content: $data['content'] ?? '',
            tags: $data['tags'] ?? [],
            createdAt: new \DateTimeImmutable($data['createdAt'] ?? 'now'),
            updatedAt: isset($data['updatedAt']) ? new \DateTimeImmutable($data['updatedAt']) : null,
        );
    }
}
