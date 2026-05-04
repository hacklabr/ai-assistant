<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\SubAgents;

use HackLab\AIAssistant\Skills\SkillRegistry;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\Message;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SubAgentFactory
{
    public function __construct(
        private readonly SkillRegistry $skillRegistry,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function create(SubAgentConfig $config): Agent
    {
        $this->logger->debug('Creating sub-agent instance', [
            'id' => $config->id,
            'tools_count' => count($config->tools),
            'skills' => $config->skills,
        ]);

        return new SubAgentInstance($config, $this->skillRegistry);
    }

    public function createWithHistory(SubAgentConfig $config, array $messages): Agent
    {
        $agent = $this->create($config);

        foreach ($messages as $message) {
            $agent->getChatHistory()->addMessage($message);
        }

        $this->logger->debug('Sub-agent created with history', [
            'id' => $config->id,
            'history_messages' => count($messages),
        ]);

        return $agent;
    }
}
