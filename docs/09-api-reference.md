# API Reference

## Assistant

```php
class Assistant extends \NeuronAI\Agent\Agent
{
    /** Create from configuration */
    public static function configure(AssistantConfig $config): self;
    
    /** Delegate to a sub-agent */
    public function delegate(string $subAgentId, UserMessage $message): SubAgentResult;
    
    /** Get the context condenser */
    public function getContextCondenser(): ContextCondenserInterface;
    
    /** Get the sub-agent registry */
    public function getSubAgentRegistry(): SubAgentRegistry;
    
    /** Get the skill registry */
    public function getSkillRegistry(): SkillRegistry;
    
    /** Get the auto-learning engine */
    public function getLearningEngine(): ?AutoLearningEngine;
}
```

## AssistantConfig

```php
class AssistantConfig
{
    public function __construct(
        public readonly AIProviderInterface $provider,
        public readonly string $instructions = '',
        public readonly int $contextWindow = 200000,
        public readonly array $tools = [],
        public readonly array $subAgents = [],
        public readonly array $skills = [],
        public readonly array $mcps = [],
        public readonly ?string $skillsPath = null,
        public readonly ?StorageInterface $storage = null,
        public readonly ?string $storagePath = null,
        public readonly bool $autoLearn = false,
        public readonly ?string $learningPath = null,
        public readonly array $middleware = [],
    ) {}
}
```

## Context Condenser

```php
interface ContextCondenserInterface
{
    public function condense(
        array $messages,
        string $taskDescription,
        int $maxTokens,
        ?string $contextStrategy = null
    ): CondensedContext;
}

class CondensedContext
{
    public function __construct(
        public readonly array $messages,
        public readonly ?string $summary,
        public readonly array $keyFacts,
        public readonly int $originalTokens,
        public readonly int $condensedTokens,
        public readonly string $strategy,
    ) {}
    
    public function toMessages(): array;
}
```

## Sub-Agent System

```php
class SubAgentConfig
{
    public function __construct(
        public readonly string $id,
        public readonly AIProviderInterface $provider,
        public readonly string $instructions,
        public readonly array $tools = [],
        public readonly array $skills = [],
        public readonly string $contextStrategy = 'default',
        public readonly int $contextWindow = 150000,
        public readonly array $mcps = [],
        public readonly array $middleware = [],
    ) {}
}

class SubAgentRegistry
{
    public function register(string $id, SubAgentConfig $config): void;
    public function get(string $id): SubAgentConfig;
    public function has(string $id): bool;
    public function all(): array;
}

class SubAgentDispatcher
{
    public function delegate(
        string $subAgentId,
        UserMessage $message,
        array $currentMessages = []
    ): SubAgentResult;
}

class SubAgentResult
{
    public function __construct(
        public readonly Message $message,
        public readonly AgentState $state,
        public readonly CondensedContext $context,
        public readonly array $toolCalls = [],
        public readonly int $tokenUsage = 0,
        public readonly float $duration = 0.0,
    ) {}
}
```

## Skills

```php
class Skill
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $content,
        public readonly array $tools = [],
        public readonly ?string $contextStrategy = null,
        public readonly array $categories = [],
        public readonly ?string $version = null,
        public readonly ?string $author = null,
        public readonly ?string $sourceFile = null,
    ) {}
    
    public function toSystemPrompt(): string;
}

class SkillRegistry
{
    public function loadAll(): void;
    public function register(Skill $skill): void;
    public function get(string $name): ?Skill;
    public function has(string $name): bool;
    public function all(): array;
    public function byCategory(string $category): array;
    public function search(string $query): array;
}

class MarkdownSkillLoader
{
    public function load(string $filePath): Skill;
    public function loadDirectory(string $directory): array;
}
```

## MCP Bridge

```php
class McpConfigBridge
{
    public static function make(array $config): McpConnector;
}
```

Configuration formats:
```php
// stdio
['type' => 'stdio', 'command' => 'php', 'args' => ['server.php']]

// SSE
['type' => 'sse', 'url' => 'http://localhost:8080/sse', 'token' => 'optional']

// HTTP
['type' => 'http', 'url' => 'http://localhost:8080/mcp']
```

## Auto-Learning

```php
class ToolLearner
{
    public function record(ToolInterface $tool, array $arguments, mixed $result, ?\Throwable $error = null, array $context = []): void;
    public function getSuccessRate(string $toolName): float;
    public function findPatterns(string $taskDescription, int $limit = 5): array;
    public function suggestTools(string $taskDescription): array;
}

class BugCollector
{
    public function collect(\Throwable $error, array $context, ?string $resolution = null): string;
    public function findSimilar(\Throwable $error, array $context): array;
    public function resolve(string $bugId, string $resolution): void;
    public function getUnresolved(): array;
}

class SuggestionEngine
{
    public function suggestTools(string $taskDescription, array $availableTools): array;
    public function getWarnings(string $toolName, array $arguments): array;
    public function getTips(string $taskDescription): array;
}
```

## Persistence

```php
interface StorageInterface
{
    public function save(string $key, array $data): void;
    public function load(string $key): ?array;
    public function delete(string $key): void;
    public function list(string $pattern = '*'): array;
    public function exists(string $key): bool;
}

interface ConversationStorageInterface extends StorageInterface
{
    public function saveThread(string $threadId, array $messages): void;
    public function loadThread(string $threadId): array;
    public function appendToThread(string $threadId, array $messages): void;
    public function listThreads(): array;
    public function deleteThread(string $threadId): void;
}

class FileStorage implements StorageInterface
{
    public function __construct(string $basePath);
}

class HierarchicalChatHistory extends AbstractChatHistory
{
    public function __construct(
        int $contextWindow = 150000,
        int $summaryThreshold = 10000,
        int $recentMessages = 5,
        ?AIProviderInterface $summarizationProvider = null,
    );
    
    public function summarize(): void;
    public function extractFacts(): void;
}
```
