<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\SubAgents;

use HackLab\AIAssistant\Context\ContextCondenserInterface;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\WorkflowState;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SubAgentDispatcher
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly SubAgentRegistry $registry,
        private readonly SubAgentFactory $factory,
        private readonly ContextCondenserInterface $condenser,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function delegate(
        string $subAgentId,
        UserMessage $message,
        array $currentMessages = []
    ): SubAgentResult {
        $startTime = microtime(true);

        $config = $this->registry->get($subAgentId);

        $taskDescription = sprintf(
            "Task for sub-agent '%s' (%s): %s",
            $config->id,
            $config->instructions,
            $message->getContent() ?? ''
        );

        $condensed = $this->condenser->condense(
            $currentMessages,
            $taskDescription,
            $config->contextWindow,
            $config->contextStrategy
        );

        $this->logger->info('Context condensed for sub-agent', [
            'sub_agent' => $subAgentId,
            'original_tokens' => $condensed->originalTokens,
            'condensed_tokens' => $condensed->condensedTokens,
            'strategy' => $condensed->strategy,
            'reduction_pct' => $condensed->originalTokens > 0
                ? round((1 - $condensed->condensedTokens / $condensed->originalTokens) * 100, 1)
                : 0,
        ]);

        $agent = $this->factory->createWithHistory($config, $condensed->toMessages());

        $handler = $agent->chat($message);
        $state = $handler->run();

        $duration = microtime(true) - $startTime;

        $this->logger->info('Sub-agent execution completed', [
            'sub_agent' => $subAgentId,
            'duration_s' => round($duration, 3),
        ]);

        $responseMessage = $handler->getMessage();

        return new SubAgentResult(
            message: $responseMessage,
            state: $state,
            context: $condensed,
            tokenUsage: $this->estimateTokenUsage($state),
            duration: $duration,
        );
    }

    private function estimateTokenUsage(WorkflowState $state): int
    {
        $total = 0;
        foreach ($state->all() as $value) {
            if (is_string($value)) {
                $total += (int) ceil(strlen($value) / 4);
            }
        }
        return $total;
    }
}
