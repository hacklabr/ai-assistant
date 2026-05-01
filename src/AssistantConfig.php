<?php

declare(strict_types=1);

namespace HackLab\AIAssistant;

use HackLab\AIAssistant\Persistence\StorageInterface;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Configuration Data Transfer Object for the Assistant.
 */
class AssistantConfig
{
    /**
     * @param string[] $tools
     * @param array<string, SubAgentConfig> $subAgents
     * @param string[] $skills
     * @param array<int, array<string, mixed>> $mcps
     * @param array<int, mixed> $middleware
     */
    public function __construct(
        public readonly AIProviderInterface $provider,
        public readonly string $instructions = '',
        public readonly int $contextWindow = 200000,
        public readonly array $tools = [],
        public readonly array $subAgents = [],
        public readonly array $skills = [],
        public readonly array $mcps = [],
        public readonly ?string $skillsPath = null,
        public readonly ?StorageInterface $storage = null,
        public readonly ?string $storagePath = null,
        public readonly bool $autoLearn = false,
        public readonly ?string $learningPath = null,
        public readonly bool $autoDelegate = true,
        public readonly bool $requireLearningCheck = true,
        public readonly array $middleware = [],
    ) {}
}
