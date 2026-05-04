<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Learning\Storage;

class ToolPattern
{
    /**
     * @param array<string, mixed> $arguments
     * @param mixed $result
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $toolName,
        public readonly array $arguments,
        public readonly mixed $result,
        public readonly ?string $error = null,
        public readonly array $context = [],
        public readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable(),
    ) {}

    /**
     * Calculate similarity score with a task description (0.0 - 1.0).
     */
    public function matches(string $taskDescription): float
    {
        $taskLower = strtolower($taskDescription);
        $toolLower = strtolower($this->toolName);
        $argText = strtolower(json_encode($this->arguments));

        $score = 0.0;

        // Check if tool name appears in task
        if (str_contains($taskLower, str_replace(['_', '-', '::'], ' ', $toolLower))) {
            $score += 0.4;
        }

        // Check argument keywords
        $taskWords = str_word_count($taskLower, 1);
        $argWords = str_word_count($argText, 1);
        $commonWords = array_intersect($taskWords, $argWords);

        if (count($taskWords) > 0) {
            $score += (count($commonWords) / count($taskWords)) * 0.4;
        }

        // Successful patterns score higher
        if ($this->error === null) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    public function toArray(): array
    {
        return [
            'toolName' => $this->toolName,
            'arguments' => $this->arguments,
            'result' => $this->result,
            'error' => $this->error,
            'context' => $this->context,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            toolName: $data['toolName'] ?? 'unknown',
            arguments: $data['arguments'] ?? [],
            result: $data['result'] ?? null,
            error: $data['error'] ?? null,
            context: $data['context'] ?? [],
            timestamp: new \DateTimeImmutable($data['timestamp'] ?? 'now'),
        );
    }
}
