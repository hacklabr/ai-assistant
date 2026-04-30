# Examples

## CLI Example (readline)

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use HackLab\AIAssistant\Assistant;
use HackLab\AIAssistant\AssistantConfig;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Anthropic\Anthropic;

$assistant = Assistant::configure(
    new AssistantConfig(
        provider: new Anthropic(
            key: getenv('ANTHROPIC_API_KEY'),
            model: 'claude-sonnet-4',
        ),
        instructions: 'You are a helpful coding assistant.',
        skillsPath: __DIR__ . '/skills',
        storagePath: __DIR__ . '/storage',
        subAgents: [
            'code-reviewer' => [
                'provider' => new Anthropic(
                    key: getenv('ANTHROPIC_API_KEY'),
                    model: 'claude-sonnet-4',
                ),
                'instructions' => 'You are an expert code reviewer.',
                'skills' => ['security', 'psr12'],
                'contextStrategy' => 'code-focused',
            ],
        ],
    )
);

echo "AI Assistant (type 'exit' to quit)\n";
echo "Commands: /review, /help\n\n";

while (true) {
    $input = readline('> ');
    
    if ($input === false || strtolower($input) === 'exit') {
        break;
    }
    
    if (empty($input)) {
        continue;
    }
    
    readline_add_history($input);
    
    try {
        if (str_starts_with($input, '/review')) {
            $message = new UserMessage(substr($input, 8));
            $result = $assistant->delegate('code-reviewer', $message);
            echo $result->message->getContent() . "\n\n";
        } else {
            $message = new UserMessage($input);
            $response = $assistant->chat($message)->getMessage();
            echo $response->getContent() . "\n\n";
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

echo "Goodbye!\n";
```

## Web API Example (Pure PHP)

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use HackLab\AIAssistant\Assistant;
use HackLab\AIAssistant\AssistantConfig;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\OpenAI\OpenAI;

$assistant = Assistant::configure(
    new AssistantConfig(
        provider: new OpenAI(
            key: getenv('OPENAI_API_KEY'),
            model: 'gpt-4',
        ),
        storagePath: __DIR__ . '/storage',
        autoLearn: true,
    )
);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$threadId = $input['thread_id'] ?? uniqid('thread_');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

try {
    // Load previous conversation if exists
    $history = $assistant->getStorage()->load("conversations/$threadId");
    if ($history) {
        $assistant->getChatHistory()->setMessages($history['messages']);
    }
    
    $response = $assistant->chat(new UserMessage($message))->getMessage();
    
    // Save conversation
    $assistant->getStorage()->save("conversations/$threadId", [
        'messages' => $assistant->getChatHistory()->getMessages(),
        'updated_at' => date('c'),
    ]);
    
    echo json_encode([
        'thread_id' => $threadId,
        'response' => $response->getContent(),
    ]);
    
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

## Sub-Agent Configuration Example

```php
use HackLab\AIAssistant\SubAgentConfig;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Tools\Toolkits\FileSystem\FileSystemToolkit;

$config = new AssistantConfig(
    provider: new Anthropic('key', 'claude-sonnet-4'),
    subAgents: [
        'code-reviewer' => new SubAgentConfig(
            id: 'code-reviewer',
            provider: new Anthropic('key', 'claude-sonnet-4'),
            instructions: <<<PROMPT
                You are an expert code reviewer with 20 years of experience.
                Review code for:
                - Security vulnerabilities
                - Performance issues
                - Code style compliance
                - Logic errors
                
                Be thorough but constructive.
            PROMPT,
            tools: [
                FileSystemToolkit::make(),
            ],
            skills: ['security', 'psr12', 'performance'],
            contextStrategy: 'code-focused',
            contextWindow: 8000,
            mcps: [
                [
                    'type' => 'stdio',
                    'command' => 'npx',
                    'args' => ['@modelcontextprotocol/server-github'],
                ],
            ],
        ),
        
        'architect' => new SubAgentConfig(
            id: 'architect',
            provider: new Anthropic('key', 'claude-opus-4'),
            instructions: 'You are a software architect specializing in distributed systems.',
            skills: ['c4-model', 'adr'],
            contextStrategy: 'architecture-focused',
            contextWindow: 200000,
        ),
    ],
);
```

## MCP Configuration Examples

```php
// GitHub MCP via npx
$githubMcp = [
    'type' => 'stdio',
    'command' => 'npx',
    'args' => ['-y', '@modelcontextprotocol/server-github'],
];

// Database MCP via local script
$dbMcp = [
    'type' => 'stdio',
    'command' => 'php',
    'args' => [__DIR__ . '/mcp/database-server.php'],
];

// Remote MCP via SSE
$remoteMcp = [
    'type' => 'sse',
    'url' => 'https://mcp.example.com/sse',
    'token' => getenv('MCP_TOKEN'),
];

// HTTP streaming MCP
$httpMcp = [
    'type' => 'http',
    'url' => 'https://api.example.com/mcp',
    'headers' => [
        'Authorization' => 'Bearer ' . getenv('API_KEY'),
    ],
];
```

## Custom Storage Example

```php
use HackLab\AIAssistant\Persistence\StorageInterface;

class DatabaseStorage implements StorageInterface
{
    public function __construct(protected PDO $pdo) {}
    
    public function save(string $key, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO storage (key, data, updated_at) 
             VALUES (:key, :data, NOW())
             ON DUPLICATE KEY UPDATE data = :data, updated_at = NOW()'
        );
        $stmt->execute([
            ':key' => $key,
            ':data' => json_encode($data),
        ]);
    }
    
    public function load(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT data FROM storage WHERE key = :key');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? json_decode($row['data'], true) : null;
    }
    
    public function delete(string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM storage WHERE key = :key');
        $stmt->execute([':key' => $key]);
    }
    
    public function list(string $pattern = '*'): array
    {
        $stmt = $this->pdo->prepare('SELECT key FROM storage WHERE key LIKE :pattern');
        $stmt->execute([':pattern' => str_replace('*', '%', $pattern)]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function exists(string $key): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM storage WHERE key = :key');
        $stmt->execute([':key' => $key]);
        return (bool) $stmt->fetch();
    }
}

// Usage
$assistant = Assistant::configure(
    new AssistantConfig(
        provider: $provider,
        storage: new DatabaseStorage($pdo),
    )
);
```
