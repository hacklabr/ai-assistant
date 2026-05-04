<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Context;

/**
 * Scores messages for relevance based on keywords and patterns.
 */
class RelevanceScorer
{
    /**
     * @param array<string, array{keywords: string[], patterns: string[]}> $strategies
     */
    public function __construct(
        private readonly array $strategies = [],
    ) {}

    /**
     * Score a message for relevance to a task.
     */
    public function score(string $messageContent, string $taskDescription, ?string $contextStrategy = null): float
    {
        $score = 0.0;
        $messageLower = strtolower($messageContent);
        $taskLower = strtolower($taskDescription);

        // Direct keyword overlap
        $taskWords = str_word_count($taskLower, 1);
        $messageWords = str_word_count($messageLower, 1);
        $commonWords = array_intersect($taskWords, $messageWords);

        if (count($taskWords) > 0) {
            $score += (count($commonWords) / count($taskWords)) * 0.5;
        }

        // Strategy-specific scoring
        if ($contextStrategy !== null && isset($this->strategies[$contextStrategy])) {
            $strategy = $this->strategies[$contextStrategy];

            // Keyword matching
            foreach ($strategy['keywords'] as $keyword) {
                if (str_contains($messageLower, strtolower($keyword))) {
                    $score += 0.1;
                }
            }

            // Pattern matching
            foreach ($strategy['patterns'] as $pattern) {
                if (!$this->isValidRegex($pattern)) {
                    continue;
                }
                if (preg_match($pattern, $messageContent)) {
                    $score += 0.15;
                }
            }
        }

        return min(1.0, $score);
    }

    private function isValidRegex(string $pattern): bool
    {
        set_error_handler(fn() => false);
        $valid = preg_match($pattern, '') !== false;
        restore_error_handler();
        return $valid;
    }

    /**
     * Get default strategies configuration.
     *
     * @return array<string, array{keywords: string[], patterns: string[]}>
     */
    public static function getDefaultStrategies(): array
    {
        return [
            'code-focused' => [
                'keywords' => [
                    'function', 'class', 'method', 'variable', 'return',
                    'bug', 'error', 'fix', 'refactor', 'code', 'file',
                    'namespace', 'import', 'use', 'namespace',
                ],
                'patterns' => [
                    '/```[a-z]*/',
                    '/\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/',
                    '/function\s+\w+/',
                    '/class\s+\w+/',
                    '/namespace\s+/',
                ],
            ],
            'security-focused' => [
                'keywords' => [
                    'sql injection', 'xss', 'csrf', 'vulnerability',
                    'exploit', 'authentication', 'authorization',
                    'password', 'secret', 'token', 'hash', 'encrypt',
                ],
                'patterns' => [
                    '/password\s*[=:]\s*["\']?\w+/i',
                    '/secret\s*[=:]\s*["\']?\w+/i',
                    '/api[_-]?key\s*[=:]\s*["\']?\w+/i',
                ],
            ],
            'architecture-focused' => [
                'keywords' => [
                    'architecture', 'design', 'pattern', 'microservice',
                    'database', 'api', 'service', 'layer', 'component',
                    'module', 'dependency', 'interface',
                ],
                'patterns' => [
                    '/\b(MVC|MVVM|SOLID|DDD|CQRS)\b/i',
                ],
            ],
            'default' => [
                'keywords' => [],
                'patterns' => [],
            ],
        ];
    }
}
