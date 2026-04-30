<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Utils;

/**
 * Simple token estimator based on character count.
 * Uses the heuristic: ~4 characters per token for English text.
 */
class TokenEstimator
{
    private const float CHARS_PER_TOKEN = 4.0;

    public function estimate(string $text): int
    {
        return (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN);
    }

    /**
     * Estimate tokens for an array of messages.
     *
     * @param array<int, array<string, mixed>> $messages
     */
    public function estimateMessages(array $messages): int
    {
        $total = 0;
        foreach ($messages as $message) {
            $content = $message['content'] ?? json_encode($message);
            $total += $this->estimate((string) $content);
        }
        return $total;
    }
}
