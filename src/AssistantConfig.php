<?php

declare(strict_types=1);

namespace HackLab\AIAssistant;

use HackLab\AIAssistant\Persistence\StorageInterface;
use HackLab\AIAssistant\SubAgents\SubAgentConfig;
use NeuronAI\Providers\AIProviderInterface;
use Psr\Log\LoggerInterface;

class AssistantConfig
{
    /**
     * @param array<int, string|object> $tools Class names or ToolInterface instances
     * @param array<string, SubAgentConfig> $subAgents
     * @param string[] $skills
     * @param array<int, array<string, mixed>> $mcps
     * @param array<int, mixed> $middleware
     */
    public function __construct(
        public readonly AIProviderInterface $provider,
        public readonly StorageInterface $storage,
        public readonly string $instructions = '',
        public readonly int $contextWindow = 200000,
        public readonly array $tools = [],
        public readonly array $subAgents = [],
        public readonly array $skills = [],
        public readonly array $mcps = [],
        public readonly ?string $skillsPath = null,
        public readonly bool $autoLearn = false,
        public readonly bool $autoDelegate = true,
        public readonly bool $requireLearningCheck = true,
        public readonly array $middleware = [],
        public readonly ?LoggerInterface $logger = null,
        public readonly ?string $userId = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->contextWindow < 1000) {
            throw new \InvalidArgumentException('contextWindow must be at least 1000 tokens.');
        }

        foreach ($this->subAgents as $id => $subConfig) {
            if (!$subConfig instanceof SubAgentConfig) {
                throw new \InvalidArgumentException("Sub-agent '{$id}' must be an instance of SubAgentConfig.");
            }
        }
    }
}
