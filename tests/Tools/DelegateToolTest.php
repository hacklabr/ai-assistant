<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Tools;

use HackLab\AIAssistant\Context\CondensedContext;
use HackLab\AIAssistant\SubAgents\SubAgentConfig;
use HackLab\AIAssistant\SubAgents\SubAgentDispatcher;
use HackLab\AIAssistant\SubAgents\SubAgentRegistry;
use HackLab\AIAssistant\SubAgents\SubAgentResult;
use HackLab\AIAssistant\Tools\DelegateTool;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\OpenAI\OpenAI;
use PHPUnit\Framework\TestCase;

class DelegateToolTest extends TestCase
{
    public function testToolNameAndDescription(): void
    {
        $registry = new SubAgentRegistry();
        $registry->register('test-agent', $this->createMockConfig('test-agent'));

        $dispatcher = $this->createMock(SubAgentDispatcher::class);
        $tool = new DelegateTool($dispatcher, $registry, fn() => []);

        $this->assertSame('delegate_to_subagent', $tool->getName());
        $this->assertStringContainsString('test-agent', $tool->getDescription());
        $this->assertStringContainsString('Delegate a specialized task', $tool->getDescription());
    }

    public function testPropertiesIncludeSubAgentIds(): void
    {
        $registry = new SubAgentRegistry();
        $registry->register('reviewer', $this->createMockConfig('reviewer'));
        $registry->register('architect', $this->createMockConfig('architect'));

        $dispatcher = $this->createMock(SubAgentDispatcher::class);
        $tool = new DelegateTool($dispatcher, $registry, fn() => []);

        $properties = $tool->getProperties();
        $this->assertCount(3, $properties);

        $subAgentProperty = $properties[0];
        $this->assertSame('sub_agent_id', $subAgentProperty->getName());
        $this->assertSame(['reviewer', 'architect'], $subAgentProperty->getEnum());
    }

    public function testInvokeDelegatesToSubAgent(): void
    {
        $registry = new SubAgentRegistry();
        $registry->register('reviewer', $this->createMockConfig('reviewer'));

        $message = new UserMessage('Test result');
        $state = $this->createMock(\NeuronAI\Agent\AgentState::class);
        $state->method('getSteps')->willReturn([]);

        $result = new SubAgentResult(
            message: $message,
            state: $state,
            context: new CondensedContext(messages: [], strategy: 'test'),
        );

        $dispatcher = $this->createMock(SubAgentDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('delegate')
            ->with('reviewer', $this->isInstanceOf(UserMessage::class), [])
            ->willReturn($result);

        $tool = new DelegateTool($dispatcher, $registry, fn() => []);
        $tool->setInputs(['sub_agent_id' => 'reviewer', 'task' => 'Review this code']);

        $tool->execute();

        $this->assertSame('Test result', $tool->getResult());
    }

    public function testInvokeReturnsErrorForUnknownSubAgent(): void
    {
        $registry = new SubAgentRegistry();
        $dispatcher = $this->createMock(SubAgentDispatcher::class);

        $tool = new DelegateTool($dispatcher, $registry, fn() => []);
        $tool->setInputs(['sub_agent_id' => 'unknown', 'task' => 'Do something']);

        $tool->execute();

        $this->assertStringContainsString('Error', $tool->getResult());
        $this->assertStringContainsString('unknown', $tool->getResult());
    }

    public function testInvokePassesCurrentMessages(): void
    {
        $registry = new SubAgentRegistry();
        $registry->register('reviewer', $this->createMockConfig('reviewer'));

        $currentMessages = [new UserMessage('Previous context')];
        $capturedMessages = null;

        $dispatcher = $this->createMock(SubAgentDispatcher::class);
        $dispatcher->method('delegate')
            ->willReturnCallback(function ($id, $msg, $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                $state = $this->createMock(\NeuronAI\Agent\AgentState::class);
                $state->method('getSteps')->willReturn([]);
                return new SubAgentResult(
                    message: new UserMessage('Done'),
                    state: $state,
                    context: new CondensedContext(messages: [], strategy: 'test'),
                );
            });

        $tool = new DelegateTool($dispatcher, $registry, fn() => $currentMessages);
        $tool->setInputs(['sub_agent_id' => 'reviewer', 'task' => 'Review']);
        $tool->execute();

        $this->assertSame($currentMessages, $capturedMessages);
    }

    private function createMockConfig(string $id): SubAgentConfig
    {
        return new SubAgentConfig(
            id: $id,
            provider: $this->createMock(OpenAI::class),
            instructions: "Test instructions for {$id}",
        );
    }
}
