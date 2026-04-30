# Sub-Agent System

The Sub-Agent System enables delegation of tasks to specialized agents while intelligently condensing context for each target.

## Components

### SubAgentConfig

Data Transfer Object defining a sub-agent:

```php
class SubAgentConfig
{
    public function __construct(
        public readonly string $id,
        public readonly AIProviderInterface $provider,
        public readonly string $instructions,
        public readonly array $tools = [],           // Tool classes or instances
        public readonly array $skills = [],          // Skill names to load
        public readonly string $contextStrategy = 'default',
        public readonly int $contextWindow = 150000,
        public readonly array $mcps = [],            // MCP configurations
        public readonly array $middleware = [],      // Additional middleware
    ) {}
    
    /** Build system prompt including skills */
    public function buildSystemPrompt(SkillRegistry $skills): string;
}
```

### SubAgentRegistry

Stores and retrieves sub-agent configurations:

```php
class SubAgentRegistry
{
    public function register(string $id, SubAgentConfig $config): void;
    public function get(string $id): SubAgentConfig;
    public function has(string $id): bool;
    public function all(): array;
    public function loadFromArray(array $configs): void;
}
```

### SubAgentFactory

Creates Neuron Agent instances from configuration:

```php
class SubAgentFactory
{
    public function create(SubAgentConfig $config): Agent;
    
    /** Create with pre-loaded (condensed) chat history */
    public function createWithHistory(
        SubAgentConfig $config, 
        array $messages
    ): Agent;
}
```

### SubAgentDispatcher

Orchestrates delegation:

```php
class SubAgentDispatcher
{
    public function __construct(
        protected SubAgentRegistry $registry,
        protected SubAgentFactory $factory,
        protected ContextCondenserInterface $condenser,
    ) {}
    
    /**
     * Delegate a message to a sub-agent.
     * 
     * 1. Retrieves sub-agent config
     * 2. Condenses current context using sub-agent's strategy
     * 3. Creates fresh Neuron Agent with condensed history
     * 4. Executes and returns result
     */
    public function delegate(
        string $subAgentId,
        UserMessage $message,
        array $currentMessages = []
    ): SubAgentResult;
}
```

## Usage

### Configuration via AssistantConfig

```php
use HackLab\AIAssistant\AssistantConfig;

$config = new AssistantConfig(
    provider: new Anthropic('key', 'claude-sonnet-4'),
    subAgents: [
        'code-reviewer' => new SubAgentConfig(
            id: 'code-reviewer',
            provider: new OpenAI('key', 'gpt-4'),
            instructions: 'You are an expert code reviewer...',
            tools: [GitToolkit::class, FileSystemToolkit::class],
            skills: ['security', 'psr12'],
            contextStrategy: 'code-focused',
            contextWindow: 8000,
            mcps: [
                ['type' => 'stdio', 'command' => 'npx', 'args' => ['@modelcontextprotocol/server-github']],
            ],
        ),
        'architect' => new SubAgentConfig(
            id: 'architect',
            provider: new Anthropic('key', 'claude-opus-4'),
            instructions: 'You are a software architect...',
            tools: [DiagramToolkit::class],
            skills: ['c4-model', 'adr'],
            contextStrategy: 'architecture-focused',
            contextWindow: 200000,
        ),
    ]
);
```

### Programmatic Delegation

```php
$assistant = Assistant::configure($config);

// Main chat
$response = $assistant->chat(new UserMessage('Review this PR'));

// Delegate to specific sub-agent
$result = $assistant->delegate('code-reviewer', new UserMessage('Check for security issues'));

echo $result->getMessage()->getContent();
echo $result->getTokenUsage();
```

### Auto-Delegation

The main assistant can decide to delegate based on the user's request:

```php
// This would be implemented as a tool in the main assistant
$assistant->tools([
    new DelegateTool($dispatcher, [
        'code-reviewer' => 'For code review and security analysis tasks',
        'architect' => 'For system design and architecture decisions',
    ])
]);
```

## SubAgentResult

```php
class SubAgentResult
{
    public function __construct(
        public readonly Message $message,           // Final response
        public readonly AgentState $state,           // Full agent state
        public readonly CondensedContext $context,   // Context that was sent
        public readonly array $toolCalls = [],       // Tools invoked
        public readonly int $tokenUsage = 0,         // Total tokens used
        public readonly float $duration = 0.0,       // Execution time
    ) {}
    
    /** Get the response content */
    public function getContent(): string;
    
    /** Get all steps/messages in the sub-agent execution */
    public function getSteps(): array;
}
```

## Context Condensation per Sub-Agent

Each sub-agent can define its own `contextStrategy`. The dispatcher:

1. Takes the full conversation history from the main assistant
2. Applies the sub-agent's strategy via `ContextCondenser`
3. Injects only the condensed context into the sub-agent

This ensures:
- The sub-agent only sees relevant context
- Token limits are respected
- Different sub-agents get appropriately filtered context

## Lifecycle

```
User Message
    |
    v
Assistant.chat()
    |
    +---> LLM decides to delegate?
    |         |
    |         v
    |     SubAgentDispatcher.delegate()
    |         |
    |         +---> SubAgentRegistry.get('code-reviewer')
    |         |         |
    |         |         v
    |         |     SubAgentConfig (provider, tools, skills, mcps)
    |         |
    |         +---> ContextCondenser.condense()
    |         |         |
    |         |         v
    |         |     CondensedContext (summary + recent + facts)
    |         |
    |         +---> SubAgentFactory.createWithHistory()
    |         |         |
    |         |         v
    |         |     Neuron Agent (fresh instance)
    |         |
    |         +---> Agent.chat(message)
    |                   |
    |                   v
    |               SubAgentResult
    |
    +---> Or: Assistant handles directly
```
