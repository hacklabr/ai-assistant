<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Utils;

final class ConfigStorage
{
    private const int KEY_BYTES = 32;
    private const int NONCE_BYTES = 24;

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
        if (!is_array($data)) {
            return [];
        }

        if (isset($data['_encrypted']) && $data['_encrypted'] === true) {
            return $this->decrypt($data);
        }

        return $data;
    }

    public function save(array $config): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $encryptionKey = $this->getEncryptionKey();

        if ($encryptionKey !== null) {
            $config = $this->encrypt($config, $encryptionKey);
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

    public function isEncryptionAvailable(): bool
    {
        return $this->getEncryptionKey() !== null;
    }

    private function getEncryptionKey(): ?string
    {
        $envKey = $_ENV['HL_AI_ENCRYPTION_KEY'] ?? $_SERVER['HL_AI_ENCRYPTION_KEY'] ?? null;

        if ($envKey === null || $envKey === '') {
            return null;
        }

        $derived = sodium_crypto_generichash('hacklab-ai-assistant-config', $envKey, self::KEY_BYTES);

        return $derived;
    }

    private function encrypt(array $config, string $key): array
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $plaintext = json_encode($config, JSON_UNESCAPED_SLASHES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return [
            '_encrypted' => true,
            '_nonce' => sodium_bin2base64($nonce, SODIUM_BASE64_VARIANT_ORIGINAL),
            '_data' => sodium_bin2base64($ciphertext, SODIUM_BASE64_VARIANT_ORIGINAL),
        ];
    }

    private function decrypt(array $data): array
    {
        $key = $this->getEncryptionKey();

        if ($key === null) {
            throw new \RuntimeException(
                'Config file is encrypted but HL_AI_ENCRYPTION_KEY environment variable is not set. '
                . 'Set the variable or delete ' . $this->path . ' to reconfigure.'
            );
        }

        if (!isset($data['_nonce'], $data['_data'])) {
            throw new \RuntimeException('Encrypted config file is malformed.');
        }

        $nonce = sodium_base642bin($data['_nonce'], SODIUM_BASE64_VARIANT_ORIGINAL);
        $ciphertext = sodium_base642bin($data['_data'], SODIUM_BASE64_VARIANT_ORIGINAL);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($plaintext === false) {
            throw new \RuntimeException(
                'Failed to decrypt config file. Verify HL_AI_ENCRYPTION_KEY is correct.'
            );
        }

        $decrypted = json_decode($plaintext, true);
        return is_array($decrypted) ? $decrypted : [];
    }
}
