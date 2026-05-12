<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Context\Strategies;

use HackLab\AIAssistant\Context\CondensedContext;
use HackLab\AIAssistant\Context\ContextCondenserInterface;
use HackLab\AIAssistant\Utils\SensitiveDataRedactor;
use HackLab\AIAssistant\Utils\TokenEstimator;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Uses an LLM to summarize old messages when token limit is approached.
 */
class SummarizationStrategy implements ContextCondenserInterface
{
    private readonly TokenEstimator $tokenEstimator;
    private readonly SensitiveDataRedactor $redactor;

    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly int $maxTokens = 10000, // phpstan: ignore property.onlyWritten
        private readonly int $messagesToKeep = 5,
        private readonly string $systemPrompt = 'Please provide a comprehensive summary of the following conversation. Extract the highest quality and most relevant pieces of information, including key topics discussed, important decisions made, critical information exchanged, action items, and any unresolved questions.',
        ?TokenEstimator $tokenEstimator = null,
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

        if (count($messages) <= $this->messagesToKeep) {
            return new CondensedContext(
                messages: $messages,
                originalTokens: $originalTokens,
                condensedTokens: $originalTokens,
                strategy: 'summarization',
            );
        }

        // Split into messages to summarize and messages to keep
        $messagesToKeep = array_slice($messages, -$this->messagesToKeep);
        $messagesToSummarize = array_slice($messages, 0, -$this->messagesToKeep);

        // Generate summary
        $summaryText = $this->summarizeMessages($messagesToSummarize, $taskDescription);
        $summaryMessage = new UserMessage("[Context Summary]\n\n{$summaryText}");

        $condensed = array_merge([$summaryMessage], $messagesToKeep);

        $condensedTokens = $this->tokenEstimator->estimateMessages(
            array_map(fn (Message $m) => ['content' => $m->getContent() ?? ''], $condensed)
        );

        return new CondensedContext(
            messages: $condensed,
            summary: $summaryText,
            originalTokens: $originalTokens,
            condensedTokens: $condensedTokens,
            strategy: 'summarization',
        );
    }

    /**
     * @param Message[] $messages
     */
    private function summarizeMessages(array $messages, string $taskDescription): string
    {
        $conversation = '';
        foreach ($messages as $message) {
            $role = $message->getRole();
            $content = $this->redactor->redact($message->getContent() ?? '');
            $conversation .= "{$role}: {$content}\n\n";
        }

        $prompt = "{$this->systemPrompt}\n\nTask context: {$taskDescription}\n\nConversation to summarize:\n\n{$conversation}";

        $response = $this->provider->chat(
            new UserMessage($prompt),
        );

        return $response->getContent() ?? 'No summary available.';
    }
}
