<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Tools;

use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use HackLab\AIAssistant\Persistence\FileStorage;
use HackLab\AIAssistant\Tools\RecordBugTool;
use PHPUnit\Framework\TestCase;

class RecordBugToolTest extends TestCase
{
    private string $tempDir;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/hl-bug-test-' . uniqid();
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

    public function testRecordsBug(): void
    {
        $kb = new KnowledgeBase($this->storage);
        $tool = new RecordBugTool($kb);

        $tool->setInputs([
            'context' => 'api_tool',
            'error_description' => 'Rate limit exceeded on third-party API',
        ]);
        $tool->execute();

        $this->assertStringContainsString('Recorded bug', $tool->getResult());
        $this->assertStringContainsString('api_tool', $tool->getResult());

        $bugs = $kb->searchBugs([]);
        $this->assertCount(1, $bugs);
        $this->assertSame('Rate limit exceeded on third-party API', $bugs[0]->errorMessage);
    }

    public function testRecordsBugWithWorkaround(): void
    {
        $kb = new KnowledgeBase($this->storage);
        $tool = new RecordBugTool($kb);

        $tool->setInputs([
            'context' => 'database',
            'error_description' => 'Deadlock on concurrent writes',
            'workaround' => 'Use retries with exponential backoff',
        ]);
        $tool->execute();

        $this->assertStringContainsString('Workaround documented', $tool->getResult());

        $bugs = $kb->searchBugs([]);
        $this->assertTrue($bugs[0]->resolved);
        $this->assertSame('Use retries with exponential backoff', $bugs[0]->resolution);
    }
}
