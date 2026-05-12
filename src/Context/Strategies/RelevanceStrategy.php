<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Context\Strategies;

use HackLab\AIAssistant\Context\CondensedContext;
use HackLab\AIAssistant\Context\ContextCondenserInterface;
use HackLab\AIAssistant\Context\RelevanceScorer;
use HackLab\AIAssistant\Utils\TokenEstimator;
use NeuronAI\Chat\Messages\Message;

/**
 * Extracts messages matching keywords/patterns relevant to the task.
 * Pure PHP implementation with zero dependencies.
 */
class RelevanceStrategy implements ContextCondenserInterface
{
    private readonly RelevanceScorer $scorer;
    private readonly TokenEstimator $tokenEstimator;

    public function __construct(
        ?TokenEstimator $tokenEstimator = null,
        ?RelevanceScorer $scorer = null,
    ) {
        $this->tokenEstimator = $tokenEstimator ?? new TokenEstimator();
        $this->scorer = $scorer ?? new RelevanceScorer(RelevanceScorer::getDefaultStrategies());
    }

    public function condense(
        array $messages,
        string $taskDescription,
        int $maxTokens,
        ?string $contextStrategy = null
    ): CondensedContext {
        $originalTokens = $this->tokenEstimator->estimateMessages(
            array_map(fn (Message $m) => ['content' => $m->getContent() ?? ''], $messages)
        );

        // Score all messages
        $scoredMessages = [];
        foreach ($messages as $message) {
            $score = $this->scorer->score(
                $message->getContent() ?? '',
                $taskDescription,
                $contextStrategy
            );
            $scoredMessages[] = ['message' => $message, 'score' => $score];
        }

        // Sort by score descending
        usort($scoredMessages, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        // Keep highest scoring messages within token limit
        $condensed = [];
        $currentTokens = 0;
        $keyFacts = [];

        foreach ($scoredMessages as $item) {
            $message = $item['message'];
            $messageTokens = $this->tokenEstimator->estimate($message->getContent() ?? '');

            if ($currentTokens + $messageTokens > $maxTokens && count($condensed) > 0) {
                break;
            }

            // Add highest scoring facts
            if ($item['score'] > 0.5 && count($keyFacts) < 10) {
                $keyFacts[] = $message->getContent() ?? '';
            }

            $condensed[] = $message;
            $currentTokens += $messageTokens;
        }

        // Sort back to original order
        usort($condensed, function (Message $a, Message $b) use ($messages) {
            $aIndex = array_search($a, $messages, true);
            $bIndex = array_search($b, $messages, true);
            return $aIndex <=> $bIndex;
        });

        return new CondensedContext(
            messages: $condensed,
            keyFacts: $keyFacts,
            originalTokens: $originalTokens,
            condensedTokens: $currentTokens,
            strategy: 'relevance',
        );
    }
}
