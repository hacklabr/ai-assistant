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
    public function getStorage(): StorageInterface;
    
    /** Get the auto-learning engine */
    public function getLearningEngine(): ?AutoLearningEngine;
    
    /** Get the user memory store */
    public function getUserMemoryStore(): ?UserMemoryStore;
}
```

## AssistantConfig

```php
class AssistantConfig
{
    public function __construct(
        public readonly AIProviderInterface $provider,
        public readonly StorageInterface $storage,
        public readonly string $instructions = '',
        public readonly int $contextWindow = 200000,
        public readonly array $tools = [],
        public readonly array $subAgents = [],
        public readonly array $skills = [],
        public readonly array $mcps = [],
        public readonly ?string $skillsPath = null,
        public readonly bool $autoLearn = false,
        public readonly bool $autoDelegate = true,
        public readonly bool $requireLearningCheck = true,
        public readonly array $middleware = [],
        public readonly ?LoggerInterface $logger = null,
        public readonly ?string $userId = null,
        public readonly ?float $requestTimeout = null,
    ) {}
}
```

### Validation Rules

- `contextWindow` must be at least 1000
- `requestTimeout` must be greater than 0 (when provided)
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

The `GuardsAgainstPoisoning` trait is applied to `RecordLearningTool`, `RecordBugTool`, and `ForgetLearningTool`. It detects instruction-like patterns (e.g., "never use X", "always skip Y") and refuses to record/delete them, preventing knowledge base poisoning through user manipulation.

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
    public function toArray(): array;
    public static function fromArray(array $data): self;
}

class UserMemoryStore
{
    public function __construct(StorageInterface $storage);
    public function save(UserMemory $memory): void;
    public function get(string $userId, string $memoryId): ?UserMemory;
    public function listForUser(string $userId, ?string $category = null): array;
    public function search(string $userId, string $query): array;
    public function delete(string $userId, string $memoryId): bool;
    public function exists(string $userId, string $memoryId): bool;
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
- Storage is partitioned by sanitized user ID via namespace (`memories/{userId}`)
- `delete_memory` verifies `memory->userId === $this->userId` before deletion

## Persistence

```php
interface StorageInterface
{
    public function save(string $namespace, string $key, array $data): void;
    public function load(string $namespace, string $key): ?array;
    public function delete(string $namespace, string $key): bool;
    public function exists(string $namespace, string $key): bool;
    
    /**
     * @return string[]
     */
    public function list(string $namespace, string $pattern = '*'): array;
    
    /**
     * @return array{data: array, score: float}[]
     */
    public function search(string $namespace, string $query, int $limit = 10): array;
    
    /**
     * @param array{max_age_days?: int, max_per_namespace?: int} $criteria
     * @return int Number of entries removed
     */
    public function cleanup(string $namespace, array $criteria = []): int;
}

class FileStorage implements StorageInterface
{
    public function __construct(string $basePath);
}

class ConversationStore
{
    public function __construct(StorageInterface $storage);
    public function saveThread(string $threadId, array $messages): void;
    public function loadThread(string $threadId): array;
    public function appendToThread(string $threadId, array $messages): void;
    public function listThreads(): array;
    public function deleteThread(string $threadId): bool;
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

## Learning Storage

```php
class KnowledgeBase
{
    public function __construct(StorageInterface $storage);
    
    public function saveToolPattern(ToolPattern $pattern): void;
    public function getToolPatterns(string $toolName): array;
    public function getToolNames(): array;
    public function searchPatterns(string $query): array;
    
    public function saveBug(BugReport $bug): string;
    public function getBug(string $id): ?BugReport;
    public function searchBugs(array $criteria): array;
    
    public function saveLearning(LearningEntry $entry): void;
    public function getLearnings(string $context): array;
    public function searchLearnings(string $query): array;
    public function getContexts(): array;
    public function deleteLearning(string $context, string $id): bool;
    
    public function cleanup(): int;
}

class LearningEntry
{
    public function __construct(
        public readonly string $context,
        public readonly string $observation,
        public readonly bool $workedWell,
        public readonly array $tags = [],
        public readonly ?string $id = null,
        public readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable(),
    ) {}
    
    public function matches(string $query): float;
    public function toArray(): array;
    public static function fromArray(array $data): self;
}

class ToolPattern
{
    public function __construct(
        public readonly string $toolName,
        public readonly array $arguments,
        public readonly mixed $result,
        public readonly ?string $error = null,
        public readonly array $context = [],
        public readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable(),
    ) {}
    
    public function matches(string $taskDescription): float;
    public function toArray(): array;
    public static function fromArray(array $data): self;
}

class BugReport
{
    public function __construct(
        public readonly string $id,
        public readonly string $errorType,
        public readonly string $errorMessage,
        public readonly string $stackTrace,
        public readonly array $context,
        public readonly \DateTimeImmutable $timestamp,
        public readonly ?string $resolution = null,
        public readonly bool $resolved = false,
    ) {}
    
    public function toArray(): array;
    public static function fromArray(array $data): self;
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

## Built-in Tools

### FileReaderTool

Reads and extracts text from local documents. Supports PDF, DOCX, TXT, CSV, Markdown, HTML, JSON, XML, and RTF.

```php
use HackLab\AIAssistant\Tools\FileReader\FileReaderTool;

new AssistantConfig(
    provider: $provider,
    storage: $storage,
    tools: [new FileReaderTool()],
);
```

#### Constructor

```php
new FileReaderTool(
    int $maxFileSizeBytes = 52428800,  // 50MB
    ?array $readers = null,            // Custom readers (null = defaults)
);
```

#### Tool Properties

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `file_path` | string | yes | Absolute path to the file |
| `max_length` | integer | no | Max characters to return (default: 100000) |

#### Response Format

```json
{
    "success": true,
    "file": "document.pdf",
    "type": "pdf",
    "size_bytes": 12345,
    "content": "Extracted text...",
    "truncated": false
}
```

#### Supported Formats

| Format | Extension | Reader |
|--------|-----------|--------|
| PDF | `.pdf` | `PdfDocumentReader` (smalot/pdfparser) |
| Word | `.docx` | `DocxDocumentReader` (phpoffice/phpword) |
| Plain Text | `.txt` | `PlainTextDocumentReader` |
| CSV | `.csv` | `PlainTextDocumentReader` (formatted as table) |
| Markdown | `.md` | `PlainTextDocumentReader` |
| HTML | `.html`, `.htm` | `PlainTextDocumentReader` |
| JSON | `.json` | `PlainTextDocumentReader` |
| XML | `.xml` | `PlainTextDocumentReader` |
| RTF | `.rtf` | `PlainTextDocumentReader` |

#### Custom Readers

Implement `DocumentReaderInterface` to add support for new file types:

```php
use HackLab\AIAssistant\Tools\FileReader\DocumentReaderInterface;
use HackLab\AIAssistant\Tools\FileReader\FileReaderTool;

class ExcelReader implements DocumentReaderInterface
{
    public function supports(string $type): bool
    {
        return $type === 'xlsx';
    }

    public function read(string $filePath): string
    {
        // Extract text from Excel...
    }
}

$tool = new FileReaderTool(readers: [
    new ExcelReader(),
    // Default readers are NOT included when you pass custom ones.
    // Add them manually if needed:
    new \HackLab\AIAssistant\Tools\FileReader\PdfDocumentReader(),
    new \HackLab\AIAssistant\Tools\FileReader\DocxDocumentReader(),
    new \HackLab\AIAssistant\Tools\FileReader\PlainTextDocumentReader(),
]);
```
