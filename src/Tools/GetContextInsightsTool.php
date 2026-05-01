<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools;

use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * Get insights (learnings and bugs) about a specific context.
 */
class GetContextInsightsTool extends Tool
{
    public function __construct(
        private readonly KnowledgeBase $knowledgeBase,
    ) {
        parent::__construct(
            name: 'get_context_insights',
            description: 'Retrieve recorded learnings, successful patterns, known issues, and tips for a specific context. Use before tackling an unfamiliar task or when unsure about best practices.',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'context',
                type: PropertyType::STRING,
                description: 'The context to query. Can be a tool name, framework, or domain.',
                required: true,
            ),
            new ToolProperty(
                name: 'include_related',
                type: PropertyType::BOOLEAN,
                description: 'If true, also search for insights in related contexts (via tags).',
                required: false,
            ),
        ];
    }

    public function __invoke(
        string $context,
        ?bool $include_related = false,
    ): string {
        $learnings = $this->knowledgeBase->getLearnings($context);
        $bugs = $this->knowledgeBase->searchBugs(['context' => $context]);

        $output = [];
        $output[] = "## Insights for context: {$context}\n";

        // Successful patterns
        $successes = array_filter($learnings, fn($l) => $l->workedWell);
        if (!empty($successes)) {
            $output[] = "### Successful Patterns";
            foreach (array_slice($successes, 0, 5) as $entry) {
                $output[] = "- {$entry->observation}";
                if (!empty($entry->tags)) {
                    $output[] = "  Tags: " . implode(', ', $entry->tags);
                }
            }
            $output[] = "";
        }

        // Anti-patterns / things to avoid
        $failures = array_filter($learnings, fn($l) => !$l->workedWell);
        if (!empty($failures)) {
            $output[] = "### Things to Avoid";
            foreach (array_slice($failures, 0, 5) as $entry) {
                $output[] = "- {$entry->observation}";
            }
            $output[] = "";
        }

        // Known bugs
        if (!empty($bugs)) {
            $output[] = "### Known Issues";
            foreach (array_slice($bugs, 0, 5) as $bug) {
                $status = $bug->resolved ? '[RESOLVED]' : '[OPEN]';
                $output[] = "- {$status} {$bug->errorMessage}";
                if ($bug->resolution !== null) {
                    $output[] = "  Solution: {$bug->resolution}";
                }
            }
            $output[] = "";
        }

        if (empty($successes) && empty($failures) && empty($bugs)) {
            $output[] = "No recorded insights for this context yet.";
        }

        // Related contexts
        if ($include_related && !empty($successes)) {
            $allTags = array_unique(array_merge(...array_map(fn($l) => $l->tags, $successes)));
            if (!empty($allTags)) {
                $output[] = "### Related Tags";
                $output[] = "Explore these related areas: " . implode(', ', array_slice($allTags, 0, 10));
            }
        }

        return implode("\n", $output);
    }
}
