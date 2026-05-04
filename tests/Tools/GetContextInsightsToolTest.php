<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Tools;

use HackLab\AIAssistant\Learning\Storage\BugReport;
use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use HackLab\AIAssistant\Learning\Storage\LearningEntry;
use HackLab\AIAssistant\Persistence\FileStorage;
use HackLab\AIAssistant\Tools\GetContextInsightsTool;
use PHPUnit\Framework\TestCase;

class GetContextInsightsToolTest extends TestCase
{
    private string $tempDir;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/hl-insights-test-' . uniqid();
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

    public function testReturnsLearningsAndBugs(): void
    {
        $kb = new KnowledgeBase($this->storage);

        $kb->saveLearning(new LearningEntry(
            context: 'php',
            observation: 'Use prepared statements',
            workedWell: true,
        ));

        $kb->saveLearning(new LearningEntry(
            context: 'php',
            observation: 'Never use eval() on user input',
            workedWell: false,
        ));

        $kb->saveBug(new BugReport(
            id: 'bug-1',
            errorType: 'php',
            errorMessage: 'Memory leak in long-running scripts',
            stackTrace: '',
            context: ['context' => 'php'],
            timestamp: new \DateTimeImmutable(),
        ));

        $tool = new GetContextInsightsTool($kb);
        $tool->setInputs(['context' => 'php', 'include_related' => false]);
        $tool->execute();

        $result = $tool->getResult();
        $this->assertStringContainsString('Use prepared statements', $result);
        $this->assertStringContainsString('Never use eval()', $result);
        $this->assertStringContainsString('Memory leak', $result);
    }

    public function testReturnsNoInsightsWhenEmpty(): void
    {
        $kb = new KnowledgeBase($this->storage);
        $tool = new GetContextInsightsTool($kb);

        $tool->setInputs(['context' => 'unknown']);
        $tool->execute();

        $this->assertStringContainsString('No recorded insights', $tool->getResult());
    }
}
