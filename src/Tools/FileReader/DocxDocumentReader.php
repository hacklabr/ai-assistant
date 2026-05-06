<?php

declare(strict_types=1);

namespace HackLab\AIAssistant\Tools\FileReader;

use PhpOffice\PhpWord\IOFactory;

final class DocxDocumentReader implements DocumentReaderInterface
{
    public function supports(string $type): bool
    {
        return $type === 'docx';
    }

    public function read(string $filePath): string
    {
        $phpWord = IOFactory::createReader('Word2007')->load($filePath);

        $sections = [];

        foreach ($phpWord->getSections() as $section) {
            $sectionText = $this->extractSectionText($section);
            if (trim($sectionText) !== '') {
                $sections[] = $sectionText;
            }
        }

        $text = implode("\n\n", $sections);

        if (trim($text) === '') {
            return '[DOCX file contains no extractable text.]';
        }

        return $text;
    }

    private function extractSectionText(\PhpOffice\PhpWord\Element\Section $section): string
    {
        $elements = [];

        foreach ($section->getElements() as $element) {
            if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                $elements[] = $this->extractTextRun($element);
            } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                $elements[] = $element->getText();
            } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                $elements[] = $this->extractTable($element);
            } elseif ($element instanceof \PhpOffice\PhpWord\Element\ListItem) {
                $elements[] = '- ' . $element->getText();
            } elseif (method_exists($element, 'getText')) {
                $elements[] = $element->getText();
            }
        }

        return implode("\n", $elements);
    }

    private function extractTextRun(\PhpOffice\PhpWord\Element\TextRun $textRun): string
    {
        $parts = [];

        foreach ($textRun->getElements() as $element) {
            if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                $parts[] = $element->getText();
            } elseif ($element instanceof \PhpOffice\PhpWord\Element\Link) {
                $parts[] = $element->getText();
            }
        }

        return implode('', $parts);
    }

    private function extractTable(\PhpOffice\PhpWord\Element\Table $table): string
    {
        $rows = [];

        foreach ($table->getRows() as $row) {
            $cells = [];
            foreach ($row->getCells() as $cell) {
                $cellText = [];
                foreach ($cell->getElements() as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        $cellText[] = $this->extractTextRun($element);
                    } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                        $cellText[] = $element->getText();
                    }
                }
                $cells[] = implode(' ', $cellText);
            }
            $rows[] = implode(' | ', $cells);
        }

        return implode("\n", $rows);
    }
}
