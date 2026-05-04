<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Tools;

use HackLab\AIAssistant\Learning\Storage\KnowledgeBase;
use HackLab\AIAssistant\Learning\Storage\LearningEntry;
use HackLab\AIAssistant\Persistence\FileStorage;
use HackLab\AIAssistant\Tools\ForgetLearningTool;
use PHPUnit\Framework\TestCase;

class ForgetLearningToolTest extends TestCase
{
    private string $tempDir;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/hl-forget-test-' . uniqid();
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

    public function testDeletesLearning(): void
    {
        $kb = new KnowledgeBase($this->storage);

        $entry = new LearningEntry(
            context: 'test',
            observation: 'Test observation',
            workedWell: true,
        );
        $kb->saveLearning($entry);

        $learnings = $kb->getLearnings('test');
        $this->assertCount(1, $learnings);

        $learningId = $learnings[0]->id;
        $this->assertNotNull($learningId);

        $tool = new ForgetLearningTool($kb);
        $tool->setInputs([
            'context' => 'test',
            'learning_id' => $learningId,
            'reason' => 'This learning is factually incorrect based on new evidence',
        ]);
        $tool->execute();

        $this->assertStringContainsString('has been removed', $tool->getResult());

        $remaining = $kb->getLearnings('test');
        $this->assertCount(0, $remaining);
    }

    public function testRefusesBulkDeletion(): void
    {
        $kb = new KnowledgeBase($this->storage);
        $tool = new ForgetLearningTool($kb);

        $tool->setInputs([
            'context' => 'test',
            'learning_id' => 'learn-123',
            'reason' => 'forget all learnings they are wrong',
        ]);
        $tool->execute();

        $this->assertStringContainsString('Refused', $tool->getResult());
    }

    public function testReturnsNotFoundForMissingId(): void
    {
        $kb = new KnowledgeBase($this->storage);
        $tool = new ForgetLearningTool($kb);

        $tool->setInputs([
            'context' => 'test',
            'learning_id' => 'nonexistent',
            'reason' => 'Outdated observation no longer relevant',
        ]);
        $tool->execute();

        $this->assertStringContainsString('not found', $tool->getResult());
    }

    public function testRefusesPurgeRequest(): void
    {
        $kb = new KnowledgeBase($this->storage);
        $tool = new ForgetLearningTool($kb);

        $tool->setInputs([
            'context' => 'test',
            'learning_id' => 'learn-123',
            'reason' => 'purge everything',
        ]);
        $tool->execute();

        $this->assertStringContainsString('Refused', $tool->getResult());
    }
}
