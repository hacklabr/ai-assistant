<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Context\Strategies;

use HackLab\AIAssistant\Context\CondensedContext;
use HackLab\AIAssistant\Context\ContextCondenserInterface;
use HackLab\AIAssistant\Context\RelevanceScorer;
use HackLab\AIAssistant\Utils\SensitiveDataRedactor;
use HackLab\AIAssistant\Utils\TokenEstimator;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Maintains three levels of context:
 * 1. Summary of older conversation
 * 2. Recent messages (full content)
 * 3. Extracted key facts
 */
class HierarchicalStrategy implements ContextCondenserInterface
{
    private readonly TokenEstimator $tokenEstimator;
    private readonly SensitiveDataRedactor $redactor;

    public function __construct(
        private readonly ?AIProviderInterface $summarizationProvider = null,
        private readonly int $recentMessages = 5,
        private readonly int $summaryThreshold = 8000,
        ?TokenEstimator $tokenEstimator = null,
        private readonly ?RelevanceScorer $relevanceScorer = null,
        ?SensitiveDataRedactor $redactor = null,
    ) {
        $this->tokenEstimator = $tokenEstimator ?? new TokenEstimator();
        $this->redactor = $redactor ?? new SensitiveDataRedactor();
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

        // If under threshold, return as-is
        if ($originalTokens <= $this->summaryThreshold || count($messages) <= $this->recentMessages + 2) {
            return new CondensedContext(
                messages: $messages,
                originalTokens: $originalTokens,
                condensedTokens: $originalTokens,
                strategy: 'hierarchical',
            );
        }

        // Extract recent messages
        $recent = array_slice($messages, -$this->recentMessages);
        $older = array_slice($messages, 0, -$this->recentMessages);

        // Generate summary of older messages
        $summary = null;
        if ($this->summarizationProvider !== null) {
            $summary = $this->summarizeMessages($older, $taskDescription);
        }

        // Extract key facts using relevance scoring
        $keyFacts = $this->extractKeyFacts($older, $taskDescription, $contextStrategy);

        // Build condensed context
        $condensed = [];

        // Add summary if available
        if ($summary !== null) {
            $condensed[] = new UserMessage("[Previous Context Summary]\n\n{$summary}");
        }

        // Add key facts if available
        if (!empty($keyFacts)) {
            $factsText = "[Key Facts]\n" . implode("\n", array_map(fn ($f) => "- {$f}", $keyFacts));
            $condensed[] = new UserMessage($factsText);
        }

        // Add recent messages
        $condensed = array_merge($condensed, $recent);

        $condensedTokens = $this->tokenEstimator->estimateMessages(
            array_map(fn (Message $m) => ['content' => $m->getContent() ?? ''], $condensed)
        );

        return new CondensedContext(
            messages: $condensed,
            summary: $summary,
            keyFacts: $keyFacts,
            originalTokens: $originalTokens,
            condensedTokens: $condensedTokens,
            strategy: 'hierarchical',
        );
    }

    /**
     * @param Message[] $messages
     */
    private function summarizeMessages(array $messages, string $taskDescription): ?string
    {
        if ($this->summarizationProvider === null) {
            return null;
        }

        $conversation = '';
        foreach ($messages as $message) {
            $role = $message->getRole();
            $content = $this->redactor->redact($message->getContent() ?? '');
            $conversation .= "{$role}: {$content}\n\n";
        }

        $prompt = "Please provide a comprehensive summary of the following conversation. Extract key topics, decisions, action items, and critical information.\n\nTask context: {$taskDescription}\n\nConversation:\n\n{$conversation}";

        try {
            $response = $this->summarizationProvider->chat(
                new UserMessage($prompt),
            );
            return $response->getContent() ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param Message[] $messages
     * @return string[]
     */
    private function extractKeyFacts(array $messages, string $taskDescription, ?string $contextStrategy): array
    {
        $scorer = $this->relevanceScorer ?? new RelevanceScorer(RelevanceScorer::getDefaultStrategies());
        $facts = [];

        foreach ($messages as $message) {
            $score = $scorer->score($message->getContent() ?? '', $taskDescription, $contextStrategy);
            if ($score > 0.6) {
                $content = $message->getContent() ?? '';
                if (strlen($content) > 200) {
                    $content = substr($content, 0, 200) . '...';
                }
                $facts[] = $content;
            }
        }

        // Limit to top 10 facts
        return array_slice($facts, 0, 10);
    }
}
