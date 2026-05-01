<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools;

use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * Find similar issues and anti-patterns in a context.
 */
class FindSimilarIssuesTool extends Tool
{
    public function __construct(
        private readonly KnowledgeBase $knowledgeBase,
    ) {
        parent::__construct(
            name: 'find_similar_issues',
            description: 'Search for known issues, bugs, and anti-patterns in a context before attempting a potentially problematic approach.',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'context',
                type: PropertyType::STRING,
                description: 'The context to search in.',
                required: true,
            ),
            new ToolProperty(
                name: 'problem_description',
                type: PropertyType::STRING,
                description: 'Description of the problem or approach you are considering.',
                required: true,
            ),
        ];
    }

    public function __invoke(
        string $context,
        string $problem_description,
    ): string {
        $learnings = $this->knowledgeBase->getLearnings($context);
        $bugs = $this->knowledgeBase->searchBugs(['context' => $context]);

        $issues = [];

        // Filter learnings that didn't work well
        foreach ($learnings as $entry) {
            if (!$entry->workedWell && $entry->matches($problem_description) > 0.3) {
                $issues[] = [
                    'type' => 'anti-pattern',
                    'description' => $entry->observation,
                    'score' => $entry->matches($problem_description),
                ];
            }
        }

        // Filter bugs
        foreach ($bugs as $bug) {
            if (str_contains(strtolower($bug->errorMessage), strtolower($problem_description))) {
                $issues[] = [
                    'type' => 'bug',
                    'description' => $bug->errorMessage,
                    'resolution' => $bug->resolution,
                    'score' => 1.0,
                ];
            }
        }

        if (empty($issues)) {
            return "No similar issues found in context '{$context}' for: {$problem_description}";
        }

        // Sort by relevance
        usort($issues, fn($a, $b) => $b['score'] <=> $a['score']);

        $output = ["## Similar issues found in '{$context}':\n"];

        foreach (array_slice($issues, 0, 5) as $issue) {
            $output[] = "### [{$issue['type']}]";
            $output[] = $issue['description'];
            if (isset($issue['resolution']) && $issue['resolution'] !== null) {
                $output[] = "**Workaround:** {$issue['resolution']}";
            }
            $output[] = "";
        }

        return implode("\n", $output);
    }
}
