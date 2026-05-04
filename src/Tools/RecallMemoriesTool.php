<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools;

use HackLab\AIAssistant\Memory\UserMemoryStoreInterface;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class RecallMemoriesTool extends Tool
{
    public function __construct(
        private readonly UserMemoryStoreInterface $store,
        private readonly string $userId,
    ) {
        parent::__construct(
            name: 'recall_memories',
            description: 'Search and retrieve memories for the current user. Use to recall preferences, past context, or notes.',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'Search query to find relevant memories.',
                required: true,
            ),
            new ToolProperty(
                name: 'category',
                type: PropertyType::STRING,
                description: 'Optional category filter. One of: preference, context, note, instruction.',
                required: false,
            ),
        ];
    }

    public function __invoke(
        string $query,
        ?string $category = null,
    ): string {
        if ($category !== null) {
            $memories = $this->store->listForUser($this->userId, $category);

            $filtered = array_filter($memories, fn ($m) => $m->matches($query) > 0.2);
            $memories = array_values($filtered);
        } else {
            $memories = $this->store->search($this->userId, $query);
        }

        if (empty($memories)) {
            return "No memories found matching '{$query}'.";
        }

        $output = ["## Memories matching '{$query}':\n"];

        foreach (array_slice($memories, 0, 10) as $memory) {
            $output[] = "### [{$memory->category}] {$memory->id}";
            $output[] = $memory->content;
            if (!empty($memory->tags)) {
                $output[] = "Tags: " . implode(', ', $memory->tags);
            }
            $output[] = "Saved: " . $memory->createdAt->format('Y-m-d H:i');
            $output[] = "";
        }

        return implode("\n", $output);
    }
}
