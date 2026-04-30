<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Context;

use NeuronAI\Chat\Messages\Message;

class CondensedContext
{
    /**
     * @param Message[] $messages
     * @param string|null $summary
     * @param string[] $keyFacts
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $summary = null,
        public readonly array $keyFacts = [],
        public readonly int $originalTokens = 0,
        public readonly int $condensedTokens = 0,
        public readonly string $strategy = 'unknown',
    ) {}

    /**
     * @return Message[]
     */
    public function toMessages(): array
    {
        return $this->messages;
    }
}
