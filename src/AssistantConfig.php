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
     * @param string|null $outputClass FQCN of a class with #[SchemaProperty] attributes for structured output
     * @param int $structuredMaxRetries Number of retries when structured output validation fails (default: 1)
     * @param StorageInterface|null $conversationStorage Override storage for conversations (falls back to $storage)
     * @param StorageInterface|null $learningStorage Override storage for learning/patterns/bugs (falls back to $storage)
     * @param StorageInterface|null $userMemoryStorage Override storage for user memories (falls back to $storage)
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
        public readonly ?float $requestTimeout = null,
        public readonly ?string $outputClass = null,
        public readonly int $structuredMaxRetries = 1,
        public readonly ?StorageInterface $conversationStorage = null,
        public readonly ?StorageInterface $learningStorage = null,
        public readonly ?StorageInterface $userMemoryStorage = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->contextWindow < 1000) {
            throw new \InvalidArgumentException('contextWindow must be at least 1000 tokens.');
        }

        if ($this->requestTimeout !== null && $this->requestTimeout <= 0) {
            throw new \InvalidArgumentException('requestTimeout must be greater than 0 seconds.');
        }

        foreach ($this->subAgents as $id => $subConfig) {
            if (!$subConfig instanceof SubAgentConfig) {
                throw new \InvalidArgumentException("Sub-agent '{$id}' must be an instance of SubAgentConfig.");
            }
        }

        if ($this->structuredMaxRetries < 0) {
            throw new \InvalidArgumentException('structuredMaxRetries must be 0 or greater.');
        }

        if ($this->outputClass !== null && !class_exists($this->outputClass)) {
            throw new \InvalidArgumentException("outputClass '{$this->outputClass}' does not exist.");
        }
    }
}
