<?php
declare(strict_types=1);

/*
 * This file is part of madj2k\ai-core
 *
 * Copyright (C) 2026 Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
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
 * Class PlainAdapter
 *
 * Extracts text from plain text files.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class PlainAdapter implements AdapterInterface
{
    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'aiassistant.text.plain';
    }


    /**
     * @inheritDoc
     */
    public function getSupportedExtensions(): array
    {
        return ['txt', 'text', 'csv'];
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
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        return new IndexableDocument(trim($content), $metadata);
    }
}
