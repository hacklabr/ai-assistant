<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools;

use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use HackLab\AIAssistant\Learning\Storage\LearningEntry;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * Record a contextual learning observation.
 *
 * Use this when you discover something that worked well or should be avoided
 * in a specific context (e.g., a tool, framework, pattern).
 */
class RecordLearningTool extends Tool
{
    public function __construct(
        private readonly KnowledgeBase $knowledgeBase,
    ) {
        parent::__construct(
            name: 'record_learning',
            description: 'Record a learning observation about a specific context. Use when you discover patterns, best practices, or pitfalls that should be remembered for future reference.',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'context',
                type: PropertyType::STRING,
                description: 'The context this learning applies to. Can be a tool name (e.g., "filesystem_tool"), framework (e.g., "laravel"), or domain (e.g., "api_integration").',
                required: true,
            ),
            new ToolProperty(
                name: 'observation',
                type: PropertyType::STRING,
                description: 'What was learned. Be specific and actionable.',
                required: true,
            ),
            new ToolProperty(
                name: 'worked_well',
                type: PropertyType::BOOLEAN,
                description: 'True if this is a successful pattern to repeat. False if it is something to avoid.',
                required: true,
            ),
            new ToolProperty(
                name: 'tags',
                type: PropertyType::STRING,
                description: 'Comma-separated tags for cross-referencing (e.g., "security,filesystem,php").',
                required: false,
            ),
        ];
    }

    public function __invoke(
        string $context,
        string $observation,
        bool $worked_well,
        ?string $tags = null,
    ): string {
        $tagArray = $tags !== null ? array_map('trim', explode(',', $tags)) : [];

        $entry = new LearningEntry(
            context: $context,
            observation: $observation,
            workedWell: $worked_well,
            tags: $tagArray,
        );

        $this->knowledgeBase->saveLearning($entry);

        $outcome = $worked_well ? 'success pattern' : 'anti-pattern to avoid';
        return "Recorded {$outcome} in context '{$context}'.";
    }
}
