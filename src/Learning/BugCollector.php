<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Learning;

use HackLab\AIAssistant\Learning\Storage\BugReport;
use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;

/**
 * Collects and manages bug reports with full context.
 */
class BugCollector
{
    public function __construct(
        private readonly KnowledgeBase $storage,
    ) {}

    /**
     * Collect a bug/error.
     *
     * @param array<string, mixed> $context
     */
    public function collect(
        \Throwable $error,
        array $context,
        ?string $resolution = null,
    ): string {
        $bugId = 'bug-' . date('Y-m-d') . '-' . uniqid();

        $bug = new BugReport(
            id: $bugId,
            errorType: $error::class,
            errorMessage: $error->getMessage(),
            stackTrace: $this->sanitizeStackTrace($error->getTraceAsString()),
            context: $context,
            timestamp: new \DateTimeImmutable(),
            resolution: $resolution,
            resolved: $resolution !== null,
        );

        $this->storage->saveBug($bug);

        return $bugId;
    }

    /**
     * Find similar past bugs based on error type and message.
     *
     * @return BugReport[]
     */
    public function findSimilar(\Throwable $error, array $context): array
    {
        $allBugs = $this->storage->searchBugs([]);
        $similarBugs = [];

        $errorType = $error::class;
        $errorMessage = strtolower($error->getMessage());
        $errorWords = str_word_count($errorMessage, 1);

        foreach ($allBugs as $bug) {
            $score = 0.0;

            // Same error type
            if ($bug->errorType === $errorType) {
                $score += 0.5;
            }

            // Message similarity
            $bugWords = str_word_count(strtolower($bug->errorMessage), 1);
            $commonWords = array_intersect($errorWords, $bugWords);

            if (count($errorWords) > 0) {
                $score += (count($commonWords) / count($errorWords)) * 0.5;
            }

            if ($score > 0.3) {
                $similarBugs[] = ['bug' => $bug, 'score' => $score];
            }
        }

        // Sort by similarity
        usort($similarBugs, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_column($similarBugs, 'bug');
    }

    /**
     * Mark a bug as resolved with solution.
     */
    public function resolve(string $bugId, string $resolution): void
    {
        $bug = $this->storage->getBug($bugId);

        if ($bug === null) {
            throw new \InvalidArgumentException("Bug not found: {$bugId}");
        }

        $resolvedBug = new BugReport(
            id: $bug->id,
            errorType: $bug->errorType,
            errorMessage: $bug->errorMessage,
            stackTrace: $bug->stackTrace,
            context: $bug->context,
            timestamp: $bug->timestamp,
            resolution: $resolution,
            resolved: true,
        );

        $this->storage->saveBug($resolvedBug);
    }

    /**
     * Get unresolved bugs.
     *
     * @return BugReport[]
     */
    public function getUnresolved(): array
    {
        return $this->storage->searchBugs(['resolved' => false]);
    }

    private function sanitizeStackTrace(string $trace): string
    {
        $trace = preg_replace('#/home/[^\s/]+#', '/[HOME]', $trace);
        $trace = preg_replace('#/var/www/[^\s/]+#', '/[APP]', $trace);
        $trace = preg_replace('#/Users/[^\s/]+#', '/[HOME]', $trace);
        $trace = preg_replace('#/root/#', '/[ROOT]/', $trace);

        $lines = explode("\n", $trace);
        if (count($lines) > 10) {
            $lines = array_slice($lines, 0, 10);
            $lines[] = '... [truncated]';
        }

        return implode("\n", $lines);
    }
}
