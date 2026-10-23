<?php
declare(strict_types=1);

/*
 * This file is part of madj2k\ai-core
 *
 * Copyright (C) 2026 Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Madj2k\AiCore\Indexing\Adapter;

use Madj2k\AiCore\DTO\DocumentMetadata;
use Madj2k\AiCore\Indexing\DTO\IndexableDocument;

/**
 * Extracts Markdown sections separated by level-two ATX headings.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class MarkdownAdapter implements AdapterInterface, MultiDocumentAdapterInterface
{
    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'aiassistant.text.markdown';
    }

    /**
     * @inheritDoc
     */
    public function getSupportedExtensions(): array
    {
        return ['md', 'markdown'];
    }

    /**
     * @inheritDoc
     */
    public function supports(string $path): bool
    {
        return in_array(strtolower((string)pathinfo($path, PATHINFO_EXTENSION)), $this->getSupportedExtensions(), true);
    }

    /**
     * @inheritDoc
     */
    public function extract(string $path, DocumentMetadata $metadata): ?IndexableDocument
    {
        $documents = $this->extractDocuments($path, $metadata);

        return $documents[0] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function extractDocuments(string $path, DocumentMetadata $metadata): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $sections = $this->splitSections($content);
        if ($sections === []) {
            return [];
        }

        $title = $this->extractDocumentTitle($content);
        $documents = [];
        foreach ($sections as $index => $section) {
            $sectionMetadata = clone $metadata;
            $sectionMetadata->setTitle($title !== '' ? $title : $metadata->getTitle());
            $sectionMetadata->setSourceGroupIdentifier($metadata->getSourceGroupIdentifier());
            $sectionMetadata->setSourceIdentifier($metadata->getSourceIdentifier() . '#section-' . ($index + 1));
            $documents[] = new IndexableDocument($section, $sectionMetadata);
        }

        return $documents;
    }

    /**
     * Splits Markdown at real level-two headings while preserving section text.
     *
     * @param string $content Markdown source.
     * @return array<int, string> Non-empty sections.
     */
    private function splitSections(string $content): array
    {
        $lines = preg_split('/\R/u', $content) ?: [];
        $sections = [];
        $current = [];
        $fence = '';
        $indentedCode = false;

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            $isFence = preg_match('/^(`{3,}|~{3,})/', $trimmed, $matches) === 1;
            if ($isFence) {
                $marker = $matches[1][0];
                if ($fence === '') {
                    $fence = $marker;
                } elseif ($marker === $fence) {
                    $fence = '';
                }
            }

            $isIndentedCode = str_starts_with($line, '    ') || str_starts_with($line, "\t");
            if ($fence === '' && !$isIndentedCode && preg_match('/^##(?!#)(?:[ \t]+|$)/', $trimmed) === 1) {
                if (trim(implode("\n", $current)) !== '') {
                    $sections[] = implode("\n", $current);
                }

                $current = [$line];
                $indentedCode = false;
                continue;
            }

            $current[] = $line;
            $indentedCode = $isIndentedCode;
        }

        if (trim(implode("\n", $current)) !== '') {
            $sections[] = implode("\n", $current);
        }

        return $sections;
    }

    /**
     * Extracts the first real level-one ATX heading.
     *
     * @param string $content Markdown source.
     * @return string Document title.
     */
    private function extractDocumentTitle(string $content): string
    {
        $lines = preg_split('/\R/u', $content) ?: [];
        $fence = '';
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (preg_match('/^(`{3,}|~{3,})/', $trimmed, $matches) === 1) {
                $marker = $matches[1][0];
                $fence = $fence === '' ? $marker : ($fence === $marker ? '' : $fence);
                continue;
            }

            if ($fence === '' && preg_match('/^#(?!#)(?:[ \t]+|$)(.*?)[ \t]*#?[ \t]*$/', $trimmed, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        return '';
    }
}
