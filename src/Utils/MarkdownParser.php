<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Utils;

/**
 * Lightweight Markdown parser for extracting content.
 */
class MarkdownParser
{
    /**
     * Extract YAML frontmatter from markdown content.
     *
     * @return array{frontmatter: array<string, mixed>, body: string}
     */
    public function parse(string $content): array
    {
        $frontmatter = [];
        $body = $content;

        // Check for YAML frontmatter (--- at start)
        if (str_starts_with(trim($content), '---')) {
            $parts = preg_split('/\n---\s*\n/', $content, 2);
            if ($parts !== false && count($parts) >= 2) {
                $yamlContent = substr($parts[0], 3); // Remove leading ---
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
     * Simple YAML parser for frontmatter.
     *
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

            // List item
            if (str_starts_with($trimmed, '- ')) {
                $item = trim(substr($trimmed, 2));
                // Remove quotes if present
                $item = $this->unquote($item);
                $currentList[] = $item;
                $inList = true;
                continue;
            }

            // If we were in a list and now hit a key, save the list
            if ($inList && $currentKey !== null && str_contains($trimmed, ':')) {
                $result[$currentKey] = $currentList;
                $currentList = [];
                $inList = false;
                $currentKey = null;
            }

            // Key-value pair
            if (str_contains($trimmed, ':')) {
                $colonPos = strpos($trimmed, ':');
                $key = trim(substr($trimmed, 0, $colonPos));
                $value = trim(substr($trimmed, $colonPos + 1));

                if ($value === '') {
                    // Might be a list starting next line
                    $currentKey = $key;
                    $currentList = [];
                } else {
                    $result[$key] = $this->parseValue($value);
                }
            }
        }

        // Save any remaining list
        if ($inList && $currentKey !== null) {
            $result[$currentKey] = $currentList;
        }

        return $result;
    }

    private function parseValue(string $value): mixed
    {
        $value = $this->unquote($value);

        // Boolean
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        // Null
        if ($value === 'null' || $value === '~') {
            return null;
        }

        // Integer
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        // Float
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
