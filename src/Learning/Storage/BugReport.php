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
}
