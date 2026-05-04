<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\MCP;

use NeuronAI\MCP\McpConnector;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class McpConfigBridge
{
    private const array ALLOWED_COMMANDS = [
        'npx',
        'node',
        'python3',
        'python',
        'uvx',
        'docker',
        'php',
    ];

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public static function make(array $config, ?LoggerInterface $logger = null): McpConnector
    {
        return (new self($logger ?? new NullLogger()))->create($config);
    }

    public function create(array $config): McpConnector
    {
        $type = $config['type'] ?? 'stdio';

        return match ($type) {
            'stdio' => $this->createStdio($config),
            'sse' => $this->createSse($config),
            'http' => $this->createHttp($config),
            default => throw new \InvalidArgumentException("Unknown MCP type: {$type}"),
        };
    }

    private function createStdio(array $config): McpConnector
    {
        if (!isset($config['command'])) {
            throw new \InvalidArgumentException('MCP stdio requires "command"');
        }

        $command = basename($config['command']);

        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            $this->logger->error('MCP command blocked: command not in allowlist', [
                'command' => $command,
                'allowed' => self::ALLOWED_COMMANDS,
            ]);
            throw new \InvalidArgumentException(
                "MCP command '{$command}' is not allowed. Allowed: " . implode(', ', self::ALLOWED_COMMANDS)
            );
        }

        $args = $config['args'] ?? [];
        $this->validateArgs($args);

        $this->logger->info('MCP stdio connection created', ['command' => $command]);

        return new McpConnector([
            'command' => $command,
            'args' => $args,
        ]);
    }

    private function createSse(array $config): McpConnector
    {
        if (!isset($config['url'])) {
            throw new \InvalidArgumentException('MCP SSE requires "url"');
        }

        $this->validateUrl($config['url']);

        $connectorConfig = [
            'url' => $config['url'],
            'async' => true,
        ];

        if (isset($config['token'])) {
            $connectorConfig['token'] = $config['token'];
        }

        if (isset($config['headers'])) {
            $connectorConfig['headers'] = $config['headers'];
        }

        if (isset($config['timeout'])) {
            $connectorConfig['timeout'] = $config['timeout'];
        }

        $this->logger->info('MCP SSE connection created', ['url' => $this->redactUrl($config['url'])]);

        return new McpConnector($connectorConfig);
    }

    private function createHttp(array $config): McpConnector
    {
        if (!isset($config['url'])) {
            throw new \InvalidArgumentException('MCP HTTP requires "url"');
        }

        $this->validateUrl($config['url']);

        $connectorConfig = [
            'url' => $config['url'],
        ];

        if (isset($config['token'])) {
            $connectorConfig['token'] = $config['token'];
        }

        if (isset($config['headers'])) {
            $connectorConfig['headers'] = $config['headers'];
        }

        $this->logger->info('MCP HTTP connection created', ['url' => $this->redactUrl($config['url'])]);

        return new McpConnector($connectorConfig);
    }

    private function validateArgs(array $args): void
    {
        foreach ($args as $arg) {
            if (!is_string($arg)) {
                continue;
            }

            if (str_contains($arg, '..') || str_starts_with($arg, '/') || str_starts_with($arg, '~')) {
                $this->logger->error('MCP arg blocked: path traversal detected', ['arg' => $arg]);
                throw new \InvalidArgumentException("MCP argument contains invalid path: {$arg}");
            }

            $dangerousChars = ['|', '`', '$', ';', '&', '>', '<'];
            foreach ($dangerousChars as $char) {
                if (str_contains($arg, $char)) {
                    $this->logger->error('MCP arg blocked: dangerous character', ['arg' => $arg]);
                    throw new \InvalidArgumentException("MCP argument contains dangerous character: {$char}");
                }
            }
        }
    }

    private function validateUrl(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new \InvalidArgumentException("Invalid MCP URL: {$url}");
        }

        if (!in_array($parsed['scheme'], ['http', 'https'], true)) {
            throw new \InvalidArgumentException("MCP URL must use http or https scheme, got: {$parsed['scheme']}");
        }

        $host = $parsed['host'];
        $blocked = ['169.254.169.254', 'metadata.google.internal', 'localhost', '127.0.0.1', '0.0.0.0'];

        if (in_array($host, $blocked, true) || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            $this->logger->warning('MCP URL points to internal/local address', ['host' => $host]);
        }
    }

    private function redactUrl(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return '[invalid-url]';
        }

        $host = $parsed['host'] ?? 'unknown';
        $path = $parsed['path'] ?? '/';
        $scheme = $parsed['scheme'] ?? 'https';

        return "{$scheme}://{$host}{$path}";
    }
}
