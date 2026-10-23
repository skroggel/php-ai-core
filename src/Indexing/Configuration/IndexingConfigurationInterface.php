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

namespace Madj2k\AiCore\Indexing\Configuration;

use Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface;
use Madj2k\AiCore\Indexing\Enum\ChunkingStrategy;

/**
 * Interface IndexingConfigurationInterface
 *
 * Defines connector and chunking settings required to index documents.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
interface IndexingConfigurationInterface
{
    /**
     * Returns the configured vector collection name.
     *
     * @return string
     */
    public function getCollection(): string;


    /**
     * Returns the AI connection used to create embeddings.
     *
     * @return \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface|null
     */
    public function getAiConnection(): ?AiConnectionConfigurationInterface;


    /**
     * Returns the vector store connection used to persist documents
     *
     * @return \Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface|null
     */
    public function getVectorStoreConnection(): ?VectorStoreConnectionConfigurationInterface;


    /**
     * Returns the target chunk length in characters.
     *
     * @return int
     */
    public function getChunkSize(): int;

    /**
     * Returns the configured chunking strategy.
     *
     * @return \Madj2k\AiCore\Indexing\Enum\ChunkingStrategy Chunking strategy.
     */
    public function getChunkingStrategy(): ChunkingStrategy;


    /**
     * Returns the chunk overlap in characters.
     *
     * @return int
     */
    public function getChunkOverlap(): int;


    /**
     * Returns the maximum number of chunks per document.
     *
     * @return int
     */
    public function getMaxChunks(): int;


    /**
     * Returns the minimum retained chunk length in characters.
     *
     * @return int
     */
    public function getMinChunkChars(): int;
}
