<?php

/**
 * CLI Example - Interactive AI Assistant
 *
 * Usage:
 *   php examples/cli-assistant.php
 *
 * Requires ANTHROPIC_API_KEY environment variable.
 */

require __DIR__ . '/../vendor/autoload.php';

use HackLab\AIAssistant\Assistant;
use HackLab\AIAssistant\AssistantConfig;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Anthropic\Anthropic;

if (!getenv('ANTHROPIC_API_KEY')) {
    echo "Error: ANTHROPIC_API_KEY environment variable is required\n";
    exit(1);
}

$assistant = Assistant::configure(
    new AssistantConfig(
        provider: new Anthropic(
            key: getenv('ANTHROPIC_API_KEY'),
            model: 'claude-sonnet-4',
        ),
        instructions: 'You are a helpful coding assistant.',
        skillsPath: __DIR__ . '/../skills',
        storagePath: __DIR__ . '/../storage',
    )
);

echo "AI Assistant (type 'exit' to quit)\n";
echo "Commands: /review - delegate to code reviewer\n\n";

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
            echo $result->getContent() . "\n\n";
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
