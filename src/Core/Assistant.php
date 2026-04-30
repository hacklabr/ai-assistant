<?php

declare(strict_types=1);

namespace HackLab\AIAssistant;

use HackLab\AIAssistant\Context\ContextCondenserInterface;
use HackLab\AIAssistant\Context\Strategies\HierarchicalStrategy;
use HackLab\AIAssistant\Core\AssistantConfig;
use HackLab\AIAssistant\Learning\AutoLearningEngine;
use HackLab\AIAssistant\Learning\BugCollector;
use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use HackLab\AIAssistant\Learning\SuggestionEngine;
use HackLab\AIAssistant\Learning\ToolLearner;
use HackLab\AIAssistant\MCP\McpConfigBridge;
use HackLab\AIAssistant\Persistence\FileStorage;
use HackLab\AIAssistant\Persistence\HierarchicalChatHistory;
use HackLab\AIAssistant\Persistence\StorageInterface;
use HackLab\AIAssistant\Skills\SkillRegistry;
use HackLab\AIAssistant\SubAgents\SubAgentConfig;
use HackLab\AIAssistant\SubAgents\SubAgentDispatcher;
use HackLab\AIAssistant\SubAgents\SubAgentFactory;
use HackLab\AIAssistant\SubAgents\SubAgentRegistry;
use HackLab\AIAssistant\SubAgents\SubAgentResult;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Main AI Assistant facade extending Neuron Agent.
 * Provides sub-agent orchestration, skill management, and context condensation.
 */
class Assistant extends Agent
{
    private AssistantConfig $config;
    private SubAgentRegistry $subAgentRegistry;
    private SubAgentDispatcher $subAgentDispatcher;
    private SkillRegistry $skillRegistry;
    private ?StorageInterface $storage;
    private ?AutoLearningEngine $learningEngine = null;
    private ContextCondenserInterface $contextCondenser;

    /**
     * Create an Assistant from configuration.
     */
    public static function configure(AssistantConfig $config): self
    {
        $assistant = new self();
        $assistant->initialize($config);
        return $assistant;
    }

    /**
     * Initialize the assistant with configuration.
     */
    protected function initialize(AssistantConfig $config): void
    {
        $this->config = $config;

        // Initialize storage
        $this->storage = $config->storage
            ?? ($config->storagePath !== null ? new FileStorage($config->storagePath) : null);

        // Initialize skills
        $this->skillRegistry = new SkillRegistry($config->skillsPath);
        $this->skillRegistry->loadAll();

        // Initialize sub-agents
        $this->subAgentRegistry = new SubAgentRegistry();
        $subAgentFactory = new SubAgentFactory($this->skillRegistry);

        foreach ($config->subAgents as $id => $subConfig) {
            if ($subConfig instanceof SubAgentConfig) {
                $this->subAgentRegistry->register($id, $subConfig);
            }
        }

        // Initialize context condenser
        $this->contextCondenser = new HierarchicalStrategy(
            summarizationProvider: $config->provider,
        );

        // Initialize sub-agent dispatcher
        $this->subAgentDispatcher = new SubAgentDispatcher(
            $this->subAgentRegistry,
            $subAgentFactory,
            $this->contextCondenser,
        );

        // Initialize auto-learning if enabled
        if ($config->autoLearn && $config->learningPath !== null) {
            $knowledgeBase = new KnowledgeBase($config->learningPath);
            $toolLearner = new ToolLearner($knowledgeBase);
            $bugCollector = new BugCollector($knowledgeBase);
            $suggestionEngine = new SuggestionEngine($toolLearner, $bugCollector);

            $this->learningEngine = new AutoLearningEngine(
                $toolLearner,
                $bugCollector,
                $suggestionEngine,
            );
        }
    }

    /**
     * Get the AI provider.
     */
    protected function provider(): AIProviderInterface
    {
        return $this->config->provider;
    }

    /**
     * Get the system instructions.
     */
    protected function instructions(): string
    {
        $instructions = $this->config->instructions;

        // Inject main skills
        foreach ($this->config->skills as $skillName) {
            $skill = $this->skillRegistry->get($skillName);
            if ($skill !== null) {
                $instructions .= "\n\n" . $skill->toSystemPrompt();
            }
        }

        return $instructions;
    }

    /**
     * Get available tools.
     *
     * @return \NeuronAI\Tools\ToolInterface[]
     */
    protected function tools(): array
    {
        $tools = [];

        foreach ($this->config->tools as $tool) {
            if (is_string($tool) && class_exists($tool)) {
                $tools[] = $tool::make();
            } elseif (is_object($tool)) {
                $tools[] = $tool;
            }
        }

        // Add MCP tools
        foreach ($this->config->mcps as $mcpConfig) {
            try {
                $connector = McpConfigBridge::make($mcpConfig);
                $tools = array_merge($tools, $connector->tools());
            } catch (\Throwable $e) {
                // Skip failed MCP connections
            }
        }

        return $tools;
    }

    /**
     * Get chat history with hierarchical memory.
     */
    protected function chatHistory(): ChatHistoryInterface
    {
        return new HierarchicalChatHistory(
            contextWindow: $this->config->contextWindow,
            summarizationProvider: $this->config->provider,
        );
    }

    /**
     * Delegate a message to a sub-agent.
     */
    public function delegate(string $subAgentId, UserMessage $message): SubAgentResult
    {
        $currentMessages = $this->getChatHistory()->getMessages();
        return $this->subAgentDispatcher->delegate($subAgentId, $message, $currentMessages);
    }

    /**
     * Get the context condenser.
     */
    public function getContextCondenser(): ContextCondenserInterface
    {
        return $this->contextCondenser;
    }

    /**
     * Get the sub-agent registry.
     */
    public function getSubAgentRegistry(): SubAgentRegistry
    {
        return $this->subAgentRegistry;
    }

    /**
     * Get the skill registry.
     */
    public function getSkillRegistry(): SkillRegistry
    {
        return $this->skillRegistry;
    }

    /**
     * Get the storage interface.
     */
    public function getStorage(): ?StorageInterface
    {
        return $this->storage;
    }

    /**
     * Get the auto-learning engine.
     */
    public function getLearningEngine(): ?AutoLearningEngine
    {
        return $this->learningEngine;
    }
}
