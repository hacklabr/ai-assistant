<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Context\Strategies;

use HackLab\AIAssistant\Context\CondensedContext;
use HackLab\AIAssistant\Context\ContextCondenserInterface;
use HackLab\AIAssistant\Utils\TokenEstimator;
use NeuronAI\Chat\Messages\Message;

/**
 * Simple token-based truncation strategy.
 * Removes oldest messages until under token limit.
 */
class TruncationStrategy implements ContextCondenserInterface
{
    public function __construct(
        private readonly TokenEstimator $tokenEstimator = new TokenEstimator(),
    ) {}

    public function condense(
        array $messages,
        string $taskDescription,
        int $maxTokens,
        ?string $contextStrategy = null
    ): CondensedContext {
        $originalTokens = $this->tokenEstimator->estimateMessages(
            array_map(fn (Message $m) => ['content' => $m->getContent() ?? ''], $messages)
        );

        $condensed = [];
        $currentTokens = 0;

        // Keep messages from newest to oldest until we hit the limit
        $reversed = array_reverse($messages);
        foreach ($reversed as $message) {
            $messageTokens = $this->tokenEstimator->estimate($message->getContent() ?? '');

            if ($currentTokens + $messageTokens > $maxTokens && count($condensed) > 0) {
                break;
            }

            array_unshift($condensed, $message);
            $currentTokens += $messageTokens;
        }

        return new CondensedContext(
            messages: $condensed,
            originalTokens: $originalTokens,
            condensedTokens: $currentTokens,
            strategy: 'truncation',
        );
    }
}
