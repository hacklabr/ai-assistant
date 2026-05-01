<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Tools;

use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use HackLab\AIAssistant\Learning\Storage\LearningEntry;
use HackLab\AIAssistant\Tools\FindSimilarIssuesTool;
use PHPUnit\Framework\TestCase;

class FindSimilarIssuesToolTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/hl-similar-test-' . uniqid();
        mkdir($this->tempDir . '/learnings', 0755, true);
        mkdir($this->tempDir . '/bugs', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveDelete($this->tempDir);
    }

    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                (is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : unlink("$dir/$file");
            }
            rmdir($dir);
        }
    }

    public function testFindsAntiPattern(): void
    {
        $kb = new KnowledgeBase($this->tempDir);

        $kb->saveLearning(new LearningEntry(
            context: 'database',
            observation: 'Using raw SQL without prepared statements causes injection',
            workedWell: false,
        ));

        $tool = new FindSimilarIssuesTool($kb);
        $tool->setInputs([
            'context' => 'database',
            'problem_description' => 'SQL injection risk',
        ]);
        $tool->execute();

        $this->assertStringContainsString('anti-pattern', $tool->getResult());
        $this->assertStringContainsString('prepared statements', $tool->getResult());
    }

    public function testReturnsEmptyWhenNoIssues(): void
    {
        $kb = new KnowledgeBase($this->tempDir);
        $tool = new FindSimilarIssuesTool($kb);

        $tool->setInputs([
            'context' => 'empty',
            'problem_description' => 'nothing',
        ]);
        $tool->execute();

        $this->assertStringContainsString('No similar issues found', $tool->getResult());
    }
}
