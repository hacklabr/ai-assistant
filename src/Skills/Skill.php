<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Skills;

/**
 * Skill entity representing a reusable instruction module.
 */
class Skill
{
    /**
     * @param string[] $tools
     * @param string[] $categories
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $content,
        public readonly array $tools = [],
        public readonly ?string $contextStrategy = null,
        public readonly array $categories = [],
        public readonly ?string $version = null,
        public readonly ?string $author = null,
        public readonly ?string $sourceFile = null,
    ) {}

    /**
     * Generate system prompt addition from this skill.
     */
    public function toSystemPrompt(): string
    {
        $prompt = "## Skill: {$this->name}\n\n";
        $prompt .= "{$this->description}\n\n";
        $prompt .= $this->content;

        if (!empty($this->tools)) {
            $prompt .= "\n\n## Recommended Tools\n";
            foreach ($this->tools as $tool) {
                $prompt .= "- {$tool}\n";
            }
        }

        return $prompt;
    }
}
