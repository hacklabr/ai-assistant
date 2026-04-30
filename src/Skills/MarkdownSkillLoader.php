<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Skills;

use HackLab\AIAssistant\Utils\MarkdownParser;

/**
 * Loads skills from Markdown files with YAML frontmatter.
 */
class MarkdownSkillLoader
{
    public function __construct(
        private readonly MarkdownParser $parser = new MarkdownParser(),
    ) {}

    /**
     * Parse a skill from a .md file.
     */
    public function load(string $filePath): Skill
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Skill file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read skill file: {$filePath}");
        }

        $parsed = $this->parser->parse($content);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

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

    /**
     * Load all skills from a directory.
     *
     * @return Skill[]
     */
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
                // Skip invalid skill files
                continue;
            }
        }

        return $skills;
    }
}
