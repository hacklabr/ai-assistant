<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\SubAgents;

/**
 * Registry for storing and retrieving sub-agent configurations.
 */
class SubAgentRegistry
{
    /**
     * @var array<string, SubAgentConfig>
     */
    private array $configs = [];

    /**
     * Register a sub-agent configuration.
     */
    public function register(string $id, SubAgentConfig $config): void
    {
        $this->configs[$id] = $config;
    }

    /**
     * Get a sub-agent configuration by ID.
     *
     * @throws \InvalidArgumentException
     */
    public function get(string $id): SubAgentConfig
    {
        if (!isset($this->configs[$id])) {
            throw new \InvalidArgumentException("Sub-agent not found: {$id}");
        }

        return $this->configs[$id];
    }

    /**
     * Check if a sub-agent exists.
     */
    public function has(string $id): bool
    {
        return isset($this->configs[$id]);
    }

    /**
     * Get all registered sub-agent configurations.
     *
     * @return array<string, SubAgentConfig>
     */
    public function all(): array
    {
        return $this->configs;
    }

    /**
     * Load multiple configurations from an array.
     *
     * @param array<string, SubAgentConfig> $configs
     */
    public function loadFromArray(array $configs): void
    {
        foreach ($configs as $id => $config) {
            $this->register($id, $config);
        }
    }
}
