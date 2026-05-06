<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools\FileReader;

final class FileTypeDetector
{
    private const MIME_MAP = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/msword' => 'doc',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'text/markdown' => 'md',
        'text/html' => 'html',
        'application/json' => 'json',
        'application/xml' => 'xml',
        'text/xml' => 'xml',
        'text/rtf' => 'rtf',
        'application/rtf' => 'rtf',
    ];

    private const EXTENSION_MAP = [
        'pdf' => 'pdf',
        'docx' => 'docx',
        'doc' => 'doc',
        'txt' => 'txt',
        'csv' => 'csv',
        'md' => 'md',
        'html' => 'html',
        'htm' => 'html',
        'json' => 'json',
        'xml' => 'xml',
        'rtf' => 'rtf',
    ];

    public static function detect(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException("File not readable: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (isset(self::EXTENSION_MAP[$extension])) {
            return self::EXTENSION_MAP[$extension];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        if (isset(self::MIME_MAP[$mimeType])) {
            return self::MIME_MAP[$mimeType];
        }

        throw new \InvalidArgumentException("Unsupported file type: {$mimeType} ({$filePath})");
    }

    public static function isSupported(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (isset(self::EXTENSION_MAP[$extension])) {
            return true;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        return isset(self::MIME_MAP[$mimeType]);
    }

    public static function supportedTypes(): array
    {
        return array_values(array_unique(self::EXTENSION_MAP));
    }
}
