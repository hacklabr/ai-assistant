<?php

/**
 * CLI Example - Interactive AI Assistant
 *
 * Usage:
 *   php examples/cli-assistant.php
 *
 * Interactive setup on first run. Saves config to ~/.hacklab-ai-assistant.json
 */

require __DIR__ . '/../vendor/autoload.php';

use HackLab\AIAssistant\Assistant;
use HackLab\AIAssistant\AssistantConfig;
use HackLab\AIAssistant\SubAgents\SubAgentConfig;
use HackLab\AIAssistant\Utils\ConfigStorage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\XAI\XAI;
use NeuronAI\Providers\Mistral\Mistral;

$configStorage = new ConfigStorage();
$config = $configStorage->load();

// Provider definitions
$providers = [
    'anthropic' => ['name' => 'Anthropic Claude', 'model' => 'claude-sonnet-4', 'needsKey' => true],
    'openai' => ['name' => 'OpenAI GPT', 'model' => 'gpt-4o', 'needsKey' => true],
    'gemini' => ['name' => 'Google Gemini', 'model' => 'gemini-2.0-flash', 'needsKey' => true],
    'ollama' => ['name' => 'Ollama (local)', 'model' => 'llama3.2', 'needsKey' => false],
    'deepseek' => ['name' => 'Deepseek', 'model' => 'deepseek-chat', 'needsKey' => true],
    'grok' => ['name' => 'xAI Grok', 'model' => 'grok-2', 'needsKey' => true],
    'mistral' => ['name' => 'Mistral AI', 'model' => 'mistral-large-latest', 'needsKey' => true],
    'zai' => ['name' => 'Z.AI (General)', 'model' => 'glm-5.1', 'needsKey' => true],
    'zai-coding' => ['name' => 'Z.AI Coding Plan', 'model' => 'glm-5.1', 'needsKey' => true],
];

function ask(string $prompt, ?string $default = null): string
{
    $display = $default !== null ? "$prompt [$default]: " : "$prompt: ";
    $input = readline($display);
    return $input !== false && trim($input) !== '' ? trim($input) : ($default ?? '');
}

function askHidden(string $prompt): string
{
    if (!posix_isatty(STDIN)) {
        return ask($prompt);
    }

    echo "$prompt: ";
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        $input = rtrim(fgets(STDIN) ?: '', "\r\n");
    } else {
        shell_exec('stty -echo');
        $input = rtrim(fgets(STDIN) ?: '', "\n");
        shell_exec('stty echo');
        echo "\n";
    }
    return $input;
}

function selectProvider(array $providers): string
{
    echo "\nSelect AI Provider:\n";
    $idx = 1;
    $keys = array_keys($providers);
    foreach ($providers as $key => $info) {
        $keyHint = $info['needsKey'] ? ' (requires API key)' : '';
        echo "  {$idx}. {$info['name']}{$keyHint}\n";
        $idx++;
    }

    $choice = (int) ask('Enter number', '1');
    $choice = max(1, min($choice, count($keys)));
    return $keys[$choice - 1];
}

// Interactive setup if no saved config
if (empty($config)) {
    echo "=== HackLab AI Assistant Setup ===\n";

    $providerKey = selectProvider($providers);
    $providerInfo = $providers[$providerKey];

    $model = ask('Model', $providerInfo['model']);

    $apiKey = '';
    if ($providerInfo['needsKey']) {
        $envVar = strtoupper(str_replace('-', '_', $providerKey)) . '_API_KEY';
        $envKey = getenv($envVar);

        if ($envKey) {
            echo "Found {$envVar} environment variable.\n";
            $useEnv = strtolower(ask('Use it? (y/n)', 'y')) === 'y';
            if ($useEnv) {
                $apiKey = $envKey;
            }
        }

        if (empty($apiKey)) {
            $apiKey = askHidden('API Key');
        }
    }

    $config = [
        'provider' => $providerKey,
        'model' => $model,
        'apiKey' => $apiKey,
    ];

    $save = strtolower(ask('Save configuration for future runs? (y/n)', 'y')) === 'y';
    if ($save) {
        $configStorage->save($config);
        echo "Config saved to {$configStorage->getPath()}\n";
    }
} else {
    $providerName = $providers[$config['provider']]['name'] ?? $config['provider'];
    echo "Using saved configuration (provider: {$providerName})\n";
    echo "Delete ~/.hacklab-ai-assistant.json to reconfigure.\n\n";
}

// Build provider instance
$providerKey = $config['provider'];
$model = $config['model'];
$apiKey = $config['apiKey'] ?? '';

$provider = match ($providerKey) {
    'anthropic' => new Anthropic(key: $apiKey, model: $model),
    'openai' => new OpenAI(key: $apiKey, model: $model),
    'gemini' => new Gemini(key: $apiKey, model: $model),
    'ollama' => new Ollama(model: $model),
    'deepseek' => new Deepseek(key: $apiKey, model: $model),
    'grok' => new XAI(key: $apiKey, model: $model),
    'mistral' => new Mistral(key: $apiKey, model: $model),
    'zai' => new OpenAILike(
        baseUri: 'https://api.z.ai/api/paas/v4',
        key: $apiKey,
        model: $model,
        parameters: [],
        strict_response: false,
        httpClient: null
    ),
    'zai-coding' => new OpenAILike(
        baseUri: 'https://api.z.ai/api/coding/paas/v4',
        key: $apiKey,
        model: $model,
        parameters: [],
        strict_response: false,
        httpClient: null
    ),
    default => throw new \RuntimeException("Unknown provider: {$providerKey}"),
};

$assistant = Assistant::configure(
    new AssistantConfig(
        provider: $provider,
        instructions: 'You are a helpful coding assistant. You have access to specialized sub-agents and a learning system. Delegate tasks that require specific expertise. Record learnings when you discover useful patterns or bugs.',
        skillsPath: __DIR__ . '/../skills',
        storagePath: __DIR__ . '/../storage',
        autoLearn: true,
        learningPath: __DIR__ . '/../storage/learning',
        subAgents: [
            'code-reviewer' => new SubAgentConfig(
                id: 'code-reviewer',
                provider: $provider,
                instructions: 'You are an expert code reviewer focused on security, performance, and best practices. Analyze code thoroughly and provide actionable feedback.',
                skills: ['security'],
                contextStrategy: 'code-focused',
                contextWindow: 8000,
            ),
        ],
    )
);

echo "\nAI Assistant (type 'exit' to quit)\n";
echo "The assistant can auto-delegate to sub-agents when appropriate.\n\n";

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
        $message = new UserMessage($input);
        $response = $assistant->chat($message)->getMessage();
        echo $response->getContent() . "\n\n";
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

echo "Goodbye!\n";
