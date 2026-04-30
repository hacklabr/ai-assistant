<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\SubAgents;

use HackLab\AIAssistant\SubAgents\SubAgentConfig;
use HackLab\AIAssistant\SubAgents\SubAgentRegistry;
use NeuronAI\Providers\Anthropic\Anthropic;
use PHPUnit\Framework\TestCase;

class SubAgentRegistryTest extends TestCase
{
    public function testRegisterAndRetrieve(): void
    {
        $registry = new SubAgentRegistry();

        $config = new SubAgentConfig(
            id: 'test-agent',
            provider: new Anthropic('test-key', 'claude-test'),
            instructions: 'Test instructions',
        );

        $registry->register('test-agent', $config);

        $this->assertTrue($registry->has('test-agent'));
        $this->assertSame($config, $registry->get('test-agent'));
    }

    public function testGetNonExistentThrows(): void
    {
        $registry = new SubAgentRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('non-existent');
    }
}
