# MCP Integration

The framework leverages Neuron AI's native MCP support. We provide a configuration bridge to simplify setting up MCP connections for sub-agents.

## Neuron's Native MCP Support

Neuron AI provides three transport implementations:

| Transport | Class | Protocol | Use Case |
|-----------|-------|----------|----------|
| stdio | `StdioTransport` | Standard I/O | Local MCP processes |
| SSE | `SseHttpTransport` | Server-Sent Events | Remote servers with SSE |
| HTTP | `StreamableHttpTransport` | HTTP streaming | Standard HTTP MCP |

And the connector:
- `McpClient`: Manages JSON-RPC 2.0 communication
- `McpConnector`: Discovers and exposes tools from MCP servers

## McpConfigBridge

Our wrapper simplifies configuration:

```php
class McpConfigBridge
{
    /**
     * Create McpConnector from configuration array.
     *
     * @param array $config MCP configuration
     * @return McpConnector Neuron MCP connector
     */
    public static function make(array $config): McpConnector
    {
        return match ($config['type'] ?? 'stdio') {
            'stdio' => self::createStdio($config),
            'sse' => self::createSse($config),
            'http' => self::createHttp($config),
            default => throw new \InvalidArgumentException("Unknown MCP type: {$config['type']}"),
        };
    }
    
    protected static function createStdio(array $config): McpConnector
    {
        return new McpConnector([
            'command' => $config['command'],
            'args' => $config['args'] ?? [],
        ]);
    }
    
    protected static function createSse(array $config): McpConnector
    {
        return new McpConnector([
            'url' => $config['url'],
            'token' => $config['token'] ?? null,
            'headers' => $config['headers'] ?? [],
            'timeout' => $config['timeout'] ?? 30,
        ]);
    }
    
    protected static function createHttp(array $config): McpConnector
    {
        return new McpConnector([
            'url' => $config['url'],
            'token' => $config['token'] ?? null,
            'headers' => $config['headers'] ?? [],
        ]);
    }
}
```

## Configuration Examples

### stdio (Local Process)

```php
'mcps' => [
    [
        'type' => 'stdio',
        'command' => 'npx',
        'args' => ['-y', '@modelcontextprotocol/server-github'],
    ],
    [
        'type' => 'stdio',
        'command' => 'php',
        'args' => ['/path/to/custom/mcp-server.php'],
    ],
]
```

### SSE (Server-Sent Events)

```php
'mcps' => [
    [
        'type' => 'sse',
        'url' => 'http://localhost:8080/sse',
        'token' => 'optional-bearer-token',
        'timeout' => 60,
    ],
]
```

### HTTP Streaming

```php
'mcps' => [
    [
        'type' => 'http',
        'url' => 'https://api.example.com/mcp',
        'headers' => [
            'X-API-Key' => 'your-api-key',
        ],
    ],
]
```

## Integration with Sub-Agent

MCP tools are automatically discovered and added to the sub-agent:

```php
protected function tools(): array
{
    $tools = [
        CalculatorToolkit::make(),
        FileSystemToolkit::make(),
    ];
    
    // Add MCP tools
    foreach ($this->config->mcps as $mcpConfig) {
        $connector = McpConfigBridge::make($mcpConfig);
        $tools = array_merge($tools, $connector->tools());
    }
    
    return $tools;
}
```

## Tool Filtering

You can filter which MCP tools are exposed:

```php
$connector = McpConfigBridge::make($config)
    ->only(['search_repositories', 'get_file_contents'])  // Only these
    ->exclude(['delete_repository']);                     // Exclude this

$tools = $connector->tools();
```

## MCP Server Discovery

For SSE and HTTP transports, the MCP server is discovered automatically:

1. `McpClient` connects to the endpoint
2. Sends `initialize` request (JSON-RPC 2.0)
3. Receives server capabilities
4. Calls `tools/list` to discover available tools
5. `McpConnector` wraps each tool as a Neuron `ToolInterface`

## Error Handling

MCP connection errors are handled gracefully:

```php
try {
    $connector = McpConfigBridge::make($config);
    $tools = $connector->tools();
} catch (McpException $e) {
    // Log error, continue without MCP tools
    $this->logger->error('MCP connection failed: ' . $e->getMessage());
    $tools = [];
}
```

## Custom Transport

If you need a custom transport, implement `McpTransportInterface`:

```php
use NeuronAI\MCP\McpTransportInterface;

class CustomTransport implements McpTransportInterface
{
    public function connect(): void;
    public function send(array $data): void;
    public function receive(): array;
    public function disconnect(): void;
}
```

Then pass it directly:

```php
$connector = new McpConnector([
    'transport' => new CustomTransport(),
]);
```
