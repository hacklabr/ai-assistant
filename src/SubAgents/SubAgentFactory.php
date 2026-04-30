<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\SubAgents;

use HackLab\AIAssistant\Context\ContextCondenserInterface;
use HackLab\AIAssistant\MCP\McpConfigBridge;
use HackLab\AIAssistant\Skills\SkillRegistry;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;

/**
 * Creates Neuron Agent instances from sub-agent configurations.
 */
class SubAgentFactory
{
    public function __construct(
        private readonly SkillRegistry $skillRegistry,
    ) {}

    /**
     * Create a Neuron Agent from configuration.
     */
    public function create(SubAgentConfig $config): Agent
    {
        $agent = new class($config, $this->skillRegistry) extends Agent {
            public function __construct(
                private readonly SubAgentConfig $subConfig,
                private readonly SkillRegistry $skillRegistry,
            ) {
                parent::__construct();
            }

            protected function provider(): \NeuronAI\Providers\AIProviderInterface
            {
                return $this->subConfig->provider;
            }

            protected function instructions(): string
            {
                return $this->subConfig->buildSystemPrompt($this->skillRegistry);
            }

            /**
             * @return \NeuronAI\Tools\ToolInterface[]
             */
            protected function tools(): array
            {
                $tools = [];

                foreach ($this->subConfig->tools as $tool) {
                    if (is_string($tool) && class_exists($tool)) {
                        $tools[] = $tool::make();
                    } elseif (is_object($tool)) {
                        $tools[] = $tool;
                    }
                }

                // Add MCP tools
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
        };

        return $agent;
    }

    /**
     * Create with pre-loaded chat history.
     *
     * @param Message[] $messages
     */
    public function createWithHistory(SubAgentConfig $config, array $messages): Agent
    {
        $agent = $this->create($config);

        // Load previous messages into chat history
        foreach ($messages as $message) {
            $agent->getChatHistory()->addMessage($message);
        }

        return $agent;
    }
}
