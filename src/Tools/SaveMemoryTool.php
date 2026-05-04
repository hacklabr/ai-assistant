<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools;

use HackLab\AIAssistant\Memory\UserMemory;
use HackLab\AIAssistant\Memory\UserMemoryStoreInterface;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class SaveMemoryTool extends Tool
{
    public function __construct(
        private readonly UserMemoryStoreInterface $store,
        private readonly string $userId,
    ) {
        parent::__construct(
            name: 'save_memory',
            description: 'Save a memory or note for the current user. Memories are personal and persist across conversations.',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'category',
                type: PropertyType::STRING,
                description: 'Category for the memory. One of: preference, context, note, instruction.',
                required: true,
            ),
            new ToolProperty(
                name: 'content',
                type: PropertyType::STRING,
                description: 'The content to remember. Be concise and clear.',
                required: true,
            ),
            new ToolProperty(
                name: 'tags',
                type: PropertyType::STRING,
                description: 'Comma-separated tags for organizing memories (e.g., "project-x,frontend,react").',
                required: false,
            ),
        ];
    }

    public function __invoke(
        string $category,
        string $content,
        ?string $tags = null,
    ): string {
        $allowedCategories = ['preference', 'context', 'note', 'instruction'];

        if (!in_array($category, $allowedCategories, true)) {
            return "Error: Invalid category '{$category}'. Allowed: " . implode(', ', $allowedCategories);
        }

        $tagArray = $tags !== null ? array_map('trim', explode(',', $tags)) : [];
        $id = 'mem-' . date('Y-m-d') . '-' . uniqid();

        $memory = new UserMemory(
            id: $id,
            userId: $this->userId,
            category: $category,
            content: $content,
            tags: $tagArray,
        );

        $this->store->save($memory);

        return "Memory saved (category: {$category}, id: {$id}).";
    }
}
