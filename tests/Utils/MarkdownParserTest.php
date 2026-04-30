<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Utils;

use HackLab\AIAssistant\Utils\MarkdownParser;
use PHPUnit\Framework\TestCase;

class MarkdownParserTest extends TestCase
{
    public function testParseFrontmatter(): void
    {
        $parser = new MarkdownParser();

        $markdown = "---\nname: Test Skill\ndescription: A test skill\ntools:\n  - ToolA\n  - ToolB\n---\n\n# Content\n\nThis is the body.";

        $result = $parser->parse($markdown);

        $this->assertSame('Test Skill', $result['frontmatter']['name']);
        $this->assertSame('A test skill', $result['frontmatter']['description']);
        $this->assertSame(['ToolA', 'ToolB'], $result['frontmatter']['tools']);
        $this->assertStringContainsString('This is the body', $result['body']);
    }

    public function testParseWithoutFrontmatter(): void
    {
        $parser = new MarkdownParser();

        $markdown = "# Just content\n\nNo frontmatter here.";

        $result = $parser->parse($markdown);

        $this->assertEmpty($result['frontmatter']);
        $this->assertStringContainsString('No frontmatter here', $result['body']);
    }
}
