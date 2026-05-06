<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools\FileReader;

final class PlainTextDocumentReader implements DocumentReaderInterface
{
    private const SUPPORTED_TYPES = ['txt', 'md', 'csv', 'html', 'json', 'xml', 'rtf'];

    public function supports(string $type): bool
    {
        return in_array($type, self::SUPPORTED_TYPES, true);
    }

    public function read(string $filePath): string
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        $type = FileTypeDetector::detect($filePath);

        if ($type === 'csv') {
            return $this->formatCsv($content);
        }

        return $content;
    }

    private function formatCsv(string $content): string
    {
        $rows = array_filter(explode("\n", $content));
        $formatted = [];

        foreach ($rows as $row) {
            $formatted[] = str_getcsv($row);
        }

        if ($formatted === []) {
            return $content;
        }

        $header = $formatted[0];
        $colCount = count($header);

        $lines = [implode(' | ', $header)];

        for ($i = 1; $i < count($formatted); $i++) {
            $row = $formatted[$i];
            if (count($row) === $colCount) {
                $lines[] = implode(' | ', $row);
            }
        }

        return implode("\n", $lines);
    }
}
