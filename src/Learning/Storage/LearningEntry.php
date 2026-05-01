<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Learning\Storage;

/**
 * Represents a contextual learning entry (not tied to a specific tool execution).
 */
class LearningEntry
{
    public function __construct(
        public readonly string $context,
        public readonly string $observation,
        public readonly bool $workedWell,
        public readonly array $tags = [],
        public readonly ?string $id = null,
        public readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable(),
    ) {
    }

    /**
     * Check if this entry matches a search query.
     */
    public function matches(string $query): float
    {
        $queryLower = strtolower($query);
        $text = strtolower($this->context . ' ' . $this->observation . ' ' . implode(' ', $this->tags));

        if (str_contains($text, $queryLower)) {
            return 1.0;
        }

        // Simple word overlap scoring
        $queryWords = str_word_count($queryLower, 1);
        $textWords = str_word_count($text, 1);

        if (empty($queryWords)) {
            return 0.0;
        }

        $matches = count(array_intersect($queryWords, $textWords));
        return $matches / count($queryWords);
    }
}
