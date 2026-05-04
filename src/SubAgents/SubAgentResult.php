<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\SubAgents;

use HackLab\AIAssistant\Context\CondensedContext;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Workflow\WorkflowState;

class SubAgentResult
{
    public function __construct(
        public readonly Message $message,
        public readonly WorkflowState $state,
        public readonly CondensedContext $context,
        public readonly array $toolCalls = [],
        public readonly int $tokenUsage = 0,
        public readonly float $duration = 0.0,
    ) {}

    public function getContent(): string
    {
        return $this->message->getContent() ?? '';
    }

    public function getSteps(): array
    {
        return $this->state->all();
    }
}
