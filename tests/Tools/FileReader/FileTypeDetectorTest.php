<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tests\Tools\FileReader;

use HackLab\AIAssistant\Tools\FileReader\FileTypeDetector;
use PHPUnit\Framework\TestCase;

class FileTypeDetectorTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/fixtures';
    }

    public function testDetectsTxt(): void
    {
        $this->assertSame('txt', FileTypeDetector::detect($this->fixturesDir . '/sample.txt'));
    }

    public function testDetectsCsv(): void
    {
        $this->assertSame('csv', FileTypeDetector::detect($this->fixturesDir . '/sample.csv'));
    }

    public function testDetectsMarkdown(): void
    {
        $this->assertSame('md', FileTypeDetector::detect($this->fixturesDir . '/sample.md'));
    }

    public function testDetectsPdf(): void
    {
        $this->assertSame('pdf', FileTypeDetector::detect($this->fixturesDir . '/sample.pdf'));
    }

    public function testDetectsDocx(): void
    {
        $this->assertSame('docx', FileTypeDetector::detect($this->fixturesDir . '/sample.docx'));
    }

    public function testThrowsForMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FileTypeDetector::detect('/nonexistent/file.txt');
    }

    public function testIsSupportedReturnsTrueForKnownTypes(): void
    {
        $this->assertTrue(FileTypeDetector::isSupported($this->fixturesDir . '/sample.txt'));
    }

    public function testIsSupportedReturnsFalseForMissingFile(): void
    {
        $this->assertFalse(FileTypeDetector::isSupported('/nonexistent/file.txt'));
    }

    public function testSupportedTypesIncludesCoreTypes(): void
    {
        $types = FileTypeDetector::supportedTypes();
        $this->assertContains('pdf', $types);
        $this->assertContains('docx', $types);
        $this->assertContains('txt', $types);
        $this->assertContains('csv', $types);
    }
}
