<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools\FileReader;

interface DocumentReaderInterface
{
    public function supports(string $type): bool;

    public function read(string $filePath): string;
}
