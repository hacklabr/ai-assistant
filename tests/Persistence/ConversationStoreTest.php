<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Persistence;

use HackLab\AIAssistant\Persistence\ConversationStore;
use HackLab\AIAssistant\Persistence\FileStorage;
use PHPUnit\Framework\TestCase;

class ConversationStoreTest extends TestCase
{
    private string $tempDir;
    private FileStorage $storage;
    private ConversationStore $store;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/hl-conv-test-' . uniqid();
        $this->storage = new FileStorage($this->tempDir);
        $this->store = new ConversationStore($this->storage);
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

    public function testSaveAndLoadThread(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];

        $this->store->saveThread('thread-1', $messages);

        $loaded = $this->store->loadThread('thread-1');

        $this->assertCount(2, $loaded);
        $this->assertSame('Hello', $loaded[0]['content']);
        $this->assertSame('Hi there!', $loaded[1]['content']);
    }

    public function testLoadNonExistentThreadReturnsEmpty(): void
    {
        $loaded = $this->store->loadThread('nonexistent');
        $this->assertSame([], $loaded);
    }

    public function testAppendToThread(): void
    {
        $this->store->saveThread('thread-1', [
            ['role' => 'user', 'content' => 'First'],
        ]);

        $this->store->appendToThread('thread-1', [
            ['role' => 'assistant', 'content' => 'Second'],
        ]);

        $loaded = $this->store->loadThread('thread-1');
        $this->assertCount(2, $loaded);
        $this->assertSame('First', $loaded[0]['content']);
        $this->assertSame('Second', $loaded[1]['content']);
    }

    public function testListThreads(): void
    {
        $this->store->saveThread('thread-a', [['role' => 'user', 'content' => 'A']]);
        $this->store->saveThread('thread-b', [['role' => 'user', 'content' => 'B']]);

        $threads = $this->store->listThreads();

        $this->assertCount(2, $threads);
        $this->assertContains('thread-a', $threads);
        $this->assertContains('thread-b', $threads);
    }

    public function testDeleteThread(): void
    {
        $this->store->saveThread('thread-1', [['role' => 'user', 'content' => 'Test']]);

        $deleted = $this->store->deleteThread('thread-1');
        $this->assertTrue($deleted);

        $loaded = $this->store->loadThread('thread-1');
        $this->assertSame([], $loaded);
    }

    public function testDeleteNonExistentThreadReturnsFalse(): void
    {
        $deleted = $this->store->deleteThread('nonexistent');
        $this->assertFalse($deleted);
    }
}
