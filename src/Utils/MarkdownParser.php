<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Utils;

class MarkdownParser
{
    /**
     * @return array{frontmatter: array<string, mixed>, body: string}
     */
    public function parse(string $content): array
    {
        $frontmatter = [];
        $body = $content;

        if (str_starts_with(trim($content), '---')) {
            $parts = preg_split('/\n---\s*\n/', $content, 2);
            if ($parts !== false && count($parts) >= 2) {
                $yamlContent = substr($parts[0], 3);
                $frontmatter = $this->parseYaml($yamlContent);
                $body = $parts[1];
            }
        }

        return [
            'frontmatter' => $frontmatter,
            'body' => trim($body),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYaml(string $yaml): array
    {
        $result = [];
        $lines = explode("\n", $yaml);
        $currentKey = null;
        $currentList = [];
        $inList = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (str_starts_with($trimmed, '- ')) {
                $item = trim(substr($trimmed, 2));
                $item = $this->unquote($item);
                $currentList[] = $item;
                $inList = true;
                continue;
            }

            if ($inList && $currentKey !== null && str_contains($trimmed, ':')) {
                $result[$currentKey] = $currentList;
                $currentList = [];
                $inList = false;
                $currentKey = null;
            }

            if (str_contains($trimmed, ':')) {
                $colonPos = strpos($trimmed, ':');
                $key = trim(substr($trimmed, 0, $colonPos));
                $value = trim(substr($trimmed, $colonPos + 1));

                if ($value === '') {
                    $currentKey = $key;
                    $currentList = [];
                } else {
                    $result[$key] = $this->parseValue($value);
                }
            }
        }

        if ($inList && $currentKey !== null) {
            $result[$currentKey] = $currentList;
        }

        return $result;
    }

    private function parseValue(string $value): mixed
    {
        $value = $this->unquote($value);

        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        if ($value === 'null' || $value === '~') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }

        return $value;
    }

    private function unquote(string $value): string
    {
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }
        return $value;
    }
}
