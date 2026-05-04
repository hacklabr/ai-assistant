<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Learning\Storage;

use HackLab\AIAssistant\Utils\MarkdownParser;

/**
 * File-based implementation of LearningStorageInterface.
 * Stores patterns and bugs as Markdown files with YAML frontmatter.
 */
class KnowledgeBase implements LearningStorageInterface
{
    private const int MAX_ENTRIES_PER_CONTEXT = 100;
    private const int MAX_BUG_AGE_DAYS = 90;

    public function __construct(
        private readonly string $basePath,
        private readonly MarkdownParser $parser = new MarkdownParser(),
    ) {
        $this->ensureDirectoryExists($basePath . '/patterns');
        $this->ensureDirectoryExists($basePath . '/bugs');
        $this->ensureDirectoryExists($basePath . '/learnings');
    }

    public function saveToolPattern(ToolPattern $pattern): void
    {
        $filename = $this->sanitizeFilename($pattern->toolName) . '.md';
        $filepath = $this->basePath . '/patterns/' . $filename;

        $content = $this->formatToolPattern($pattern);
        file_put_contents($filepath, $content, LOCK_EX);
    }

    public function getToolPatterns(string $toolName): array
    {
        $filename = $this->sanitizeFilename($toolName) . '.md';
        $filepath = $this->basePath . '/patterns/' . $filename;

        if (!file_exists($filepath)) {
            return [];
        }

        return [$this->parseToolPattern($filepath)];
    }

    public function saveBug(BugReport $bug): string
    {
        $filename = $bug->id . '.md';
        $filepath = $this->basePath . '/bugs/' . $filename;

        $content = $this->formatBugReport($bug);
        file_put_contents($filepath, $content, LOCK_EX);

        return $bug->id;
    }

    public function getBug(string $id): ?BugReport
    {
        $filepath = $this->basePath . '/bugs/' . $this->sanitizeFilename($id) . '.md';

        if (!file_exists($filepath)) {
            return null;
        }

        return $this->parseBugReport($filepath);
    }

    public function searchBugs(array $criteria): array
    {
        $bugs = [];
        $files = glob($this->basePath . '/bugs/*.md');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $bug = $this->parseBugReport($file);

            // Apply filters
            if (isset($criteria['resolved']) && $bug->resolved !== $criteria['resolved']) {
                continue;
            }

            if (isset($criteria['error_type']) && $bug->errorType !== $criteria['error_type']) {
                continue;
            }

            $bugs[] = $bug;
        }

