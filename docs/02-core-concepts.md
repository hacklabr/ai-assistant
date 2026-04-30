# Core Concepts

## Assistant

The main entry point. Extends `NeuronAI\Agent\Agent` and adds sub-agent orchestration, skill management, and context condensation.

```php
use HackLab\AIAssistant\Assistant;
use HackLab\AIAssistant\AssistantConfig;

$assistant = Assistant::configure(
    new AssistantConfig(
        provider: new Anthropic('key', 'claude-sonnet-4'),
        contextWindow: 200000,
        storagePath: __DIR__ . '/storage',
        skillsPath: __DIR__ . '/skills',
        subAgents: [
            'code-reviewer' => [
                'provider' => new OpenAI('key', 'gpt-4'),
                'instructions' => 'You are a code reviewer...',
                'tools' => [GitToolkit::class, FileSystemToolkit::class],
                'skills' => ['security', 'psr12'],
                'contextStrategy' => 'code-focused',
                'mcps' => [
                    ['type' => 'stdio', 'command' => 'npx', 'args' => ['@modelcontextprotocol/server-github']],
                ],
            ],
        ],
        autoLearn: true,
    )
);
```

## Context Condensation

Before delegating to a sub-agent, the framework condenses the current conversation history to only include information relevant to the sub-agent's task.

**Strategies**:
- **Truncation**: Simple token-based cutting (fastest)
- **Summarization**: Uses LLM to summarize old messages
- **Relevance**: Extracts messages matching keywords/patterns relevant to the task
- **Hierarchical**: Maintains summary + recent messages + extracted key facts (default)

## Sub-Agent Orchestration

Sub-agents are fully independent Neuron Agent instances with their own:
- AI Provider (can be different from the main assistant)
- Tools and toolkits
- Skills (system prompt additions)
- MCP connections
- Context window and strategy

The dispatcher:
1. Looks up sub-agent configuration from registry
2. Condenses current context using the sub-agent's strategy
3. Creates a fresh Neuron Agent instance
4. Injects condensed context as chat history
5. Executes the task
6. Returns the result

## Skills

Skills are reusable instruction modules stored as Markdown files with YAML frontmatter:

```markdown
---
name: Security Auditor
description: OWASP security specialist
tools:
  - StaticAnalysisTool
  - DependencyCheckTool
context_strategy: security-focused
---

When reviewing code:
- Check for SQL injection, XSS, CSRF
- Validate prepared statements
- Never expose secrets
```

Skills can be assigned to sub-agents by name. Their content is injected into the sub-agent's system prompt.

## MCP Integration

The framework supports all MCP transports natively through Neuron:

```php
// stdio
['type' => 'stdio', 'command' => 'php', 'args' => ['mcp_server.php']]

// SSE
['type' => 'sse', 'url' => 'http://localhost:8080/sse']

// HTTP Streaming
['type' => 'http', 'url' => 'http://localhost:8080/mcp']
```

The `McpConfigBridge` converts these arrays into `McpConnector` instances.

## Auto-Learning

The framework continuously learns from tool usage and failures:

- **ToolLearner**: Records successful and failed tool invocations with context
- **BugCollector**: Captures exceptions with full execution context
- **KnowledgeBase**: Stores patterns in Markdown files for retrieval
- **SuggestionEngine**: Suggests tools based on historical task similarity

## Persistence

Default persistence uses the filesystem:
- Conversations: `storage/conversations/{thread_id}.json`
- Learning: `storage/learning/*.md`
- Configuration: `storage/config/`

The `StorageInterface` allows custom implementations (database, Redis, etc.):

```php
interface StorageInterface {
    public function save(string $key, array $data): void;
    public function load(string $key): ?array;
    public function delete(string $key): void;
    public function list(string $pattern): array;
}
```
