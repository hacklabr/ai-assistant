<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools;

use HackLab\AIAssistant\SubAgents\SubAgentDispatcher;
use HackLab\AIAssistant\SubAgents\SubAgentRegistry;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * Tool that allows the main assistant to auto-delegate tasks to sub-agents.
 *
 * When registered, the LLM can decide to invoke this tool to send a task
 * to a specialized sub-agent, along with condensed conversation context.
 */
class DelegateTool extends Tool
{
    /**
     * @param \Closure(): array<Message> $getCurrentMessages Returns current chat history
     */
    public function __construct(
        private readonly SubAgentDispatcher $dispatcher,
        private readonly SubAgentRegistry $registry,
        private readonly \Closure $getCurrentMessages,
    ) {
        parent::__construct(
            name: 'delegate_to_subagent',
            description: $this->buildDescription(),
        );
    }

    protected function properties(): array
    {
        $subAgentIds = array_keys($this->registry->all());

        return [
            new ToolProperty(
                name: 'sub_agent_id',
                type: PropertyType::STRING,
                description: 'The ID of the sub-agent to delegate to.',
                required: true,
                enum: $subAgentIds,
            ),
            new ToolProperty(
                name: 'task',
                type: PropertyType::STRING,
                description: 'The specific task or question to delegate. Be concise but complete.',
                required: true,
            ),
            new ToolProperty(
                name: 'reason',
                type: PropertyType::STRING,
                description: 'Brief explanation of why this sub-agent is the right choice.',
                required: false,
            ),
        ];
    }

    public function __invoke(string $sub_agent_id, string $task, ?string $reason = null): string
    {
        if (!$this->registry->has($sub_agent_id)) {
            $available = implode(', ', array_keys($this->registry->all()));
            return "Error: Sub-agent '{$sub_agent_id}' not found. Available: {$available}";
        }

        $currentMessages = ($this->getCurrentMessages)();

        $result = $this->dispatcher->delegate(
            $sub_agent_id,
            new UserMessage($task),
            $currentMessages
        );

        return $result->getContent();
    }

    private function buildDescription(): string
    {
        $lines = [
            'Delegate a specialized task to a sub-agent with the right expertise.',
            'Use this when the user request requires skills, knowledge, or tools that the main assistant does not have.',
            '',
            'Available sub-agents:',
        ];

        foreach ($this->registry->all() as $id => $config) {
            $lines[] = "- {$id}: {$config->instructions}";
        }

        $lines[] = '';
        $lines[] = 'When delegating, provide a clear, self-contained task description and relevant context from the conversation.';

        return implode("\n", $lines);
    }
}
