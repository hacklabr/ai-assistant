<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Tools\FileReader;

use HackLab\AIAssistant\Tools\FileReader\FileReaderTool;
use PHPUnit\Framework\TestCase;

class FileReaderToolTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = realpath(__DIR__ . '/fixtures');
    }

    public function testReadsTxtFile(): void
    {
        $tool = new FileReaderTool();
        $tool->setInputs(['file_path' => $this->fixturesDir . '/sample.txt']);
        $tool->execute();

        $result = json_decode($tool->getResult(), true);
        $this->assertTrue($result['success']);
        $this->assertSame('txt', $result['type']);
        $this->assertStringContainsString('Hello World!', $result['content']);
    }

    public function testReadsCsvFile(): void
    {
        $tool = new FileReaderTool();
        $tool->setInputs(['file_path' => $this->fixturesDir . '/sample.csv']);
        $tool->execute();

        $result = json_decode($tool->getResult(), true);
        $this->assertTrue($result['success']);
        $this->assertSame('csv', $result['type']);
        $this->assertStringContainsString('Alice', $result['content']);
        $this->assertStringContainsString('|', $result['content']);
    }

    public function testReadsMarkdownFile(): void
    {
        $tool = new FileReaderTool();
        $tool->setInputs(['file_path' => $this->fixturesDir . '/sample.md']);
        $tool->execute();

        $result = json_decode($tool->getResult(), true);
        $this->assertTrue($result['success']);
        $this->assertSame('md', $result['type']);
        $this->assertStringContainsString('Test Markdown', $result['content']);
    }

    public function testReadsPdfFile(): void
    {
        $tool = new FileReaderTool();
        $tool->setInputs(['file_path' => $this->fixturesDir . '/sample.pdf']);
        $tool->execute();

        $result = json_decode($tool->getResult(), true);
        $this->assertTrue($result['success']);
        $this->assertSame('pdf', $result['type']);
        $this->assertStringContainsString('Test PDF content', $result['content']);
    }

    public function testReadsDocxFile(): void
    {
        $tool = new FileReaderTool();
        $tool->setInputs(['file_path' => $this->fixturesDir . '/sample.docx']);
        $tool->execute();

        $result = json_decode($tool->getResult(), true);
        $this->assertTrue($result['success']);
        $this->assertSame('docx', $result['type']);
        $this->assertStringContainsString('Hello from DOCX!', $result['content']);
    }

    public function testReturnsErrorForMissingFile(): void
    {
        $tool = new FileReaderTool();
        $tool->setInputs(['file_path' => '/nonexistent/file.txt']);
        $tool->execute();

        $result = json_decode($tool->getResult(), true);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testRespectsMaxLength(): void
    {
        $tool = new FileReaderTool();
        $tool->setInputs([
            'file_path' => $this->fixturesDir . '/sample.txt',
            'max_length' => 5,
        ]);
        $tool->execute();

        $result = json_decode($tool->getResult(), true);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['truncated']);
        $this->assertLessThanOrEqual(5, strlen($result['content']));
    }

    public function testReportsFileSize(): void
    {
        $tool = new FileReaderTool();
        $tool->setInputs(['file_path' => $this->fixturesDir . '/sample.txt']);
        $tool->execute();

        $result = json_decode($tool->getResult(), true);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('size_bytes', $result);
        $this->assertGreaterThan(0, $result['size_bytes']);
    }

    public function testRejectsOversizedFile(): void
    {
        $tool = new FileReaderTool(maxFileSizeBytes: 1);
        $tool->setInputs(['file_path' => $this->fixturesDir . '/sample.txt']);
        $tool->execute();

        $result = json_decode($tool->getResult(), true);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('too large', $result['error']);
    }

    public function testToolName(): void
    {
        $tool = new FileReaderTool();
        $this->assertSame('read_file', $tool->getName());
    }
}
