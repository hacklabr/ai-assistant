<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Skills;

use HackLab\AIAssistant\Skills\Skill;
use HackLab\AIAssistant\Skills\SkillRegistry;
use PHPUnit\Framework\TestCase;

class SkillRegistryTest extends TestCase
{
    public function testRegisterAndRetrieve(): void
    {
        $registry = new SkillRegistry();

        $skill = new Skill(
            name: 'test-skill',
            description: 'Test description',
            content: 'Test content',
        );

        $registry->register($skill);

        $this->assertTrue($registry->has('test-skill'));
        $this->assertSame($skill, $registry->get('test-skill'));
    }

    public function testGetNonExistent(): void
    {
        $registry = new SkillRegistry();

        $this->assertNull($registry->get('non-existent'));
    }

    public function testSearch(): void
    {
        $registry = new SkillRegistry();

        $registry->register(new Skill('security', 'Security skill', 'Check for SQL injection'));
        $registry->register(new Skill('performance', 'Performance skill', 'Optimize queries'));

        $results = $registry->search('SQL');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('security', $results);
    }
}
