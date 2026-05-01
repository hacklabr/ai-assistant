<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Utils;

use HackLab\AIAssistant\Utils\ConfigStorage;
use PHPUnit\Framework\TestCase;

class ConfigStorageTest extends TestCase
{
    private string $tempPath;

    protected function setUp(): void
    {
        $this->tempPath = sys_get_temp_dir() . '/hl-ai-test-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempPath)) {
            unlink($this->tempPath);
        }
    }

    public function testLoadEmptyWhenFileDoesNotExist(): void
    {
        $storage = new ConfigStorage($this->tempPath);

        $this->assertSame([], $storage->load());
    }

    public function testSaveAndLoad(): void
    {
        $storage = new ConfigStorage($this->tempPath);

        $storage->save(['provider' => 'anthropic', 'model' => 'claude-sonnet-4']);

        $this->assertTrue($storage->exists());
        $this->assertSame(['provider' => 'anthropic', 'model' => 'claude-sonnet-4'], $storage->load());
    }

    public function testGetAndSet(): void
    {
        $storage = new ConfigStorage($this->tempPath);

        $this->assertNull($storage->get('provider'));

        $storage->set('provider', 'openai');
        $this->assertSame('openai', $storage->get('provider'));
        $this->assertSame('gpt-4o', $storage->get('model', 'gpt-4o'));
    }

    public function testGetPath(): void
    {
        $storage = new ConfigStorage($this->tempPath);

        $this->assertSame($this->tempPath, $storage->getPath());
    }

    public function testFilePermissions(): void
    {
        $storage = new ConfigStorage($this->tempPath);
        $storage->save(['key' => 'secret']);

        $perms = fileperms($this->tempPath) & 0777;
        $this->assertSame(0600, $perms);
    }
}
