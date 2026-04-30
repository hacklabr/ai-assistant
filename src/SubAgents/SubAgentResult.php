<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\SubAgents;

use HackLab\AIAssistant\Context\CondensedContext;
use HackLab\AIAssistant\Context\ContextCondenserInterface;
use HackLab\AIAssistant\Skills\SkillRegistry;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;

/**
 * Result of a sub-agent delegation.
 */
class SubAgentResult
{
    /**
     * @param Message[] $toolCalls
     */
    public function __construct(
        public readonly Message $message,
        public readonly \NeuronAI\Agent\AgentState $state,
        public readonly CondensedContext $context,
        public readonly array $toolCalls = [],
        public readonly int $tokenUsage = 0,
        public readonly float $duration = 0.0,
    ) {}

    /**
     * Get the response content.
     */
    public function getContent(): string
    {
        return $this->message->getContent() ?? '';
    }

    /**
     * Get all steps/messages in the sub-agent execution.
     *
     * @return Message[]
     */
    public function getSteps(): array
    {
        return $this->state->getSteps();
    }
}
