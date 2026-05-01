# HackLab AI Assistant

An embeddable AI assistant framework for PHP built on top of [Neuron AI](https://neuron-ai.dev/). Provides advanced context management, sub-agent orchestration, skill configuration, auto-learning, and MCP integration.

## Features

- **Context Condensation** - 4 strategies for intelligent context reduction before delegation
- **Sub-Agent Orchestration** - Delegate tasks to specialized agents with automatically condensed context
- **Skill System** - Configure reusable instruction modules via Markdown files with YAML frontmatter
- **MCP Integration** - Native support for stdio, SSE, and HTTP transports via Neuron's MCP connector
- **Auto-Learning** - Record tool patterns, collect bugs, and get intelligent suggestions
- **Zero Dependencies** - Beyond Neuron AI, no external packages required

## Requirements

- PHP 8.3+
- [Neuron AI](https://neuron-ai.dev/) ^3.0

## Installation

```bash
composer require hacklab/ai-assistant
```

## Quick Start

```php
use HackLab\AIAssistant\Assistant;
use HackLab\AIAssistant\AssistantConfig;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Anthropic\Anthropic;

$assistant = Assistant::configure(
    new AssistantConfig(
        provider: new Anthropic(
            key: 'your-api-key',
            model: 'claude-sonnet-4',
        ),
        instructions: 'You are a helpful coding assistant.',
    )
);

$response = $assistant->chat(new UserMessage('Hello!'));
echo $response->getMessage()->getContent();
```

## Configuration

### Basic Configuration

```php
use HackLab\AIAssistant\AssistantConfig;

$config = new AssistantConfig(
    provider: $aiProvider,           // Neuron AIProviderInterface
    instructions: 'System prompt',   // Base instructions
    contextWindow: 200000,           // Token limit (default: 200000)
    tools: [],                       // Array of Tool classes/instances
    subAgents: [],                   // Sub-agent configurations
    skills: [],                      // Skill names to load
    skillsPath: '/path/to/skills',   // Directory containing .md skill files
    storagePath: '/path/to/storage', // Data storage directory
    autoLearn: false,                // Enable auto-learning
    learningPath: '/path/to/learn',  // Learning data directory
    autoDelegate: true,              // Enable auto-delegation to sub-agents
    requireLearningCheck: true,      // Mandatory learning check before using tools
);
```

### Supported AI Providers

Any Neuron AI provider can be used:

```php
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\Deepseek\Deepseek;

// Anthropic Claude
new Anthropic(key: 'sk-ant-...', model: 'claude-sonnet-4');

// OpenAI GPT
new OpenAI(key: 'sk-...', model: 'gpt-4o');

// Google Gemini
new Gemini(key: '...', model: 'gemini-2.0-flash');

// Local Ollama (no API key needed)
new Ollama(model: 'llama3.2');

// Deepseek
new Deepseek(key: '...', model: 'deepseek-chat');
```

### Custom Endpoints (OpenAI-Compatible APIs)

Use `OpenAILike` for any provider with an OpenAI-compatible API:

```php
use NeuronAI\Providers\OpenAILike;

// Z.AI Coding Plan
new OpenAILike(
    baseUri: 'https://api.z.ai/api/coding/paas/v4',
    key: 'your-zai-api-key',
    model: 'glm-5.1',
    parameters: [],
    strict_response: false,
    httpClient: null
);

// Any other OpenAI-compatible provider
new OpenAILike(
    baseUri: 'https://api.custom-provider.com/v1',
    key: 'your-key',
    model: 'your-model',
    parameters: [],
    strict_response: false,
    httpClient: null
);
```

## Sub-Agents

Delegate tasks to specialized agents with automatically condensed context:

```php
use HackLab\AIAssistant\SubAgents\SubAgentConfig;

$assistant = Assistant::configure(
    new AssistantConfig(
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
        ],
    )
);

// Delegate to sub-agent
$result = $assistant->delegate('code-reviewer', new UserMessage('Check for security issues'));
echo $result->getContent();
```

## Context Condensation Strategies

When delegating to sub-agents, context is automatically condensed using one of 4 strategies:

| Strategy | Description | Best For |
|----------|-------------|----------|
| **Truncation** | Simple token-based cutting | Emergency fallback |
| **Summarization** | LLM-powered summarization | Long conversations |
| **Relevance** | Keyword/pattern matching | Task-specific delegation |
| **Hierarchical** | Summary + recent + key facts | Complex multi-turn (default) |

Configure per sub-agent:

```php
new SubAgentConfig(
    // ...
    contextStrategy: 'code-focused',  // Use relevance strategy with code keywords
)
```

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

Load skills from a directory:

```php
new AssistantConfig(
    skillsPath: __DIR__ . '/skills',
    skills: ['security'],  // Reference by name
)
```

## MCP Integration

Connect to MCP servers via stdio, SSE, or HTTP:

```php
// stdio (local process)
['type' => 'stdio', 'command' => 'npx', 'args' => ['@modelcontextprotocol/server-github']]

// SSE (Server-Sent Events)
['type' => 'sse', 'url' => 'http://localhost:8080/sse', 'token' => 'optional-bearer-token']

// HTTP Streaming
['type' => 'http', 'url' => 'https://api.example.com/mcp']
```

## Auto-Learning

Enable to record tool usage patterns, collect bugs, and build contextual knowledge:

```php
new AssistantConfig(
    autoLearn: true,
    learningPath: __DIR__ . '/storage/learning',
    requireLearningCheck: true,  // Default: mandatory check before using tools
)
```

### Learning Tools

When auto-learning is enabled, the assistant gains access to 4 contextual learning tools:

| Tool | Purpose |
|------|---------|
| `record_learning` | Record a pattern or anti-pattern for a specific context |
| `get_context_insights` | Retrieve recorded learnings, patterns, and known issues |
| `record_bug` | Document a bug or error with optional workaround |
| `find_similar_issues` | Search for known issues before attempting an approach |

### Contextual Organization

Learnings are organized by **context** (tool name, framework, domain):

```php
// Record a successful pattern
$assistant->chat(new UserMessage(
    'record_learning(context: "filesystem_tool", observation: "Always check parent dir", worked_well: true)'
));

// Check insights before using a tool
$assistant->chat(new UserMessage(
    'get_context_insights(context: "database_tool")'
));
```

### Mandatory Learning Check

When `requireLearningCheck: true` (default), the assistant is instructed to **consult the learning system before using any tool for the first time** in a conversation. This prevents repeated mistakes and leverages accumulated knowledge.

To disable:
```php
new AssistantConfig(
    requireLearningCheck: false,  // Skip mandatory checks
)
```

## Persistence

Default file-based storage. Implement `StorageInterface` for custom backends (database, Redis, etc.):

```php
use HackLab\AIAssistant\Persistence\StorageInterface;

class RedisStorage implements StorageInterface {
    // ... implement save(), load(), delete(), list(), exists()
}

new AssistantConfig(
    storage: new RedisStorage($redis),
)
```

## CLI Example

Interactive command-line assistant:

```php
// See examples/cli-assistant.php
php examples/cli-assistant.php
```

## Documentation

Full architecture documentation is available in the `docs/` directory:

- [Architecture Overview](docs/01-architecture-overview.md)
- [Core Concepts](docs/02-core-concepts.md)
- [Context Condenser](docs/03-context-condenser.md)
- [Sub-Agent System](docs/04-subagent-system.md)
- [Skill System](docs/05-skill-system.md)
- [MCP Integration](docs/06-mcp-integration.md)
- [Auto-Learning](docs/07-auto-learning.md)
- [Persistence](docs/08-persistence.md)
- [API Reference](docs/09-api-reference.md)
- [Examples](docs/10-examples.md)
- [Development Guide](docs/11-development-guide.md)

## Testing

```bash
vendor/bin/phpunit
```

## License

MIT License

## Contributing

Contributions are welcome! Please ensure:
- PHP 8.3+ with `declare(strict_types=1)`
- PSR-12 coding standards
- Tests for new features
- All documentation in English

## Credits

Built by [HackLab](https://hacklab.com.br) on top of [Neuron AI](https://neuron-ai.dev/).
