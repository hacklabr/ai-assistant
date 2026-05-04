<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Logging;

use Psr\Log\AbstractLogger;

class StderrLogger extends AbstractLogger
{
    private string $prefix;

    public function __construct(string $prefix = 'ai-assistant')
    {
        $this->prefix = $prefix;
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d\TH:i:s.vP');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        $line = "[{$timestamp}] [{$this->prefix}] [{$level}] {$message}{$contextStr}\n";

        if (defined('STDERR') && is_resource(STDERR)) {
            fwrite(STDERR, $line);
        } else {
            error_log(rtrim($line));
        }
    }
}
