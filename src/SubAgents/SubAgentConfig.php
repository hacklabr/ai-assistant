<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\SubAgents;

use HackLab\AIAssistant\Skills\SkillRegistry;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Data Transfer Object defining a sub-agent configuration.
 */
class SubAgentConfig
{
    /**
     * @param string[] $tools
     * @param string[] $skills
     * @param array<int, array<string, mixed>> $mcps
     * @param array<int, mixed> $middleware
     */
    public function __construct(
        public readonly string $id,
        public readonly AIProviderInterface $provider,
        public readonly string $instructions,
        public readonly array $tools = [],
        public readonly array $skills = [],
        public readonly string $contextStrategy = 'default',
        public readonly int $contextWindow = 150000,
        public readonly array $mcps = [],
        public readonly array $middleware = [],
    ) {}

    /**
     * Build system prompt including skills.
     */
    public function buildSystemPrompt(SkillRegistry $skillRegistry): string
    {
        $prompt = $this->instructions;

        foreach ($this->skills as $skillName) {
            $skill = $skillRegistry->get($skillName);
            if ($skill !== null) {
                $prompt .= "\n\n" . $skill->toSystemPrompt();
            }
        }

        return $prompt;
    }
}
