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

namespace Madj2k\AiCore\Indexing;

use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest;
use Madj2k\AiCore\Connection\Ai\Enum\EmbeddingPurpose;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Connection\Resolver\VectorStoreConnectorResolver;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorCollection;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorDocument;
use Madj2k\AiCore\Exception\IndexingException;
use Madj2k\AiCore\Indexing\Configuration\IndexingConfigurationInterface;
use Madj2k\AiCore\Indexing\DTO\IndexableDocument;
use Madj2k\AiCore\Indexing\Identity\SourceIdentityGenerator;
use Madj2k\AiCore\Indexing\Enum\ChunkingStrategy;

/**
 * Class VectorDocumentIndexer
 *
 * Chunks source documents, creates embeddings and replaces their vectors in a vector store.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class VectorDocumentIndexer
{
    /**
     * @param \Madj2k\AiCore\Connection\Resolver\AiConnectorResolver $aiConnectorResolver AI connector resolver.
     * @param \Madj2k\AiCore\Connection\Resolver\VectorStoreConnectorResolver $vectorStoreConnectorResolver Vector store connector resolver.
     * @param \Madj2k\AiCore\Indexing\TextChunker $textChunker Text chunker.
     * @param \Madj2k\AiCore\Indexing\Identity\SourceIdentityGenerator $sourceIdentityGenerator Source identity generator.
     */
    public function __construct(
        private AiConnectorResolver $aiConnectorResolver,
        private VectorStoreConnectorResolver $vectorStoreConnectorResolver,
        private TextChunker $textChunker,
        private SourceIdentityGenerator $sourceIdentityGenerator,
    ) {}


    /**
     * Resolves the collection from an explicit override, indexing configuration or connection default.
     *
     * @param \Madj2k\AiCore\Indexing\Configuration\IndexingConfigurationInterface $configuration
     * @param string $collectionOverride
     * @return string
     */
    public function resolveCollection(
        IndexingConfigurationInterface $configuration,
        string $collectionOverride = '',
    ): string {
        $collectionOverride = trim($collectionOverride);
        if ($collectionOverride !== '') {
            return $collectionOverride;
        }

        $collection = trim($configuration->getCollection());
        if ($collection !== '') {
            return $collection;
        }

        return trim($configuration->getVectorStoreConnection()?->getDefaultCollection() ?? '');
    }


    /**
     * Indexes one document and returns the number of written vector chunks.
     *
     * New vectors are written before obsolete source generations are removed.
     * A dry run performs chunking only and returns the number of chunks that would be processed.
     *
     * @param array<int, string> $sourceHashesToDelete Previously stored source hashes.
     * @throws \InvalidArgumentException When the collection name is empty.
     * @throws \RuntimeException When an AI or vector store connection is missing.
     * @throws \Throwable When connector resolution or a provider request fails.
     */
    public function index(
        IndexingConfigurationInterface $configuration,
        IndexableDocument $document,
        string $collectionName,
        bool $dryRun = false,
        array $sourceHashesToDelete = [],
    ): int {
        $collectionName = trim($collectionName);
        if ($collectionName === '') {
            throw new \InvalidArgumentException('Collection name must not be empty.', 1781002001);
        }

        $chunks = $this->chunkDocument($configuration, $document);

        if ($chunks === []) {
            return 0;
        }

        if ($dryRun) {
            return count($chunks);
        }

        [$aiConnection, $vectorStoreConnection] = $this->resolveConnections($configuration);

        $embeddingResponses = $this->embedChunks($aiConnection, $chunks);

        $actualVectorSize = $this->resolveVectorSize($embeddingResponses);
        if ($actualVectorSize === null) {
            return 0;
        }

        $this->validateVectorDimension($aiConnection, $actualVectorSize);

        // The collection dimension is known only after the provider response.
        $collection = new VectorCollection(
            $collectionName,
            $actualVectorSize,
            $vectorStoreConnection->getDistance(),
        );

        [$sourceHash, $indexGeneration, $vectorDocuments] = $this->buildVectorDocuments(
            $document,
            $chunks,
            $embeddingResponses,
            $collection,
        );

        if ($vectorDocuments === []) {
            return 0;
        }

        $vectorStoreConnector = $this->vectorStoreConnectorResolver->get(
            $vectorStoreConnection->getConnectorIdentifier(),
        );

        $writeResult = $this->upsertAndDelete(
            $vectorStoreConnector,
            $vectorStoreConnection,
            $collection,
            $vectorDocuments,
            $sourceHashesToDelete,
            $sourceHash,
            $indexGeneration,
        );

        return $writeResult->getWritten();
    }


    /**
     * Deletes old source members of a complete multi-document group.
     *
     * @param \Madj2k\AiCore\Indexing\Configuration\IndexingConfigurationInterface $configuration
     * @param string $collectionName
     * @param array $sourceHashes
     * @return void
     * @throws \Madj2k\AiCore\Exception\VectorDatabaseException
     */
    public function deleteSourceHashes(
        IndexingConfigurationInterface $configuration,
        string $collectionName,
        array $sourceHashes,
    ): void {

        $connection = $configuration->getVectorStoreConnection();
        if ($connection === null || $sourceHashes === []) {
            return;
        }

        $collection = new VectorCollection(
            $collectionName,
            $configuration->getAiConnection()?->getEmbeddingDimension() ?? 0,
            $connection->getDistance()
        );

        $connector = $this->vectorStoreConnectorResolver->get($connection->getConnectorIdentifier());
        $this->deleteSourceHashesFromStorage($connector, $connection, $collection, $sourceHashes);
    }


    /**
     * Deletes source hashes from one vector-store collection.
     *
     * @param \Madj2k\AiCore\Connection\VectorStore\VectorStoreConnectorInterface $connector
     * @param \Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface $connection
     * @param \Madj2k\AiCore\Connection\VectorStore\DTO\VectorCollection $collection
     * @param array<int, string> $sourceHashes
     * @return void
     * @throws \Madj2k\AiCore\Exception\VectorDatabaseException
     */
    private function deleteSourceHashesFromStorage(
        \Madj2k\AiCore\Connection\VectorStore\VectorStoreConnectorInterface $connector,
        VectorStoreConnectionConfigurationInterface $connection,
        VectorCollection $collection,
        array $sourceHashes,
    ): void {
        $sourceHashes = array_values(array_unique(array_filter(array_map('trim', $sourceHashes))));
        foreach ($sourceHashes as $hash) {
            $connector->deleteBySourceHash($connection, $collection, $hash);
        }
    }


    /**
     * Splits document content according to the configured chunking limits.
     *
     * Non-positive configuration values are normalized to the TextChunker
     * defaults by passing null.
     *
     * @param \Madj2k\AiCore\Indexing\Configuration\IndexingConfigurationInterface $configuration
     * @param \Madj2k\AiCore\Indexing\DTO\IndexableDocument $document
     * @return array<int, string> Generated text chunks.
     */
    private function chunkDocument(
        IndexingConfigurationInterface $configuration,
        IndexableDocument $document,
    ): array {
        $chunkSize = $this->positiveOrNull($configuration->getChunkSize());

        if ($configuration->getChunkingStrategy() === ChunkingStrategy::Atomic) {
            $content = $document->getContent();
            if ($content === '') {
                return [];
            }

            $maximum = $chunkSize ?? 1200;
            if (mb_strlen($content) > $maximum) {
                throw new IndexingException(sprintf(
                    'Atomic chunking cannot index source "%s": content has %d characters, maximum is %d.',
                    $document->getMetadata()->getSourceIdentifier(),
                    mb_strlen($content),
                    $maximum,
                ), 1781002004);
            }

            return [$content];
        }

        return $this->textChunker->chunk(
            $document->getContent(),
            $chunkSize,
            $this->positiveOrNull($configuration->getChunkOverlap()),
            $this->positiveOrNull($configuration->getMaxChunks()),
            $this->positiveOrNull($configuration->getMinChunkChars()),
        );
    }

    /**
     * Resolves and validates the AI and vector-store connections required for
     * a non-dry-run indexing operation.
     *
     * @param \Madj2k\AiCore\Indexing\Configuration\IndexingConfigurationInterface $configuration
     * @return array{0: AiConnectionConfigurationInterface, 1: VectorStoreConnectionConfigurationInterface}
     * @throws \RuntimeException When a required connection is missing.
     */
    private function resolveConnections(
        IndexingConfigurationInterface $configuration,
    ): array {
        $aiConnection = $configuration->getAiConnection();
        if ($aiConnection === null) {
            throw new \RuntimeException('No AI connection configured for indexer.', 1780573401);
        }

        $vectorStoreConnection = $configuration->getVectorStoreConnection();
        if ($vectorStoreConnection === null) {
            throw new \RuntimeException('No vector store connection configured for indexer.', 1780573402);
        }

        return [$aiConnection, $vectorStoreConnection];
    }

    /**
     * Creates embeddings in the same order as the input chunks.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $aiConnection
     * @param array<int, string> $chunks
     * @return array<int, \Madj2k\AiCore\Connection\Ai\DTO\EmbeddingResponse>
     */
    private function embedChunks(
        AiConnectionConfigurationInterface $aiConnection,
        array $chunks,
    ): array {
        $embeddingRequests = array_map(
            static fn (string $chunkText): EmbeddingRequest => new EmbeddingRequest(
                text: $chunkText,
                purpose: EmbeddingPurpose::RetrievalDocument,
            ),
            $chunks,
        );

        return $this->aiConnectorResolver
            ->get($aiConnection->getConnectorIdentifier())
            ->embedBatch($aiConnection, $embeddingRequests);
    }


    /**
     * Validates that the provider dimension matches the configured dimension.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $aiConnection
     * @param int $actualVectorSize
     * @return void
     * @throws \Madj2k\AiCore\Exception\IndexingException
     */
    private function validateVectorDimension(
        AiConnectionConfigurationInterface $aiConnection,
        int $actualVectorSize,
    ): void {
        $configuredVectorSize = $aiConnection->getEmbeddingDimension();
        if ($configuredVectorSize <= 0) {
            throw new IndexingException(
                'The AI connection embedding dimension must be greater than zero.',
                1781002003,
            );
        }
        if ($actualVectorSize !== $configuredVectorSize) {
            throw new IndexingException(sprintf(
                'Embedding dimension mismatch: provider returned %d dimensions, AI connection configuration expects %d.',
                $actualVectorSize,
                $configuredVectorSize,
            ), 1781002002);
        }
    }


    /**
     * Builds vector documents, stable source IDs and the current index generation.
     * Empty embedding responses are omitted from the vector write.
     *
     * @param \Madj2k\AiCore\Indexing\DTO\IndexableDocument $document
     * @param array<int, string> $chunks
     * @param array<int, \Madj2k\AiCore\Connection\Ai\DTO\EmbeddingResponse> $embeddingResponses
     * @param \Madj2k\AiCore\Connection\VectorStore\DTO\VectorCollection $collection
     * @return array{0: string, 1: string, 2: array<int, VectorDocument>}
     */
    private function buildVectorDocuments(
        IndexableDocument $document,
        array $chunks,
        array $embeddingResponses,
        VectorCollection $collection,
    ): array {
        $sourceHash = $document->getSourceHash();
        $indexGeneration = $this->createIndexGeneration($chunks);
        $vectorDocuments = [];
        foreach ($chunks as $index => $chunkText) {
            $embedding = isset($embeddingResponses[$index])
                ? $embeddingResponses[$index]->getEmbedding()
                : [];
            if ($embedding === []) {
                continue;
            }

            $vectorDocuments[] = new VectorDocument(
                id: $this->sourceIdentityGenerator->createVectorDocumentId($sourceHash, $index),
                vector: $embedding,
                payload: $document->createPayload($index, $chunkText, $indexGeneration),
                vectorName: $collection->getName(),
            );
        }

        return [$sourceHash, $indexGeneration, $vectorDocuments];
    }


    /**
     * Writes the new generation first and then removes obsolete source hashes and
     * generations. This preserves the previous generation when the write fails.
     *
     * @param \Madj2k\AiCore\Connection\VectorStore\VectorStoreConnectorInterface $vectorStoreConnector
     * @param \Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface $vectorStoreConnection
     * @param \Madj2k\AiCore\Connection\VectorStore\DTO\VectorCollection $collection
     * @param array<int, VectorDocument> $vectorDocuments
     * @param array $sourceHashesToDelete
     * @param string $sourceHash
     * @param string $indexGeneration
     * @return \Madj2k\AiCore\Connection\VectorStore\DTO\VectorWriteResult
     * @throws \Madj2k\AiCore\Exception\VectorDatabaseException
     */
    private function upsertAndDelete(
        \Madj2k\AiCore\Connection\VectorStore\VectorStoreConnectorInterface $vectorStoreConnector,
        VectorStoreConnectionConfigurationInterface $vectorStoreConnection,
        VectorCollection $collection,
        array $vectorDocuments,
        array $sourceHashesToDelete,
        string $sourceHash,
        string $indexGeneration,
    ): \Madj2k\AiCore\Connection\VectorStore\DTO\VectorWriteResult {

        $writeResult = $vectorStoreConnector->upsert($vectorStoreConnection, $collection, $vectorDocuments);

        $this->deleteSourceHashesFromStorage(
            $vectorStoreConnector,
            $vectorStoreConnection,
            $collection,
            array_diff($sourceHashesToDelete, [$sourceHash]),
        );

        $vectorStoreConnector->deleteObsoleteSourceGenerations(
            $vectorStoreConnection,
            $collection,
            $sourceHash,
            $indexGeneration,
        );

        return $writeResult;
    }


    /**
     * Returns the common dimension of all non-empty embedding responses.
     *
     * @param array<int, \Madj2k\AiCore\Connection\Ai\DTO\EmbeddingResponse> $embeddingResponses Embedding responses.
     * @throws \Madj2k\AiCore\Exception\IndexingException
     */
    private function resolveVectorSize(array $embeddingResponses): ?int
    {
        $vectorSize = null;
        foreach ($embeddingResponses as $index => $embeddingResponse) {
            $embedding = $embeddingResponse->getEmbedding();
            if ($embedding === []) {
                continue;
            }

            $currentSize = count($embedding);
            if ($vectorSize !== null && $currentSize !== $vectorSize) {
                throw new IndexingException(sprintf(
                    'Inconsistent embedding dimensions: response %d has %d dimensions, expected %d.',
                    $index,
                    $currentSize,
                    $vectorSize,
                ), 1781002003);
            }
            $vectorSize = $currentSize;
        }

        return $vectorSize;
    }

    /**
     * Creates a deterministic generation identifier that also changes with chunk boundaries.
     *
     * @param array<int, string> $chunks Document chunks.
     */
    private function createIndexGeneration(array $chunks): string
    {
        $hash = hash_init('sha256');
        foreach ($chunks as $chunk) {
            hash_update($hash, strlen($chunk) . ':' . $chunk);
        }

        return hash_final($hash);
    }

    /**
     * @param int $value
     * @return int|null
     */
    private function positiveOrNull(int $value): ?int
    {
        return $value > 0 ? $value : null;
    }
}
