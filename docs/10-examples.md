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

## Structured Output — Map Configuration

This example shows how to use the assistant to generate structured map configurations that can be returned as JSON to a frontend application.

### Define the output classes

```php
<?php

namespace App\Output;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;
use NeuronAI\StructuredOutput\Validation\Rules\Count;
use NeuronAI\StructuredOutput\Validation\Rules\LowerThanEqual;

class MapLayer
{
    #[SchemaProperty(description: 'Layer name', required: true)]
    #[NotBlank]
    public string $name;

    #[SchemaProperty(description: 'Layer type: raster, vector, tile', required: true)]
    #[NotBlank]
    public string $type;

    #[SchemaProperty(description: 'Source URL or identifier', required: true)]
    #[NotBlank]
    public string $source;

    #[SchemaProperty(description: 'Default visibility', required: false)]
    public bool $visible = true;

    #[SchemaProperty(description: 'Opacity from 0 to 1', required: false, min: 0, max: 1)]
    #[LowerThanEqual(reference: 1)]
    public float $opacity = 1.0;
}

class MapConfig
{
    #[SchemaProperty(description: 'Map title', required: true)]
    #[NotBlank]
    public string $title;

    #[SchemaProperty(description: 'Map layers', required: true, anyOf: [MapLayer::class])]
    #[Count(min: 1)]
    public array $layers;

    #[SchemaProperty(description: 'Center coordinates [lat, lng]', required: false)]
    public ?array $center = null;

    #[SchemaProperty(description: 'Default zoom level', required: false)]
    public ?int $zoom = null;

    #[SchemaProperty(description: 'Map projection (e.g. EPSG:3857)', required: false)]
    public string $projection = 'EPSG:3857';
}
```

### API endpoint returning JSON

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Output\MapConfig;
use HackLab\AIAssistant\Assistant;
use HackLab\AIAssistant\AssistantConfig;
use HackLab\AIAssistant\Persistence\FileStorage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Anthropic\Anthropic;

$assistant = Assistant::configure(
    new AssistantConfig(
        provider: new Anthropic(
            key: getenv('ANTHROPIC_API_KEY'),
            model: 'claude-sonnet-4',
        ),
        storage: new FileStorage(__DIR__ . '/../storage'),
        instructions: <<<PROMPT
            You are a map configuration assistant. Based on the user's description,
            select appropriate map layers, set center coordinates, and zoom level.
            Always use standard map service URLs for sources.
            Prefer OpenStreetMap, Mapbox, or ArcGIS tile services when applicable.
        PROMPT,
        outputClass: MapConfig::class,
        structuredMaxRetries: 2,
    )
);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$description = $input['description'] ?? '';

if (empty($description)) {
    http_response_code(400);
    echo json_encode(['error' => 'Map description is required']);
    exit;
}

try {
    $config = $assistant->structured(
        new UserMessage($description)
    );

    echo json_encode($config, JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### Frontend request / response

**Request:**

```bash
curl -X POST http://localhost:8080/map-config \
  -H 'Content-Type: application/json' \
  -d '{"description": "Street map of São Paulo with satellite imagery overlay and bus routes"}'
```

**Response:**

```json
{
    "title": "São Paulo Street Map with Satellite and Bus Routes",
    "layers": [
        {
            "name": "Satellite Imagery",
            "type": "raster",
            "source": "https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
            "visible": true,
            "opacity": 0.7
        },
        {
            "name": "Street Network",
            "type": "vector",
            "source": "mapbox://mapbox.mapbox-streets-v8",
            "visible": true,
            "opacity": 1.0
        },
        {
            "name": "Bus Routes",
            "type": "vector",
            "source": "sptrans://linhas-onibus",
            "visible": true,
            "opacity": 0.9
        }
    ],
    "center": [-23.5505, -46.6333],
    "zoom": 12,
    "projection": "EPSG:3857"
}
```

## Structured Output — Data Extraction

Extract structured data from unstructured text without a default output class:

```php
use HackLab\AIAssistant\Assistant;
use HackLab\AIAssistant\AssistantConfig;
use HackLab\AIAssistant\Persistence\FileStorage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\Email;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

class ContactInfo
{
    #[SchemaProperty(description: 'Full name', required: true)]
    #[NotBlank]
    public string $name;

    #[SchemaProperty(description: 'Email address', required: true)]
    #[Email]
    public string $email;

    #[SchemaProperty(description: 'Phone number', required: false)]
    public ?string $phone = null;

    #[SchemaProperty(description: 'Company name', required: false)]
    public ?string $company = null;
}

$assistant = Assistant::configure(
    new AssistantConfig(
        provider: new OpenAI(getenv('OPENAI_API_KEY'), 'gpt-4o'),
        storage: new FileStorage(__DIR__ . '/storage'),
        instructions: 'Extract contact information from the provided text.',
    )
);

$contact = $assistant->structured(
    new UserMessage('Please reach out to Maria Santos at maria@techcorp.com or (11) 98765-4321. She works at TechCorp Brasil.'),
    ContactInfo::class,
);

echo $contact->name;    // Maria Santos
echo $contact->email;   // maria@techcorp.com
echo $contact->phone;   // (11) 98765-4321
echo $contact->company; // TechCorp Brasil
```

## Structured Output — Report Generation

Using nested objects and arrays with validation:

```php
use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;
use NeuronAI\StructuredOutput\Validation\Rules\Count;
use NeuronAI\StructuredOutput\Validation\Rules\WordsCount;

class Metric
{
    #[SchemaProperty(description: 'Metric name', required: true)]
    #[NotBlank]
    public string $name;

    #[SchemaProperty(description: 'Metric value', required: true)]
    public string $value;

    #[SchemaProperty(description: 'Trend: up, down, stable', required: false)]
    public string $trend = 'stable';
}

class ReportSection
{
    #[SchemaProperty(description: 'Section title', required: true)]
    #[NotBlank]
    public string $title;

    #[SchemaProperty(description: 'Section content summary', required: true)]
    #[WordsCount(min: 10, max: 200)]
    public string $content;

    #[SchemaProperty(description: 'Section metrics', required: false, anyOf: [Metric::class])]
    public array $metrics = [];
}

class Report
{
    #[SchemaProperty(description: 'Report title', required: true)]
    #[NotBlank]
    public string $title;

    #[SchemaProperty(description: 'Executive summary', required: true)]
    #[WordsCount(min: 20, max: 500)]
    public string $summary;

    #[SchemaProperty(description: 'Report sections', required: true, anyOf: [ReportSection::class])]
    #[Count(min: 1, max: 10)]
    public array $sections;

    #[SchemaProperty(description: 'List of recommendations', required: false)]
    public array $recommendations = [];
}

$assistant = Assistant::configure(
    new AssistantConfig(
        provider: $provider,
        storage: $storage,
        instructions: 'You generate analytical reports from raw data descriptions.',
        outputClass: Report::class,
        structuredMaxRetries: 3,
    )
);

$report = $assistant->structured(
    new UserMessage('Generate a quarterly sales report for Q1 2026. Revenue was $2.4M, up 15% from Q4. Customer acquisition cost dropped to $45.'),
);

header('Content-Type: application/json');
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```
