# Persistence Layer

The persistence layer provides flexible storage for conversations, learning data, and configuration.

## Interfaces

### StorageInterface

Generic key-value storage:

```php
interface StorageInterface
{
    /**
     * Save data to storage.
     */
    public function save(string $key, array $data): void;
    
    /**
     * Load data from storage.
     */
    public function load(string $key): ?array;
    
    /**
     * Delete data from storage.
     */
    public function delete(string $key): void;
    
    /**
     * List keys matching a pattern.
     */
    public function list(string $pattern = '*'): array;
    
    /**
     * Check if key exists.
     */
    public function exists(string $key): bool;
}
```

### ConversationStorageInterface

Specialized for conversation history:

```php
interface ConversationStorageInterface extends StorageInterface
{
    /**
     * Save a conversation thread.
     */
    public function saveThread(string $threadId, array $messages): void;
    
    /**
     * Load a conversation thread.
     */
    public function loadThread(string $threadId): array;
    
    /**
     * Append messages to a thread.
     */
    public function appendToThread(string $threadId, array $messages): void;
    
    /**
     * List all thread IDs.
     */
    public function listThreads(): array;
    
    /**
     * Delete a thread.
     */
    public function deleteThread(string $threadId): void;
}
```

## FileStorage (Default Implementation)

Stores data in the filesystem using JSON for structured data and Markdown for human-readable content.

```php
class FileStorage implements StorageInterface
{
    public function __construct(
        protected string $basePath,
    ) {}
}
```

### Directory Structure

```
storage/
├── conversations/
│   ├── thread-abc123.json
│   └── thread-def456.json
├── learning/
│   ├── patterns/
│   │   ├── filesystem-read_file.md
│   │   └── database-query.md
│   └── bugs/
│       ├── bug-2026-04-30-001.md
│       └── bug-2026-04-30-002.md
├── config/
│   ├── subagents.json
│   └── skills-cache.json
└── memory/
    ├── summaries/
    │   └── thread-abc123-summary.md
    └── facts/
        └── global-facts.json
```

### File Formats

**JSON files**: Machine-readable structured data
```json
{
  "thread_id": "abc123",
  "created_at": "2026-04-30T10:00:00Z",
  "updated_at": "2026-04-30T11:00:00Z",
  "messages": [
    {"role": "user", "content": "Hello"},
    {"role": "assistant", "content": "Hi there!"}
  ],
  "metadata": {
    "total_tokens": 150,
    "model": "claude-sonnet-4"
  }
}
```

**Markdown files**: Human-readable with YAML frontmatter
```markdown
---
type: conversation_summary
thread_id: abc123
timestamp: 2026-04-30T11:00:00Z
tokens: 500
---

## Summary

User is building a PHP library for AI assistants. Main concerns are:
- Context condensation for sub-agents
- MCP integration
- Auto-learning capabilities

## Key Facts

- Using Neuron AI as base framework
- Wants zero additional dependencies
- PHP 8.2 minimum
- Package name: hacklab/ai-assistant
```

## HierarchicalChatHistory

Extends Neuron's `AbstractChatHistory` with multi-level memory:

```php
class HierarchicalChatHistory extends AbstractChatHistory
{
    public function __construct(
        int $contextWindow = 150000,
        protected int $summaryThreshold = 10000,
        protected int $recentMessages = 5,
        protected ?AIProviderInterface $summarizationProvider = null,
    ) {
        parent::__construct($contextWindow);
    }
    
    /**
     * Get messages with hierarchy applied.
     * 
     * Returns:
     * 1. System summary message (if available)
     * 2. Key facts message (if available)
     * 3. Recent messages (full content)
     */
    public function getMessages(): array;
    
    /**
     * Trigger summarization of old messages.
     */
    public function summarize(): void;
    
    /**
     * Extract and store key facts.
     */
    public function extractFacts(): void;
}
```

### Memory Levels

1. **Summary**: Condensed text of older conversation (replaced every N messages)
2. **Key Facts**: Structured facts extracted from conversation (user preferences, decisions, etc.)
3. **Recent Messages**: Last N messages kept verbatim

### Integration with Neuron

```php
class Assistant extends Agent
{
    protected function chatHistory(): ChatHistoryInterface
    {
        return new HierarchicalChatHistory(
            contextWindow: $this->config->contextWindow,
            summarizationProvider: $this->config->summarizationProvider,
        );
    }
}
```

## Custom Storage Implementations

Users can implement their own storage:

```php
use HackLab\AIAssistant\Persistence\StorageInterface;

class RedisStorage implements StorageInterface
{
    public function __construct(protected \Redis $redis) {}
    
    public function save(string $key, array $data): void
    {
        $this->redis->set($key, json_encode($data));
    }
    
    public function load(string $key): ?array
    {
        $data = $this->redis->get($key);
        return $data ? json_decode($data, true) : null;
    }
    
    // ... other methods
}
```

## Configuration

```php
use HackLab\AIAssistant\AssistantConfig;

$config = new AssistantConfig(
    storage: new FileStorage(__DIR__ . '/storage'),
    // Or custom:
    // storage: new RedisStorage($redis),
);
```

### Per-Domain Storage

You can assign different storage backends for each domain. When omitted, the main `storage` is used as fallback.

```php
$config = new AssistantConfig(
    provider: $provider,
    storage: new FileStorage('/data/default'),
    conversationStorage: new RedisStorage($redis),      // conversations in Redis
    learningStorage: new FileStorage('/data/learning'),  // learning data on disk
    userMemoryStorage: new DatabaseStorage($pdo),        // user memories in database
);
```

| Parameter | Domain | Used by |
|-----------|--------|---------|
| `conversationStorage` | Conversations (threads) | `ConversationStore` |
| `learningStorage` | Patterns, bugs, entries | `KnowledgeBase` |
| `userMemoryStorage` | Per-user memories | `UserMemoryStore` |

## Migration from Neuron Chat History

Neuron provides:
- `InMemoryChatHistory`
- `FileChatHistory`
- `SQLChatHistory`
- `EloquentChatHistory`

Our `HierarchicalChatHistory` can wrap any of these:

```php
$hierarchical = new HierarchicalChatHistory(
    contextWindow: 150000,
    underlying: new SQLChatHistory(
        thread_id: 'thread-123',
        pdo: $pdo,
    ),
);
```
