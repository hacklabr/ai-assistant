<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Context;

use NeuronAI\Chat\Messages\Message;

interface ContextCondenserInterface
{
    /**
     * Condense messages for a specific task/target.
     *
     * @param Message[] $messages Full conversation history
     * @param string $taskDescription Description of the task being delegated
     * @param int $maxTokens Maximum tokens allowed for the sub-agent
     * @param string|null $contextStrategy Strategy name (e.g., 'code-focused')
     */
    public function condense(
        array $messages,
        string $taskDescription,
        int $maxTokens,
        ?string $contextStrategy = null
    ): CondensedContext;
}
