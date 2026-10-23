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

namespace Madj2k\AiCore\Indexing\Indexer;

use Madj2k\AiCore\Indexing\Configuration\IndexingConfigurationInterface;
use Madj2k\AiCore\Indexing\DTO\IndexableDocument;
use Madj2k\AiCore\Indexing\DTO\IndexingRequest;
use Madj2k\AiCore\Indexing\DTO\IndexingResult;
use Madj2k\AiCore\Indexing\State\SourceStateInterface;
use Madj2k\AiCore\Indexing\VectorDocumentIndexer;

/**
 * Class AbstractIndexer
 *
 * Configuration lookup, persistence and logging are deliberately supplied by
 * the integration adapter. This class only coordinates document indexing.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
abstract class AbstractIndexer implements IndexerInterface
{

    /**
     * Constructor.
     *
     * @param \Madj2k\AiCore\Indexing\State\SourceStateInterface $sourceState
     * @param \Madj2k\AiCore\Indexing\VectorDocumentIndexer $vectorDocumentIndexer
     */
    public function __construct(
        protected readonly SourceStateInterface $sourceState,
        protected readonly VectorDocumentIndexer $vectorDocumentIndexer,
    ) {
    }

    /**
     * Coordinates the indexing of one document.
     *
     * The method validates the content and target collection, skips unchanged
     * documents, writes the vectors and persists the resulting source state.
     *
     * @param \Madj2k\AiCore\Indexing\Configuration\IndexingConfigurationInterface $configuration
     * @param \Madj2k\AiCore\Indexing\DTO\IndexableDocument $document
     * @param \Madj2k\AiCore\Indexing\DTO\IndexingRequest $request
     * @param \Madj2k\AiCore\Indexing\DTO\IndexingResult $result
     * @param bool $deleteObsolete
     * @return bool
     */
    protected function indexDocument(
        IndexingConfigurationInterface $configuration,
        IndexableDocument $document,
        IndexingRequest $request,
        IndexingResult $result,
        bool $deleteObsolete = true,
    ): bool {
        if ($document->getContent() === '') {
            $result->increaseSkipped();
            return false;
        }

        // Resolve the strategy before unchanged-source skipping so invalid
        // persisted configuration is reported instead of hidden by a skip.
        $configuration->getChunkingStrategy();

        // get collection
        $collection = $this->vectorDocumentIndexer->resolveCollection($configuration, $request->getCollection());
        if ($collection === '') {
            $result->increaseSkipped();
            return false;
        }

        if ($this->sourceState->shouldSkip($configuration, $document, $collection, $request->isOnlyChanged())) {
            $result->increaseSkipped();
            return false;
        }

        try {

            // get all relevant hashes to delete
            // for multi-page-documents that may be all hashes of the document group
            $sourceHashesToDelete = $this->sourceState->getStorageSourceHashesForDeletion(
                $configuration,
                $document,
                $collection,
            );

            $chunksWritten = $this->vectorDocumentIndexer->index(
                $configuration,
                $document,
                $collection,
                $request->isDryRun(),
                $deleteObsolete ? $sourceHashesToDelete : [],
            );

            if ($chunksWritten === 0) {
                $result->increaseSkipped();
                return false;
            }

            if (!$request->isDryRun()) {
                $this->sourceState->markIndexed($configuration, $document, $collection);
            }

            $result->increaseIndexed();
            $result->increaseChunksTotal($chunksWritten);
            return true;

        } catch (\Throwable $exception) {
            $result->increaseFailed();
            $result->addDetail('source_error_' . $result->getProcessed(), [
                'source_type' => $document->getMetadata()->getSourceType(),
                'source_identifier' => $document->getMetadata()->getSourceIdentifier(),
                'message' => $exception->getMessage(),
            ]);

            if (!$request->isDryRun()) {
                $this->sourceState->markFailed($configuration, $document, $collection, $exception);
            }

            return false;
        }
    }

    /**
     * Indexes all members and reconciles stale members only after the group succeeds.
     *
     * Every member is indexed without individual stale-member deletion first.
     * Once all members succeed, source hashes that are no longer present in the
     * group are removed from vector storage and persisted source state.
     *
     * @param array<int, IndexableDocument> $documents
     * @throws \Madj2k\AiCore\Exception\VectorDatabaseException
     */
    protected function indexDocumentGroup(
        IndexingConfigurationInterface $configuration,
        array $documents,
        IndexingRequest $request,
        IndexingResult $result,
    ): bool {

        if ($documents === []) {
            return false;
        }

        $collection = $this->vectorDocumentIndexer->resolveCollection($configuration, $request->getCollection());
        $sourceHashesToDelete = $this->sourceState->getStorageSourceHashesForDeletion(
            $configuration,
            $documents[0],
            $collection,
        );

        foreach ($documents as $document) {
            $failedBefore = $result->getFailed();
            $this->indexDocument($configuration, $document, $request, $result, false);
            if ($result->getFailed() > $failedBefore) {
                return false;
            }
        }

        if (!$request->isDryRun()) {
            $currentHashes = array_map(
                fn (IndexableDocument $document): string => $document->getSourceHash(),
                $documents,
            );

            $this->vectorDocumentIndexer->deleteSourceHashes(
                $configuration,
                $collection,
                array_diff($sourceHashesToDelete, $currentHashes),
            );

            $this->sourceState->removeStaleGroupMembers(
                $configuration,
                $documents[0],
                $collection,
                $currentHashes,
            );
        }

        return true;
    }

    /**
     * Checks whether the configured batch limit has been reached.
     *
     * @param \Madj2k\AiCore\Indexing\DTO\IndexingRequest $request
     * @param \Madj2k\AiCore\Indexing\DTO\IndexingResult $result
     * @return bool
     */
    protected function isLimitReached(IndexingRequest $request, IndexingResult $result): bool
    {
        return $request->getLimit() !== null && $result->getProcessed() >= $request->getLimit();
    }
}
