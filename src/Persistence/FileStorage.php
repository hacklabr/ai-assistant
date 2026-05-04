<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Persistence;

class FileStorage implements StorageInterface
{
    public function __construct(
        private readonly string $basePath,
    ) {
        $this->ensureDirectoryExists($basePath);
    }

    public function save(string $namespace, string $key, array $data): void
    {
        $path = $this->getPath($namespace, $key);
        $this->ensureDirectoryExists(dirname($path));

        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        file_put_contents($path, $content, LOCK_EX);
    }

    public function load(string $namespace, string $key): ?array
    {
        $path = $this->getPath($namespace, $key);

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    public function delete(string $namespace, string $key): bool
    {
        $path = $this->getPath($namespace, $key);
        if (file_exists($path)) {
            unlink($path);
            return true;
        }
        return false;
    }

    public function exists(string $namespace, string $key): bool
    {
        return file_exists($this->getPath($namespace, $key));
    }

    public function list(string $namespace, string $pattern = '*'): array
    {
        $dir = $this->getNamespacePath($namespace);

        if (!is_dir($dir)) {
            return [];
        }

        $safePattern = preg_replace('/[^a-zA-Z0-9_*\-]/', '_', $pattern);
        $globPattern = $dir . DIRECTORY_SEPARATOR . $safePattern . '.json';

        $files = glob($globPattern);
        if ($files === false) {
            return [];
        }

        return array_map(fn(string $file) => basename($file, '.json'), $files);
    }

    public function search(string $namespace, string $query, int $limit = 10): array
    {
        $dir = $this->getNamespacePath($namespace);

        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');
        if ($files === false) {
            return [];
        }

        $results = [];
        $queryLower = strtolower($query);
        $queryWords = str_word_count($queryLower, 1);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            try {
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (!is_array($data)) {
                continue;
            }

            $textLower = strtolower(json_encode($data));
            $score = 0.0;

            if (str_contains($textLower, $queryLower)) {
                $score = 1.0;
            } elseif (!empty($queryWords)) {
                $textWords = str_word_count($textLower, 1);
                $matches = count(array_intersect($queryWords, $textWords));
                $score = $matches / count($queryWords);
            }

            if ($score > 0.1) {
                $results[] = ['data' => $data, 'score' => $score];
            }
        }

        usort($results, fn(array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    public function cleanup(string $namespace, array $criteria = []): int
    {
        $removed = 0;
        $dir = $this->getNamespacePath($namespace);

        if (!is_dir($dir)) {
            return 0;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');
        if ($files === false) {
            return 0;
        }

        if (isset($criteria['max_age_days'])) {
            $threshold = time() - ($criteria['max_age_days'] * 86400);

            foreach ($files as $file) {
                if (filemtime($file) < $threshold) {
                    unlink($file);
                    $removed++;
                }
            }

            $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');
            if ($files === false) {
                return $removed;
            }
        }

        if (isset($criteria['max_per_namespace']) && count($files) > $criteria['max_per_namespace']) {
            usort($files, fn(string $a, string $b) => filemtime($b) <=> filemtime($a));

            $toRemove = array_slice($files, $criteria['max_per_namespace']);
            foreach ($toRemove as $file) {
                unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    private function getPath(string $namespace, string $key): string
    {
        $safeNamespace = preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $namespace);
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);

        return $this->basePath . DIRECTORY_SEPARATOR . $safeNamespace . DIRECTORY_SEPARATOR . $safeKey . '.json';
    }

    private function getNamespacePath(string $namespace): string
    {
        $safeNamespace = preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $namespace);
        return $this->basePath . DIRECTORY_SEPARATOR . $safeNamespace;
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0750, true);
        }
    }
}
