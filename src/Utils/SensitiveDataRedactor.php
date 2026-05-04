<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Utils;

class SensitiveDataRedactor
{
    private const array PATTERNS = [
        '/sk-[a-zA-Z0-9]{20,}/' => '[REDACTED_API_KEY]',
        '/sk-ant-[a-zA-Z0-9\-]{20,}/' => '[REDACTED_ANTHROPIC_KEY]',
        '/AIza[a-zA-Z0-9\-_]{30,}/' => '[REDACTED_GOOGLE_KEY]',
        '/(?:password|passwd|pwd)\s*[:=]\s*["\']?[^\s"\']{4,}/i' => '[REDACTED_PASSWORD]',
        '/(?:api[_-]?key|apikey)\s*[:=]\s*["\']?[^\s"\']{4,}/i' => '[REDACTED_API_KEY]',
        '/(?:secret|token|auth)\s*[:=]\s*["\']?[^\s"\']{8,}/i' => '[REDACTED_SECRET]',
        '/Bearer\s+[a-zA-Z0-9\-._~+\/]+=*/i' => 'Bearer [REDACTED_TOKEN]',
        '/["\'][a-f0-9]{32,}["\']/i' => '[REDACTED_HASH]',
        '/[\w.+-]+@[\w-]+\.[\w.-]+/' => '[REDACTED_EMAIL]',
        '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/' => '[REDACTED_PHONE]',
        '/\b\d{3}-\d{2}-\d{4}\b/' => '[REDACTED_SSN]',
        '/\b(?:\d[ -]?){13,19}\b/' => '[REDACTED_CC]',
    ];

    public function redact(string $text): string
    {
        foreach (self::PATTERNS as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    public function redactMessages(array $messages): array
    {
        return array_map(function (array $message): array {
            if (isset($message['content']) && is_string($message['content'])) {
                $message['content'] = $this->redact($message['content']);
            }
            return $message;
        }, $messages);
    }

    public static function redactString(string $text): string
    {
        return (new self())->redact($text);
    }
}
