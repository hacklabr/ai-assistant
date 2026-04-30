<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\MCP;

use NeuronAI\MCP\McpConnector;

/**
 * Bridge for simplifying MCP configuration.
 * Wraps Neuron's native MCP support.
 */
class McpConfigBridge
{
    /**
     * Create McpConnector from configuration array.
     *
     * @param array<string, mixed> $config
     */
    public static function make(array $config): McpConnector
    {
        $type = $config['type'] ?? 'stdio';

        return match ($type) {
            'stdio' => self::createStdio($config),
            'sse' => self::createSse($config),
            'http' => self::createHttp($config),
            default => throw new \InvalidArgumentException("Unknown MCP type: {$type}"),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createStdio(array $config): McpConnector
    {
        if (!isset($config['command'])) {
            throw new \InvalidArgumentException('MCP stdio requires "command"');
        }

        return new McpConnector([
            'command' => $config['command'],
            'args' => $config['args'] ?? [],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createSse(array $config): McpConnector
    {
        if (!isset($config['url'])) {
            throw new \InvalidArgumentException('MCP SSE requires "url"');
        }

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

        return new McpConnector($connectorConfig);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createHttp(array $config): McpConnector
    {
        if (!isset($config['url'])) {
            throw new \InvalidArgumentException('MCP HTTP requires "url"');
        }

        $connectorConfig = [
            'url' => $config['url'],
        ];

        if (isset($config['token'])) {
            $connectorConfig['token'] = $config['token'];
        }

        if (isset($config['headers'])) {
            $connectorConfig['headers'] = $config['headers'];
        }

        return new McpConnector($connectorConfig);
    }
}
