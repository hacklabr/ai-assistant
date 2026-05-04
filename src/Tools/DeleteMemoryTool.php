<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools;

use HackLab\AIAssistant\Memory\UserMemoryStoreInterface;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class DeleteMemoryTool extends Tool
{
    public function __construct(
        private readonly UserMemoryStoreInterface $store,
        private readonly string $userId,
    ) {
        parent::__construct(
            name: 'delete_memory',
            description: 'Delete a specific memory by ID. You can only delete memories belonging to the current user.',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'memory_id',
                type: PropertyType::STRING,
                description: 'The ID of the memory to delete.',
                required: true,
            ),
        ];
    }

    public function __invoke(string $memory_id): string
    {
        $memory = $this->store->get($this->userId, $memory_id);

        if ($memory === null) {
            return "Error: Memory '{$memory_id}' not found or access denied.";
        }

        if ($memory->userId !== $this->userId) {
            return "Error: Memory not found or access denied.";
        }

        $deleted = $this->store->delete($this->userId, $memory_id);

        if ($deleted) {
            return "Memory '{$memory_id}' deleted successfully.";
        }

        return "Error: Failed to delete memory '{$memory_id}'.";
    }
}
