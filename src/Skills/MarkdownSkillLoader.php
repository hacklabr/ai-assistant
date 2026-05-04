<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Skills;

use HackLab\AIAssistant\Utils\MarkdownParser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MarkdownSkillLoader
{
    private const array PROMPT_INJECTION_PATTERNS = [
        '/ignore\s+(previous|above|all)\s+(instructions|rules)/i',
        '/forget\s+(everything|all|previous)/i',
        '/you\s+are\s+now\s+/i',
        '/new\s+instructions?\s*:/i',
        '/system\s*prompt\s*:/i',
        '/\badmin\b.*\baccess\b/i',
        '/override\s+(safety|security|restrictions)/i',
    ];

    public function __construct(
        private readonly MarkdownParser $parser = new MarkdownParser(),
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $maxFileSize = 65536,
    ) {}

    public function load(string $filePath): Skill
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Skill file not found: {$filePath}");
        }

        $this->validateFilePath($filePath);

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read skill file: {$filePath}");
        }

        if (strlen($content) > $this->maxFileSize) {
            throw new \RuntimeException(
                "Skill file exceeds maximum size of {$this->maxFileSize} bytes: {$filePath}"
            );
        }

        $parsed = $this->parser->parse($content);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        $this->validateContent($body, $filePath);

        return new Skill(
            name: $frontmatter['name'] ?? basename($filePath, '.md'),
            description: $frontmatter['description'] ?? '',
            content: $body,
            tools: $frontmatter['tools'] ?? [],
            contextStrategy: $frontmatter['context_strategy'] ?? null,
            categories: $frontmatter['categories'] ?? [],
            version: $frontmatter['version'] ?? null,
            author: $frontmatter['author'] ?? null,
            sourceFile: $filePath,
        );
    }

    public function loadDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $skills = [];
        $files = glob($directory . '/*.md');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            try {
                $skills[] = $this->load($file);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to load skill file', [
                    'file' => $file,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $skills;
    }

    private function validateFilePath(string $filePath): void
    {
        $realPath = realpath($filePath);

        if ($realPath === false) {
            throw new \InvalidArgumentException("Invalid skill file path: {$filePath}");
        }

        if (!str_ends_with(strtolower($realPath), '.md')) {
            throw new \InvalidArgumentException("Skill file must have .md extension: {$filePath}");
        }
    }

    private function validateContent(string $body, string $filePath): void
    {
        foreach (self::PROMPT_INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $body)) {
                $this->logger->warning('Skill file contains suspicious prompt injection pattern', [
                    'file' => $filePath,
                    'pattern' => $pattern,
                ]);

                throw new \RuntimeException(
                    "Skill file contains suspicious content that resembles a prompt injection attempt: {$filePath}"
                );
            }
        }
    }
}
