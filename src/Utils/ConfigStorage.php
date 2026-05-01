<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Utils;

final class ConfigStorage
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? ($_ENV['HOME'] ?? $_SERVER['HOME'] ?? getcwd()) . '/.hacklab-ai-assistant.json';
    }

    public function load(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        $content = file_get_contents($this->path);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    public function save(array $config): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        chmod($this->path, 0600);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->load()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $config = $this->load();
        $config[$key] = $value;
        $this->save($config);
    }

    public function exists(): bool
    {
        return file_exists($this->path);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
