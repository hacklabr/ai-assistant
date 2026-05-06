<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools\FileReader;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class FileReaderTool extends Tool
{
    private int $maxFileSizeBytes;

    /** @var array<string, DocumentReaderInterface> */
    private array $readers = [];

    /**
     * @param int $maxFileSizeBytes Maximum file size in bytes (default: 50MB)
     * @param array<DocumentReaderInterface>|null $readers Custom readers (null = defaults)
     */
    public function __construct(
        int $maxFileSizeBytes = 52428800,
        ?array $readers = null,
    ) {
        parent::__construct(
            name: 'read_file',
            description: 'Read and extract text content from documents. Supports PDF, DOCX, TXT, CSV, Markdown, HTML, JSON, XML, and RTF files. Returns the extracted text content for analysis.',
        );

        $this->maxFileSizeBytes = $maxFileSizeBytes;
        $this->readers = $readers ?? $this->defaultReaders();
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'file_path',
                type: PropertyType::STRING,
                description: 'Absolute path to the file to read.',
                required: true,
            ),
            new ToolProperty(
                name: 'max_length',
                type: PropertyType::INTEGER,
                description: 'Maximum number of characters to return. Default: 100000. Use to limit output for large files.',
                required: false,
            ),
        ];
    }

    public function __invoke(
        string $file_path,
        ?int $max_length = null,
    ): string {
        $maxLength = $max_length ?? 100000;

        $realPath = realpath($file_path);

        if ($realPath === false || !file_exists($realPath)) {
            return json_encode([
                'success' => false,
                'error' => "File not found: {$file_path}",
            ]);
        }

        $fileSize = filesize($realPath);

        if ($fileSize > $this->maxFileSizeBytes) {
            $maxMB = round($this->maxFileSizeBytes / 1048576, 1);
            return json_encode([
                'success' => false,
                'error' => "File too large: {$fileSize} bytes exceeds {$maxMB}MB limit.",
            ]);
        }

        try {
            $type = FileTypeDetector::detect($realPath);
        } catch (\InvalidArgumentException $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }

        $reader = $this->findReader($type);

        if ($reader === null) {
            return json_encode([
                'success' => false,
                'error' => "No reader available for file type: {$type}",
                'supported_types' => FileTypeDetector::supportedTypes(),
            ]);
        }

        try {
            $content = $reader->read($realPath);
        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error' => "Failed to read file: {$e->getMessage()}",
                'type' => $type,
            ]);
        }

        $truncated = false;

        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength);
            $truncated = true;
        }

        return json_encode([
            'success' => true,
            'file' => basename($realPath),
            'type' => $type,
            'size_bytes' => $fileSize,
            'content' => $content,
            'truncated' => $truncated,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function findReader(string $type): ?DocumentReaderInterface
    {
        foreach ($this->readers as $reader) {
            if ($reader->supports($type)) {
                return $reader;
            }
        }

        return null;
    }

    /**
     * @return array<DocumentReaderInterface>
     */
    private function defaultReaders(): array
    {
        return [
            new PdfDocumentReader(),
            new DocxDocumentReader(),
            new PlainTextDocumentReader(),
        ];
    }
}
