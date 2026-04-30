<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\SubAgents;

use HackLab\AIAssistant\Context\ContextCondenserInterface;
use HackLab\AIAssistant\Context\Strategies\HierarchicalStrategy;
use HackLab\AIAssistant\Skills\SkillRegistry;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;

/**
 * Orchestrates delegation to sub-agents with context condensation.
 */
class SubAgentDispatcher
{
    public function __construct(
        private readonly SubAgentRegistry $registry,
        private readonly SubAgentFactory $factory,
        private readonly ContextCondenserInterface $condenser,
    ) {}

    /**
     * Delegate a message to a sub-agent.
     *
     * @param Message[] $currentMessages
     */
    public function delegate(
        string $subAgentId,
        UserMessage $message,
        array $currentMessages = []
    ): SubAgentResult {
        $startTime = microtime(true);

        // Get sub-agent config
        $config = $this->registry->get($subAgentId);

        // Condense context
        $condensed = $this->condenser->condense(
            $currentMessages,
            $message->getContent() ?? '',
            $config->contextWindow,
            $config->contextStrategy
        );

        // Create sub-agent with condensed history
        $agent = $this->factory->createWithHistory($config, $condensed->toMessages());

        // Execute
        $state = $agent->chat($message)->run();

        $duration = microtime(true) - $startTime;

        return new SubAgentResult(
            message: $state->getMessage(),
            state: $state,
            context: $condensed,
            tokenUsage: $this->estimateTokenUsage($state),
            duration: $duration,
        );
    }

    private function estimateTokenUsage(\NeuronAI\Agent\AgentState $state): int
    {
        // This is a rough estimate
        $total = 0;
        foreach ($state->getSteps() as $message) {
            $total += (int) ceil(strlen($message->getContent() ?? '') / 4);
        }
        return $total;
    }
}
