<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Persistence;

use NeuronAI\Chat\History\AbstractChatHistory;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Chat history with hierarchical memory management.
 * Maintains: summary + recent messages + key facts.
 */
class HierarchicalChatHistory extends AbstractChatHistory
{
    private ?string $summary = null;
    private array $keyFacts = [];
    private ?AIProviderInterface $summarizationProvider = null;

    public function __construct(
        int $contextWindow = 150000,
        private readonly int $summaryThreshold = 10000,
        private readonly int $recentMessages = 5,
        ?AIProviderInterface $summarizationProvider = null,
    ) {
        parent::__construct($contextWindow);
        $this->summarizationProvider = $summarizationProvider;
    }

    /**
     * Trigger summarization of old messages.
     */
    public function summarize(): void
    {
        if ($this->summarizationProvider === null) {
            return;
        }

        $messages = $this->getMessages();
        if (count($messages) <= $this->recentMessages + 2) {
            return;
        }

        $messagesToSummarize = array_slice($messages, 0, -$this->recentMessages);

        $conversation = '';
        foreach ($messagesToSummarize as $message) {
            $role = $message->getRole()->value ?? 'unknown';
            $content = $message->getContent() ?? '';
            $conversation .= "{$role}: {$content}\n\n";
        }

        $prompt = "Please provide a brief summary of the following conversation. Focus on key topics and decisions:\n\n{$conversation}";

        try {
            $response = $this->summarizationProvider->chat([
                new UserMessage($prompt),
            ]);
            $this->summary = $response->getContent() ?? null;
        } catch (\Throwable $e) {
            // Silently fail - summary is optional
        }
    }

    /**
     * Extract and store key facts from messages.
     */
    public function extractFacts(): void
    {
        $messages = $this->getMessages();
        $this->keyFacts = [];

        foreach ($messages as $message) {
            $content = $message->getContent() ?? '';

            // Simple fact extraction based on patterns
            if (preg_match_all('/(?:decided|agreed|noted|important|key)\s+(?:that|to|is|:)\s*([^\.\n]+)/i', $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $fact = trim($match);
                    if (strlen($fact) > 10 && !in_array($fact, $this->keyFacts, true)) {
                        $this->keyFacts[] = $fact;
                    }
                }
            }
        }

        // Limit facts
        $this->keyFacts = array_slice($this->keyFacts, 0, 20);
    }

    /**
     * Get messages with hierarchy applied.
     *
     * @return Message[]
     */
    public function getMessages(): array
    {
        $messages = parent::getMessages();

        // If we have a summary, prepend it
        if ($this->summary !== null) {
            $summaryMessage = new UserMessage("[Previous Context Summary]\n\n{$this->summary}");
            array_unshift($messages, $summaryMessage);
        }

        // If we have key facts, prepend them
        if (!empty($this->keyFacts)) {
            $factsText = "[Key Facts]\n" . implode("\n", array_map(fn ($f) => "- {$f}", $this->keyFacts));
            $factsMessage = new UserMessage($factsText);
            array_unshift($messages, $factsMessage);
        }

        return $messages;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    /**
     * @return string[]
     */
    public function getKeyFacts(): array
    {
        return $this->keyFacts;
    }

    public function setSummarizationProvider(AIProviderInterface $provider): self
    {
        $this->summarizationProvider = $provider;
        return $this;
    }
}
