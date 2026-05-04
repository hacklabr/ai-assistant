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
    
    /** Get the storage backend */
    public function getStorage(): ?StorageInterface;
    
    /** Get the auto-learning engine */
    public function getLearningEngine(): ?AutoLearningEngine;
    
    /** Get the user memory store */
    public function getUserMemoryStore(): ?UserMemoryStoreInterface;
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
        public readonly bool $autoDelegate = true,
        public readonly bool $requireLearningCheck = true,
        public readonly array $middleware = [],
        public readonly ?LoggerInterface $logger = null,
        public readonly ?string $userId = null,
        public readonly ?string $userMemoryPath = null,
    ) {}
}
```

### Validation Rules

- `contextWindow` must be at least 1000
- `learningPath` is required when `autoLearn` is `true`
- `userId` is required when `userMemoryPath` is provided
- All `subAgents` entries must be `SubAgentConfig` instances

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
        public readonly WorkflowState $state,
        public readonly CondensedContext $context,
        public readonly array $toolCalls = [],
        public readonly int $tokenUsage = 0,
        public readonly float $duration = 0.0,
    ) {}
    
    public function getContent(): string;
    public function getSteps(): array;
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
    public static function make(array $config, ?LoggerInterface $logger = null): McpConnector;
}
```

Configuration formats:
```php
// stdio — command is validated against an allowlist (npx, node, python3, python, uvx, docker, php)
['type' => 'stdio', 'command' => 'npx', 'args' => ['@modelcontextprotocol/server-github']]

// SSE — URL validated (no internal/metadata endpoints)
['type' => 'sse', 'url' => 'http://localhost:8080/sse', 'token' => 'optional']

// HTTP
['type' => 'http', 'url' => 'https://api.example.com/mcp']
```

### Security Features

- Command allowlist for stdio (only known binaries allowed)
- Argument validation (no path traversal, no shell metacharacters)
- URL validation (http/https only, warns on internal addresses)
- PSR-3 logging of all connection attempts

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

### Learning Guardrails

The `GuardsAgainstPoisoning` trait is applied to `RecordLearningTool` and `RecordBugTool`. It detects instruction-like patterns (e.g., "never use X", "always skip Y") and refuses to record them, preventing knowledge base poisoning through user manipulation.

## User Memory

```php
class UserMemory
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $category,
        public readonly string $content,
        public readonly array $tags = [],
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public readonly ?\DateTimeImmutable $updatedAt = null,
    ) {}
    
    public function matches(string $query): float;
}

interface UserMemoryStoreInterface
{
    public function save(UserMemory $memory): void;
    public function get(string $userId, string $memoryId): ?UserMemory;
    public function listForUser(string $userId, ?string $category = null): array;
    public function search(string $userId, string $query): array;
    public function delete(string $userId, string $memoryId): bool;
    public function exists(string $userId, string $memoryId): bool;
}

class UserMemoryStore implements UserMemoryStoreInterface
{
    public function __construct(string $basePath);
}
```

### Memory Tools

| Tool | Parameters | Description |
|------|-----------|-------------|
| `save_memory` | category, content, tags? | Save a memory (categories: preference, context, note, instruction) |
| `recall_memories` | query, category? | Search memories by query and optional category |
| `delete_memory` | memory_id | Delete a memory (ownership verified) |

### Security Model

- `userId` is injected via `AssistantConfig` by the backend — never from user messages
- Storage is partitioned by sanitized user ID (`storage/memories/{userId}/`)
- `delete_memory` verifies `memory->userId === $this->userId` before deletion
- Files are created with `chmod 0600`

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

## Utilities

```php
class SensitiveDataRedactor
{
    public function redact(string $text): string;
    public function redactMessages(array $messages): array;
    public static function redactString(string $text): string;
}

class TokenEstimator
{
    public function estimate(string $text): int;
    public function estimateMessages(array $messages): int;
}

class ConfigStorage
{
    public function __construct(?string $path = null);
    public function load(): array;
    public function save(array $config): void;
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function exists(): bool;
    public function isEncryptionAvailable(): bool;
}
```

### Config Encryption

Config files are encrypted with `sodium_crypto_secretbox()` when the `HL_AI_ENCRYPTION_KEY` environment variable is set. Without the variable, config is stored in plaintext (with a warning in the CLI example).

## Logging

```php
class StderrLogger extends AbstractLogger
{
    public function __construct(string $prefix = 'ai-assistant');
}
```

The library uses PSR-3 `LoggerInterface` throughout. Pass any PSR-3 logger (Monolog, etc.) via `AssistantConfig::$logger`. Without one, a `NullLogger` is used (no output).
