<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools;

use HackLab\AIAssistant\Learning\Storage\BugReport;
use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * Record a bug or issue in a specific context.
 */
class RecordBugTool extends Tool
{
    public function __construct(
        private readonly KnowledgeBase $knowledgeBase,
    ) {
        parent::__construct(
            name: 'record_bug',
            description: 'Record a bug, error, or issue encountered in a specific context. Use when something goes wrong so future interactions can avoid or handle it better.',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'context',
                type: PropertyType::STRING,
                description: 'The context where the bug occurred (tool name, framework, domain).',
                required: true,
            ),
            new ToolProperty(
                name: 'error_description',
                type: PropertyType::STRING,
                description: 'Clear description of what went wrong.',
                required: true,
            ),
            new ToolProperty(
                name: 'workaround',
                type: PropertyType::STRING,
                description: 'If known, describe a workaround or fix.',
                required: false,
            ),
        ];
    }

    public function __invoke(
        string $context,
        string $error_description,
        ?string $workaround = null,
    ): string {
        $bug = new BugReport(
            id: 'bug-' . date('Y-m-d') . '-' . uniqid(),
            errorType: $context,
            errorMessage: $error_description,
            stackTrace: '',
            context: ['context' => $context, 'workaround' => $workaround],
            timestamp: new \DateTimeImmutable(),
            resolution: $workaround,
            resolved: $workaround !== null,
        );

        $this->knowledgeBase->saveBug($bug);

        $msg = "Recorded bug in context '{$context}'.";
        if ($workaround !== null) {
            $msg .= " Workaround documented.";
        }

        return $msg;
    }
}