        return $bugs;
    }

    public function searchPatterns(string $query): array
    {
        $patterns = [];
        $files = glob($this->basePath . '/patterns/*.md');

        if ($files === false) {
            return [];
        }

        $queryLower = strtolower($query);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            if (str_contains(strtolower($content), $queryLower)) {
                $patterns[] = $this->parseToolPattern($file);
            }
        }

        return $patterns;
    }

    public function getToolNames(): array
    {
        $files = glob($this->basePath . '/patterns/*.md');

        if ($files === false) {
            return [];
        }

        return array_map(
            fn (string $file) => str_replace('_', '::', basename($file, '.md')),
            $files
        );
    }

    public function saveLearning(LearningEntry $entry): void
    {
        $contextDir = $this->basePath . '/learnings/' . $this->sanitizeFilename($entry->context);
        $this->ensureDirectoryExists($contextDir);

        $id = $entry->id ?? uniqid('learn-', true);
        $filepath = $contextDir . '/' . $id . '.md';

        $content = $this->formatLearningEntry($entry, $id);
        file_put_contents($filepath, $content, LOCK_EX);
    }

    public function getLearnings(string $context): array
    {
        $contextDir = $this->basePath . '/learnings/' . $this->sanitizeFilename($context);

        if (!is_dir($contextDir)) {
            return [];
        }

        $entries = [];
        $files = glob($contextDir . '/*.md');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $entries[] = $this->parseLearningEntry($file);
        }

        return $entries;
    }

    public function searchLearnings(string $query): array
    {
        $entries = [];
        $contextDirs = glob($this->basePath . '/learnings/*', GLOB_ONLYDIR);

        if ($contextDirs === false) {
            return [];
        }

        foreach ($contextDirs as $contextDir) {
            $files = glob($contextDir . '/*.md');
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                $entry = $this->parseLearningEntry($file);
                if ($entry->matches($query) > 0.3) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    public function getContexts(): array
    {
        $contextDirs = glob($this->basePath . '/learnings/*', GLOB_ONLYDIR);

        if ($contextDirs === false) {
            return [];
        }

        return array_map(
            fn (string $dir) => str_replace('_', '::', basename($dir)),
            $contextDirs
        );
    }

    public function cleanup(): int
    {
        $removed = 0;

        $removed += $this->cleanupOldBugs();
        $removed += $this->cleanupOverflowedLearnings();

        return $removed;
    }

    private function cleanupOldBugs(): int
    {
        $removed = 0;
        $files = glob($this->basePath . '/bugs/*.md');

        if ($files === false) {
            return 0;
        }

        $threshold = new \DateTimeImmutable('-' . self::MAX_BUG_AGE_DAYS . ' days');

        foreach ($files as $file) {
            $bug = $this->parseBugReport($file);

            if ($bug->resolved && $bug->timestamp < $threshold) {
                unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    private function cleanupOverflowedLearnings(): int
    {
        $removed = 0;
        $contextDirs = glob($this->basePath . '/learnings/*', GLOB_ONLYDIR);

        if ($contextDirs === false) {
            return 0;
        }

        foreach ($contextDirs as $contextDir) {
            $files = glob($contextDir . '/*.md');

            if ($files === false || count($files) <= self::MAX_ENTRIES_PER_CONTEXT) {
                continue;
            }

            usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

            $toRemove = array_slice($files, self::MAX_ENTRIES_PER_CONTEXT);

            foreach ($toRemove as $file) {
                unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    private function formatToolPattern(ToolPattern $pattern): string
    {
        $yaml = "---\n";
        $yaml .= "type: tool_pattern\n";
        $yaml .= "tool: {$pattern->toolName}\n";
        $yaml .= "timestamp: {$pattern->timestamp->format('c')}\n";
        $yaml .= "error: " . ($pattern->error ? 'true' : 'false') . "\n";
        $yaml .= "---\n\n";

        $yaml .= "## Arguments\n\n";
        $yaml .= "```json\n";
        $yaml .= json_encode($pattern->arguments, JSON_PRETTY_PRINT);
        $yaml .= "\n```\n\n";

        if ($pattern->error !== null) {
            $yaml .= "## Error\n\n";
            $yaml .= "```\n{$pattern->error}\n```\n\n";
        } else {
            $yaml .= "## Result\n\n";
            $yaml .= "```\n" . json_encode($pattern->result) . "\n```\n\n";
        }

        if (!empty($pattern->context)) {
            $yaml .= "## Context\n\n";
            $yaml .= "```json\n";
            $yaml .= json_encode($pattern->context, JSON_PRETTY_PRINT);
            $yaml .= "\n```\n";
        }

        return $yaml;
    }

    private function parseToolPattern(string $filepath): ToolPattern
    {
        $content = file_get_contents($filepath);
        $parsed = $this->parser->parse($content);
        $frontmatter = $parsed['frontmatter'];

        return new ToolPattern(
            toolName: $frontmatter['tool'] ?? 'unknown',
            arguments: [],
            result: null,
            error: ($frontmatter['error'] ?? false) ? 'Error occurred' : null,
            timestamp: new \DateTimeImmutable($frontmatter['timestamp'] ?? 'now'),
        );
    }

    private function formatBugReport(BugReport $bug): string
    {
        $yaml = "---\n";
        $yaml .= "type: bug_report\n";
        $yaml .= "id: {$bug->id}\n";
        $yaml .= "error_type: {$bug->errorType}\n";
        $yaml .= "timestamp: {$bug->timestamp->format('c')}\n";
        $yaml .= "resolved: " . ($bug->resolved ? 'true' : 'false') . "\n";

        if ($bug->resolution !== null) {
            $yaml .= "resolution: {$bug->resolution}\n";
        }

        $yaml .= "---\n\n";

        $yaml .= "## Error Message\n\n";
        $yaml .= "```\n{$bug->errorMessage}\n```\n\n";

        $yaml .= "## Stack Trace\n\n";
        $yaml .= "```\n{$bug->stackTrace}\n```\n\n";

        if (!empty($bug->context)) {
            $yaml .= "## Context\n\n";
            $yaml .= "```json\n";
            $yaml .= json_encode($bug->context, JSON_PRETTY_PRINT);
            $yaml .= "\n```\n";
        }

        return $yaml;
    }

    private function parseBugReport(string $filepath): BugReport
    {
        $content = file_get_contents($filepath);
        $parsed = $this->parser->parse($content);
        $frontmatter = $parsed['frontmatter'];

        // Extract error message and stack trace from body
        $body = $parsed['body'];
        $errorMessage = '';
        $stackTrace = '';

        if (preg_match('/## Error Message\n\n```\n(.*?)\n```/s', $body, $matches)) {
            $errorMessage = $matches[1];
        }

        if (preg_match('/## Stack Trace\n\n```\n(.*?)\n```/s', $body, $matches)) {
            $stackTrace = $matches[1];
        }

        return new BugReport(
            id: $frontmatter['id'] ?? basename($filepath, '.md'),
            errorType: $frontmatter['error_type'] ?? 'Unknown',
            errorMessage: $errorMessage,
            stackTrace: $stackTrace,
            context: [],
            timestamp: new \DateTimeImmutable($frontmatter['timestamp'] ?? 'now'),
            resolution: $frontmatter['resolution'] ?? null,
            resolved: $frontmatter['resolved'] ?? false,
        );
    }

    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    }

    private function formatLearningEntry(LearningEntry $entry, string $id): string
    {
        $yaml = "---\n";
        $yaml .= "type: learning_entry\n";
        $yaml .= "id: {$id}\n";
        $yaml .= "context: {$entry->context}\n";
        $yaml .= "worked_well: " . ($entry->workedWell ? 'true' : 'false') . "\n";
        $yaml .= "timestamp: {$entry->timestamp->format('c')}\n";

        if (!empty($entry->tags)) {
            $yaml .= "tags:\n";
            foreach ($entry->tags as $tag) {
                $yaml .= "  - {$tag}\n";
            }
        }

        $yaml .= "---\n\n";
        $yaml .= "## Observation\n\n";
        $yaml .= "{$entry->observation}\n";

        return $yaml;
    }

    private function parseLearningEntry(string $filepath): LearningEntry
    {
        $content = file_get_contents($filepath);
        $parsed = $this->parser->parse($content);
        $frontmatter = $parsed['frontmatter'];

        return new LearningEntry(
            context: $frontmatter['context'] ?? 'unknown',
            observation: $this->extractObservation($parsed['body']),
            workedWell: $frontmatter['worked_well'] ?? false,
            tags: $frontmatter['tags'] ?? [],
            id: $frontmatter['id'] ?? basename($filepath, '.md'),
            timestamp: new \DateTimeImmutable($frontmatter['timestamp'] ?? 'now'),
        );
    }

    private function extractObservation(string $body): string
    {
        if (preg_match('/## Observation\n\n(.*)/s', $body, $matches)) {
            return trim($matches[1]);
        }

        return trim($body);
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0750, true);
        }
    }
}
