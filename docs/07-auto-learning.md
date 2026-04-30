# Auto-Learning System

The Auto-Learning System enables the assistant to learn from tool usage patterns and collect bugs for continuous improvement.

## Components

### ToolLearner

Records successful and failed tool invocations:

```php
class ToolLearner
{
    public function __construct(
        protected LearningStorageInterface $storage,
    ) {}
    
    /**
     * Record a tool execution.
     */
    public function record(
        ToolInterface $tool,
        array $arguments,
        mixed $result,
        ?\Throwable $error = null,
        array $context = [],
    ): void;
    
    /**
     * Get success rate for a tool.
     */
    public function getSuccessRate(string $toolName): float;
    
    /**
     * Find similar successful patterns for a task.
     */
    public function findPatterns(string $taskDescription, int $limit = 5): array;
    
    /**
     * Suggest tools for a given task.
     */
    public function suggestTools(string $taskDescription): array;
}
```

### BugCollector

Captures exceptions with full execution context:

```php
class BugCollector
{
    public function __construct(
        protected LearningStorageInterface $storage,
    ) {}
    
    /**
     * Collect a bug/error.
     */
    public function collect(
        \Throwable $error,
        array $context,       // Messages, tools called, etc.
        ?string $resolution = null,
    ): string; // Returns bug ID
    
    /**
     * Find similar past bugs.
     */
    public function findSimilar(\Throwable $error, array $context): array;
    
    /**
     * Mark a bug as resolved with solution.
     */
    public function resolve(string $bugId, string $resolution): void;
    
    /**
     * Get unresolved bugs.
     */
    public function getUnresolved(): array;
}
```

### KnowledgeBase

Storage for learned patterns:

```php
interface LearningStorageInterface
{
    public function saveToolPattern(ToolPattern $pattern): void;
    public function getToolPatterns(string $toolName): array;
    public function saveBug(BugReport $bug): string;
    public function getBug(string $id): ?BugReport;
    public function searchBugs(array $criteria): array;
    public function searchPatterns(string $query): array;
}
```

### SuggestionEngine

Suggests tools and approaches based on history:

```php
class SuggestionEngine
{
    public function __construct(
        protected ToolLearner $learner,
        protected BugCollector $bugs,
    ) {}
    
    /**
     * Suggest the best tools for a task.
     */
    public function suggestTools(string $taskDescription, array $availableTools): array;
    
    /**
     * Warn about known issues with a tool/approach.
     */
    public function getWarnings(string $toolName, array $arguments): array;
    
    /**
     * Provide tips based on past successes.
     */
    public function getTips(string $taskDescription): array;
}
```

## Data Models

### ToolPattern

```php
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
    
    /** Check if this pattern matches a task description */
    public function matches(string $taskDescription): float; // 0.0 - 1.0
}
```

### BugReport

```php
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
}
```

## Storage Format

### Tool Patterns (Markdown with YAML frontmatter)

```markdown
---
type: tool_pattern
tool: FileSystemToolkit::read_file
success_rate: 0.95
contexts: 47
last_used: 2026-04-30T10:00:00Z
---

## When it works well

- Reading configuration files (JSON, YAML, INI)
- Reading source code files under 1000 lines
- Reading markdown documentation

## When it fails

- Binary files (images, executables)
- Files larger than 1MB
- Files with encoding issues

## Common argument patterns

```json
{"path": "config/app.php", "offset": 1, "limit": 50}
```

## Tips

- Always check file existence before reading
- Use offset/limit for large files
- Prefer preview over full read for exploration
```

### Bug Reports (Markdown with YAML frontmatter)

```markdown
---
type: bug_report
id: bug-2026-04-30-001
error_type: RuntimeException
error_message: "File not found: config/nonexistent.php"
tool: FileSystemToolkit::read_file
resolved: true
resolution: "Added file existence check before reading"
timestamp: 2026-04-30T10:00:00Z
---

## Context

- Task: "Read the application configuration"
- Messages: 5
- Tools called before: ["list_directory"]

## Stack Trace

```
RuntimeException: File not found: config/nonexistent.php
  at FileSystemToolkit.php:45
```

## Root Cause

The tool was called with a hardcoded path that doesn't exist in all environments.

## Solution

Always verify file existence:
```php
if (file_exists($path)) {
    return file_get_contents($path);
}
return "File not found: $path";
```
```

## Integration with Assistant

Auto-learning is enabled via configuration:

```php
new AssistantConfig(
    autoLearn: true,
    learningPath: __DIR__ . '/storage/learning',
)
```

When enabled:
1. Every tool call is recorded by `ToolLearner`
2. Every exception is captured by `BugCollector`
3. Patterns are stored in the knowledge base
4. The `SuggestionEngine` provides tips to agents

### Middleware Integration

```php
class AutoLearningMiddleware implements WorkflowMiddleware
{
    public function before(NodeInterface $node, Event $event, WorkflowState $state): void
    {
        // Pre-execution: provide warnings/tips
        if ($event instanceof ToolCallEvent) {
            $warnings = $this->suggestionEngine->getWarnings(
                $event->tool->getName(),
                $event->tool->getInputs()
            );
            
            if ($warnings) {
                $event->tool->setDescription(
                    $event->tool->getDescription() . "\n\nWarnings: " . implode(', ', $warnings)
                );
            }
        }
    }
    
    public function after(NodeInterface $node, Event $result, WorkflowState $state): void
    {
        // Post-execution: record results
        if ($result instanceof ToolResultEvent) {
            $this->toolLearner->record(
                $result->tool,
                $result->arguments,
                $result->output,
                $result->error,
                $state->toArray()
            );
        }
    }
}
```

## Privacy Considerations

- Tool arguments and results are stored locally (filesystem by default)
- No data is sent to external services
- Users can disable auto-learning via configuration
- Storage can be configured to exclude sensitive fields
