<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\SubAgents;

use HackLab\AIAssistant\MCP\McpConfigBridge;
use HackLab\AIAssistant\Skills\SkillRegistry;
use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;

class SubAgentInstance extends Agent
{
    public function __construct(
        private readonly SubAgentConfig $subConfig,
        private readonly SkillRegistry $skillRegistry,
    ) {
        parent::__construct();
    }

    protected function provider(): AIProviderInterface
    {
        return $this->subConfig->provider;
    }

    protected function instructions(): string
    {
        return $this->subConfig->buildSystemPrompt($this->skillRegistry);
    }

    protected function tools(): array
    {
        $tools = [];

        foreach ($this->subConfig->tools as $tool) {
            if (is_object($tool)) {
                $tools[] = $tool;
            } elseif (is_string($tool) && class_exists($tool)) {
                $tools[] = $tool::make();
            }
        }

        foreach ($this->subConfig->mcps as $mcpConfig) {
            try {
                $connector = McpConfigBridge::make($mcpConfig);
                $tools = array_merge($tools, $connector->tools());
            } catch (\Throwable $e) {
                // Skip failed MCP connections
            }
        }

        return $tools;
    }
}
