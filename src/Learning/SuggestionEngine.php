<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Learning;

/**
 * Suggests tools and approaches based on historical learning data.
 */
class SuggestionEngine
{
    public function __construct(
        private readonly ToolLearner $learner,
        private readonly BugCollector $bugs,
    ) {}

    /**
     * Suggest the best tools for a task.
     *
     * @param array<string, string> $availableTools Tool name => description
     * @return array<int, array{tool: string, confidence: float, reason: string}>
     */
    public function suggestTools(string $taskDescription, array $availableTools): array
    {
        $suggestions = $this->learner->suggestTools($taskDescription);

        // Filter to only available tools and add reasoning
        $result = [];
        foreach ($suggestions as $suggestion) {
            $toolName = $suggestion['tool'];
            if (isset($availableTools[$toolName])) {
                $successRate = $this->learner->getSuccessRate($toolName);
                $result[] = [
                    'tool' => $toolName,
                    'confidence' => $suggestion['confidence'],
                    'reason' => "Success rate: " . round($successRate * 100, 1) . "%",
                ];
            }
        }

        return $result;
    }

    /**
     * Warn about known issues with a tool/approach.
     *
     * @param array<string, mixed> $arguments
     * @return string[]
     */
    public function getWarnings(string $toolName, array $arguments): array
    {
        $warnings = [];
        $patterns = $this->learner->findPatterns("Using {$toolName}", 10);

        foreach ($patterns as $pattern) {
            if ($pattern->error !== null) {
                $warnings[] = "Known issue: {$pattern->error}";
            }
        }

        return array_unique(array_slice($warnings, 0, 3));
    }

    /**
     * Provide tips based on past successes.
     *
     * @return string[]
     */
    public function getTips(string $taskDescription): array
    {
        $tips = [];
        $patterns = $this->learner->findPatterns($taskDescription, 5);

        foreach ($patterns as $pattern) {
            if ($pattern->error === null && !empty($pattern->context)) {
                $contextSummary = json_encode($pattern->context);
                if (strlen($contextSummary) > 50) {
                    $contextSummary = substr($contextSummary, 0, 50) . '...';
                }
                $tips[] = "Successful pattern with context: {$contextSummary}";
            }
        }

        return array_slice($tips, 0, 3);
    }
}
