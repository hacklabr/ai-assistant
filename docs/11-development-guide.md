# Development Guide

## Setting Up the Project

```bash
# Clone or create project directory
mkdir hacklab-ai-assistant
cd hacklab-ai-assistant

# Initialize composer
cat > composer.json <<'JSON'
{
    "name": "hacklab/ai-assistant",
    "description": "Embeddable AI assistant framework for PHP",
    "type": "library",
    "require": {
        "php": "^8.3",
        "neuron-ai": "^3.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^1.10"
    },
    "autoload": {
        "psr-4": {
            "HackLab\\AIAssistant\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "HackLab\\AIAssistant\\Tests\\": "tests/"
        }
    },
    "minimum-stability": "stable"
}
JSON

composer install
```

## Directory Structure

```
├── src/
│   ├── Core/
│   │   ├── Assistant.php
│   │   ├── AssistantConfig.php
│   │   └── EventDispatcher.php
│   ├── Context/
│   │   ├── ContextCondenserInterface.php
│   │   ├── CondensedContext.php
│   │   ├── RelevanceScorer.php
│   │   └── Strategies/
│   │       ├── TruncationStrategy.php
│   │       ├── SummarizationStrategy.php
│   │       ├── RelevanceStrategy.php
│   │       └── HierarchicalStrategy.php
│   ├── SubAgents/
│   │   ├── SubAgentRegistry.php
│   │   ├── SubAgentDispatcher.php
│   │   ├── SubAgentConfig.php
│   │   ├── SubAgentFactory.php
│   │   └── SubAgentResult.php
│   ├── Skills/
│   │   ├── Skill.php
│   │   ├── SkillRegistry.php
│   │   └── MarkdownSkillLoader.php
│   ├── MCP/
│   │   └── McpConfigBridge.php
│   ├── Learning/
│   │   ├── ToolLearner.php
│   │   ├── BugCollector.php
│   │   ├── KnowledgeBase.php
│   │   ├── SuggestionEngine.php
│   │   └── Storage/
│   │       ├── LearningStorageInterface.php
│   │       ├── ToolPattern.php
│   │       └── BugReport.php
│   ├── Persistence/
│   │   ├── StorageInterface.php
│   │   ├── ConversationStorageInterface.php
│   │   ├── FileStorage.php
│   │   └── HierarchicalChatHistory.php
│   └── Utils/
│       ├── MarkdownParser.php
│       ├── YamlParser.php
│       └── TokenEstimator.php
├── tests/
│   ├── Context/
│   ├── SubAgents/
│   ├── Skills/
│   └── Persistence/
├── examples/
│   ├── cli-assistant.php
│   └── web-api.php
├── docs/
├── skills/
│   ├── security.md
│   ├── psr12.md
│   └── README.md
└── composer.json
```

## Code Standards

- **PHP**: 8.3+ with strict types (`declare(strict_types=1)`)
- **Style**: PSR-12
- **Types**: Full type declarations, return types, and typed properties
- **Documentation**: PHPDoc for all public methods
- **Tests**: PHPUnit with >80% coverage
- **Static Analysis**: PHPStan level 9

## Implementing a New Context Strategy

```php
namespace HackLab\AIAssistant\Context\Strategies;

use HackLab\AIAssistant\Context\ContextCondenserInterface;
use HackLab\AIAssistant\Context\CondensedContext;

class CustomStrategy implements ContextCondenserInterface
{
    public function condense(
        array $messages,
        string $taskDescription,
        int $maxTokens,
        ?string $contextStrategy = null
    ): CondensedContext {
        // Your condensation logic here
        
        return new CondensedContext(
            messages: $condensedMessages,
            summary: $summary,
            keyFacts: $facts,
            originalTokens: $originalCount,
            condensedTokens: $condensedCount,
            strategy: 'custom',
        );
    }
}
```

## Implementing Custom Storage

```php
namespace MyApp\Storage;

use HackLab\AIAssistant\Persistence\StorageInterface;

class RedisStorage implements StorageInterface
{
    public function __construct(protected \Redis $redis) {}
    
    public function save(string $key, array $data): void { /* ... */ }
    public function load(string $key): ?array { /* ... */ }
    public function delete(string $key): void { /* ... */ }
    public function list(string $pattern = '*'): array { /* ... */ }
    public function exists(string $key): bool { /* ... */ }
}
```

## Testing with Fake MCP Transport

Neuron provides `FakeMcpTransport` for testing:

```php
use NeuronAI\Testing\FakeMcpTransport;
use NeuronAI\MCP\McpConnector;

$transport = new FakeMcpTransport(
    ['jsonrpc' => '2.0', 'id' => 1, 'result' => []],
    ['jsonrpc' => '2.0', 'id' => 2, 'result' => ['tools' => []]],
);

$connector = new McpConnector(['transport' => $transport]);
$tools = $connector->tools();

$transport->assertInitialized();
$transport->assertToolsListCalled();
```

## Release Checklist

- [ ] All tests pass (`vendor/bin/phpunit`)
- [ ] Static analysis passes (`vendor/bin/phpstan analyse`)
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Version bumped in composer.json
- [ ] Git tag created

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

MIT License - see LICENSE file for details.
