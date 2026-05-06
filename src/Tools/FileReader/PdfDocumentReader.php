<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools\FileReader;

use Smalot\PdfParser\Parser;

final class PdfDocumentReader implements DocumentReaderInterface
{
    public function supports(string $type): bool
    {
        return $type === 'pdf';
    }

    public function read(string $filePath): string
    {
        $parser = new Parser();
        $document = $parser->parseFile($filePath);

        $text = $document->getText();

        if (trim($text) === '') {
            return $this->extractFromPages($document);
        }

        return $text;
    }

    private function extractFromPages(\Smalot\PdfParser\Document $document): string
    {
        $pages = $document->getPages();
        $texts = [];

        foreach ($pages as $i => $page) {
            $pageText = $page->getText();
            if (trim($pageText) !== '') {
                $texts[] = $pageText;
            }
        }

        if ($texts === []) {
            return '[PDF contains no extractable text. It may be a scanned document or contain only images.]';
        }

        return implode("\n\n", $texts);
    }
}
