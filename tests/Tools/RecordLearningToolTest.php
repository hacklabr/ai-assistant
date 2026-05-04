<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Tools;

use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use HackLab\AIAssistant\Learning\Storage\LearningEntry;
use HackLab\AIAssistant\Persistence\FileStorage;
use HackLab\AIAssistant\Tools\RecordLearningTool;
use PHPUnit\Framework\TestCase;

class RecordLearningToolTest extends TestCase
{
    private string $tempDir;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/hl-learning-test-' . uniqid();
        $this->storage = new FileStorage($this->tempDir);
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

    public function testRecordsSuccessfulPattern(): void
    {
        $kb = new KnowledgeBase($this->storage);
        $tool = new RecordLearningTool($kb);

        $tool->setInputs([
            'context' => 'filesystem_tool',
            'observation' => 'Always check if parent directory exists before creating',
            'worked_well' => true,
            'tags' => 'filesystem,php',
        ]);
        $tool->execute();

        $learnings = $kb->getLearnings('filesystem_tool');
        $this->assertCount(1, $learnings);
        $this->assertSame('Always check if parent directory exists before creating', $learnings[0]->observation);
        $this->assertTrue($learnings[0]->workedWell);
        $this->assertSame(['filesystem', 'php'], $learnings[0]->tags);
    }

    public function testRecordsAntiPattern(): void
    {
        $kb = new KnowledgeBase($this->storage);
        $tool = new RecordLearningTool($kb);

        $tool->setInputs([
            'context' => 'database_tool',
            'observation' => 'Using raw SQL without parameterization causes injection vulnerabilities',
            'worked_well' => false,
        ]);
        $tool->execute();

        $learnings = $kb->getLearnings('database_tool');
        $this->assertCount(1, $learnings);
        $this->assertFalse($learnings[0]->workedWell);
    }

    public function testReturnsConfirmation(): void
    {
        $kb = new KnowledgeBase($this->storage);
        $tool = new RecordLearningTool($kb);

        $tool->setInputs([
            'context' => 'test',
            'observation' => 'Test observation',
            'worked_well' => true,
        ]);
        $tool->execute();

        $this->assertStringContainsString('Recorded success pattern', $tool->getResult());
        $this->assertStringContainsString('test', $tool->getResult());
    }
}
