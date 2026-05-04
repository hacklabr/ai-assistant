<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Learning\Storage;

class BugReport
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $id,
        public readonly string $errorType,
        public readonly string $errorMessage,
        public readonly string $stackTrace,
        public readonly array $context,
        public readonly \DateTimeImmutable $timestamp,
        public readonly ?string $resolution = null,
        public readonly bool $resolved = false,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'errorType' => $this->errorType,
            'errorMessage' => $this->errorMessage,
            'stackTrace' => $this->stackTrace,
            'context' => $this->context,
            'timestamp' => $this->timestamp->format('c'),
            'resolution' => $this->resolution,
            'resolved' => $this->resolved,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            errorType: $data['errorType'] ?? 'Unknown',
            errorMessage: $data['errorMessage'] ?? '',
            stackTrace: $data['stackTrace'] ?? '',
            context: $data['context'] ?? [],
            timestamp: new \DateTimeImmutable($data['timestamp'] ?? 'now'),
            resolution: $data['resolution'] ?? null,
            resolved: $data['resolved'] ?? false,
        );
    }
}
