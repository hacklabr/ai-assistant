<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Learning;

use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use HackLab\AIAssistant\Learning\Storage\ToolPattern;
use NeuronAI\Tools\ToolInterface;

/**
 * Records tool usage patterns for learning.
 */
class ToolLearner
{
    public function __construct(
        private readonly KnowledgeBase $storage,
    ) {}

    /**
     * Record a tool execution.
     *
     * @param array<string, mixed> $arguments
     * @param mixed $result
     * @param array<string, mixed> $context
     */
    public function record(
        ToolInterface $tool,
        array $arguments,
        mixed $result,
        ?\Throwable $error = null,
        array $context = [],
    ): void {
        $pattern = new ToolPattern(
            toolName: $tool->getName(),
            arguments: $arguments,
            result: $result,
            error: $error?->getMessage(),
            context: $context,
        );

        $this->storage->saveToolPattern($pattern);
    }

    /**
     * Get success rate for a tool (0.0 - 1.0).
     */
    public function getSuccessRate(string $toolName): float
    {
        $patterns = $this->storage->getToolPatterns($toolName);

        if (empty($patterns)) {
            return 0.0;
        }

        $successes = count(array_filter($patterns, fn (ToolPattern $p) => $p->error === null));
        return $successes / count($patterns);
    }

    /**
     * Find similar successful patterns for a task.
     *
     * @return ToolPattern[]
     */
    public function findPatterns(string $taskDescription, int $limit = 5): array
    {
        $allPatterns = [];

        foreach ($this->storage->getToolNames() as $toolName) {
            $patterns = $this->storage->getToolPatterns($toolName);
            foreach ($patterns as $pattern) {
                $score = $pattern->matches($taskDescription);
                if ($score > 0.3) {
                    $allPatterns[] = ['pattern' => $pattern, 'score' => $score];
                }
            }
        }

        // Sort by score descending
        usort($allPatterns, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        // Return top patterns
        return array_slice(array_column($allPatterns, 'pattern'), 0, $limit);
    }

    /**
     * Suggest tools based on historical patterns.
     *
     * @return array<int, array{tool: string, confidence: float}>
     */
    public function suggestTools(string $taskDescription): array
    {
        $suggestions = [];

        foreach ($this->storage->getToolNames() as $toolName) {
            $patterns = $this->storage->getToolPatterns($toolName);
            $totalScore = 0.0;
            $matchCount = 0;

            foreach ($patterns as $pattern) {
                $score = $pattern->matches($taskDescription);
                if ($score > 0.3) {
                    $totalScore += $score;
                    $matchCount++;
                }
            }

            if ($matchCount > 0) {
                $avgScore = $totalScore / $matchCount;
                $successRate = $this->getSuccessRate($toolName);
                $confidence = ($avgScore * 0.7) + ($successRate * 0.3);

                $suggestions[] = [
                    'tool' => $toolName,
                    'confidence' => round($confidence, 2),
                ];
            }
        }

        // Sort by confidence descending
        usort($suggestions, fn (array $a, array $b) => $b['confidence'] <=> $a['confidence']);

        return $suggestions;
    }
}
