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

namespace Madj2k\AiCore\Assistant\Pipeline\Processor\Retrieval;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Configuration\FilterAwarePipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\DTO\RetrievalDocument;
use Madj2k\AiCore\Assistant\Pipeline\Processor\AbstractRetrieverProcessor;
use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest;
use Madj2k\AiCore\Connection\Ai\Enum\EmbeddingPurpose;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorSearchRequest;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Connection\Resolver\VectorStoreConnectorResolver;
use Madj2k\AiCore\Connection\VectorStore\Filter\FilterValueResolverInterface;
use Madj2k\AiCore\Connection\VectorStore\Filter\IdentityFilterValueResolver;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterConjunction;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup;

/**
 * Class RetrieverProcessor
 *
 * Pipeline processor that delegates retrieval to a connector.
 *
 * @internal Register custom pipeline behavior through ProcessorInterface.
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class RetrieverProcessor extends AbstractRetrieverProcessor
{

    /**
     * Constructor.
     *
     * @param \Madj2k\AiCore\Connection\Resolver\AiConnectorResolver $aiConnectorResolver AI connector registry.
     * @param \Madj2k\AiCore\Connection\Resolver\VectorStoreConnectorResolver $vectorStoreConnectorResolver Vector store connector registry.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface $pipelineLogger Pipeline logger.
     * @param \Madj2k\AiCore\Connection\VectorStore\FilterValueResolverInterface $filterValueResolver Filter value resolver.
     */
    public function __construct(
        private AiConnectorResolver          $aiConnectorResolver,
        private VectorStoreConnectorResolver $vectorStoreConnectorResolver,
        private PipelineLoggerInterface      $pipelineLogger,
        private ?FilterValueResolverInterface $filterValueResolver = null,
    ) {
    }


    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'aiassistant.retriever.default';
    }


    /**
     * @inheritDoc
     */
    public function canProcess(Context $context, PipelineStepConfigurationInterface $step): bool
    {
        if (method_exists($step, 'getRetrievalIdentifier')
            && !$context->getRetrievalPlan()->allows((string)$step->getRetrievalIdentifier())
        ) {
            return false;
        }

        $connection = $this->resolveVectorStoreConnection($context, $step);

        return trim($context->getCurrentQuery()) !== ''
            && $connection !== null
            && $this->resolveCollection($step, $connection, false) !== '';
    }


    /**
     * @inheritDoc
     * @throws \Madj2k\AiCore\Exception\VectorDatabaseException
     */
    public function process(Context $context, PipelineStepConfigurationInterface $step, ?PipelineLogMetaData $logContext = null): void
    {
        $vectorStoreConnection = $this->resolveVectorStoreConnection($context, $step);
        if ($vectorStoreConnection === null) {
            throw new \RuntimeException('No vector store connection configured for retriever step or assistant profile.', 1780573302);
        }
        $collection = $this->resolveCollection($step, $vectorStoreConnection);

        if ($logContext instanceof PipelineLogMetaData) {
            $this->pipelineLogger->logRetrievalRequest($logContext, $step->getTitle(), $this->getIdentifier(), [
                'query' => $context->getCurrentQuery(),
                'collection' => $collection,
                'max_retrieval_results' => $step->getMaxRetrievalResults(),
                'score_threshold' => $step->getScoreThreshold(),
                'prompt_metadata_fields' => $step->getPromptMetadataFieldList(),
            ]);
        }

        $aiConnection = $context->getAssistant()->getAiConnection();
        if ($aiConnection === null) {
            throw new \RuntimeException('No AI connection configured for assistant profile.', 1780573301);
        }

        $embedding = $this->aiConnectorResolver
            ->get($aiConnection->getConnectorIdentifier())
            ->embed($aiConnection, new EmbeddingRequest(
                text: $context->getCurrentQuery(),
                purpose: EmbeddingPurpose::RetrievalQuery,
            ))
            ->getEmbedding();

        $filter = $step instanceof FilterAwarePipelineStepConfigurationInterface
            ? $step->getRetrievalFilter()
            : null;
        $filter = ($this->filterValueResolver ?? new IdentityFilterValueResolver())->resolve($filter, $context);
        $filter = $this->mergeIntentPayloadFilter($context, $filter);

        $rows = $this->vectorStoreConnectorResolver
            ->get($vectorStoreConnection->getConnectorIdentifier())
            ->search($vectorStoreConnection, new VectorSearchRequest(
                collection: $collection,
                vector: $embedding,
                limit: $step->getMaxRetrievalResults(),
                params: [
                    'hnsw_ef' => 128,
                    'exact' => false,
                ],
                withPayload: true,
                withVector: false,
                vectorName: $collection,
                filter: $filter,
            ));


        $documents = [];
        foreach ($rows as $row) {
            $payload = $row->getPayload();
            $score = $row->getScore();

            if ($step->getScoreThreshold() > 0.0 && $score < $step->getScoreThreshold()) {
                continue;
            }

            $documentMetadata = $this->extractMetadata($payload, $step);
            $documents[] = new RetrievalDocument(
                id: $row->getId(),
                score: $score,
                text: $this->extractText($payload),
                documentMetadata: $documentMetadata,
            );
        }

        if ($logContext instanceof PipelineLogMetaData) {
            $this->pipelineLogger->logRetrievalResponse($logContext, $step->getTitle(), $this->getIdentifier(), [
                'result_count' => count($documents),
                'results' => $this->normalizeRawResults($rows),
            ]);
        }

        $this->storeRetrievalGroup(
            $context,
            $step,
            $this->getIdentifier(),
            $documents,
            $this->normalizeRawResults($rows),
            collection: $collection,
        );

        // trace
        $context->getProcessingTrace()->add('retriever.completed',
            $step->getUid(),
            $context->getCurrentQuery(),
            $documents,
        );

    }

    /**
     * Merges a selector payload filter with the static retriever filter.
     *
     * Dynamic intent filters must apply to every retriever allowed by the
     * retrieval plan. Static conditions remain active and are combined with
     * the dynamic conditions using a logical AND.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Current assistant context.
     * @param \Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup|null $filter Static filter.
     * @return \Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup|null Combined filter.
     */
    private function mergeIntentPayloadFilter(Context $context, ?FilterGroup $filter): ?FilterGroup
    {
        $payloadFilter = $context->getRetrievalPlan()->payloadFilter;
        $payloadRetrievers = $context->getRetrievalPlan()->payloadRetrievers;
        if ($payloadRetrievers !== []
            && !in_array($step->getRetrievalIdentifier(), $payloadRetrievers, true)
        ) {
            return $filter;
        }

        if ($payloadFilter === null || $payloadFilter->isEmpty()) {
            return $filter;
        }

        if ($filter === null || $filter->isEmpty()) {
            return $payloadFilter;
        }

        return new FilterGroup(
            FilterConjunction::And,
            array_merge($filter->conditions, $payloadFilter->conditions),
        );
    }


    /**
     * Normalizes raw connector rows for logging and context storage.
     *
     * @param array<int,mixed> $rows Raw connector rows.
     * @return array<int,mixed> Normalized raw rows.
     */
    private function normalizeRawResults(array $rows): array
    {
        $rawResults = [];

        foreach ($rows as $row) {
            if (is_object($row)) {
                if (method_exists($row, 'toArray')) {
                    $rawResults[] = $row->toArray();
                }
                continue;
            }

            $rawResults[] = $row;
        }

        return $rawResults;
    }


    /**
     * Resolves the step-specific connection or the assistant profile default.
     *
     * @param Context $context Current assistant context.
     * @param PipelineStepConfigurationInterface $step Current pipeline step.
     * @return VectorStoreConnectionConfigurationInterface|null Effective vector store connection.
     */
    private function resolveVectorStoreConnection(
        Context $context,
        PipelineStepConfigurationInterface $step,
    ): ?VectorStoreConnectionConfigurationInterface {
        return $step->getRetrievalVectorStoreConnection()
            ?? $context->getAssistant()->getVectorStoreConnection();
    }


    /**
     * Resolves the step-specific collection or the effective connection default.
     *
     * @param PipelineStepConfigurationInterface $step Current pipeline step.
     * @param VectorStoreConnectionConfigurationInterface $connection Effective vector store connection.
     * @param bool $validateOverride Whether to validate the step override against the connection.
     * @return string Effective collection name.
     */
    private function resolveCollection(
        PipelineStepConfigurationInterface $step,
        VectorStoreConnectionConfigurationInterface $connection,
        bool $validateOverride = true,
    ): string {
        $override = trim($step->getRetrievalCollection());

        if ($override !== '') {
            if (
                $validateOverride
                && !in_array($override, $connection->getCollectionList(), true)
            ) {
                throw new \RuntimeException(sprintf(
                    'Collection "%s" is not configured for the selected vector store connection.',
                    $override,
                ), 1786047702);
            }

            return $override;
        }

        return trim($connection->getDefaultCollection());
    }
}
