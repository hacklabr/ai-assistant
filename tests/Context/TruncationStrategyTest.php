<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Context;

use HackLab\AIAssistant\Context\CondensedContext;
use HackLab\AIAssistant\Context\ContextCondenserInterface;
use HackLab\AIAssistant\Context\Strategies\TruncationStrategy;
use HackLab\AIAssistant\Utils\TokenEstimator;
use NeuronAI\Chat\Messages\UserMessage;
use PHPUnit\Framework\TestCase;

class TruncationStrategyTest extends TestCase
{
    public function testTruncatesWhenOverLimit(): void
    {
        $strategy = new TruncationStrategy(new TokenEstimator());

        $messages = [
            new UserMessage('First message'),
            new UserMessage('Second message'),
            new UserMessage('Third message'),
        ];

        $result = $strategy->condense($messages, 'test', 5);

        $this->assertInstanceOf(CondensedContext::class, $result);
        $this->assertLessThanOrEqual(count($messages), count($result->messages));
        $this->assertSame('truncation', $result->strategy);
    }

    public function testKeepsAllMessagesWhenUnderLimit(): void
    {
        $strategy = new TruncationStrategy(new TokenEstimator());

        $messages = [
            new UserMessage('Short'),
        ];

        $result = $strategy->condense($messages, 'test', 1000);

        $this->assertCount(1, $result->messages);
    }
}
