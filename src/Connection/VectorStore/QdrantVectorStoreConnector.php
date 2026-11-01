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

namespace Madj2k\AiCore\Connection\VectorStore;

use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Factory\QdrantClientFactory;
use Madj2k\AiCore\Connection\Factory\QdrantClientFactoryInterface;
use Madj2k\AiCore\Connection\Resilience\RetryExecutor;
use Madj2k\AiCore\Connection\Resilience\RetryExhaustedException;
use Madj2k\AiCore\Connection\Resilience\RetryPolicy;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorCollection;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorDeleteResult;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorDocument;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorSearchRequest;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorSearchResult;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorWriteResult;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterConjunction;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterOperator;
use Madj2k\AiCore\DTO\DocumentMetadata;
use Madj2k\AiCore\Exception\VectorDatabaseException;
use Psr\Log\LoggerInterface;
use Qdrant\Models\Filter\Condition\MatchAny;
use Qdrant\Models\Filter\Condition\MatchString;
use Qdrant\Models\Filter\Condition\IsEmpty;
use Qdrant\Models\Filter\Filter;
use Qdrant\Models\PointStruct;
use Qdrant\Models\PointsStruct;
use Qdrant\Models\Request\CreateCollection;
use Qdrant\Models\Request\CreateIndex;
use Qdrant\Models\Request\SearchRequest;
use Qdrant\Models\Request\VectorParams;
use Qdrant\Models\VectorStruct;
use Qdrant\Qdrant as Client;

use Psr\Log\NullLogger;

/**
 * Class QdrantVectorStoreConnector
 *
 * Provides shared Qdrant vector store operations.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class QdrantVectorStoreConnector implements VectorStoreConnectorInterface
{
    /**
     * Runtime client cache.
     *
     * @var array<string, \Qdrant\Qdrant>
     */
    protected array $clients = [];

    /**
     * @var array<string, true>
     */
    protected array $validatedCollections = [];

    /**
     * @var \Madj2k\AiCore\Connection\Factory\QdrantClientFactoryInterface
     */
    protected QdrantClientFactoryInterface $clientFactory;

    /**
     * @var \Madj2k\AiCore\Connection\Resilience\RetryPolicy
     */
    protected RetryPolicy $retryPolicy;

    /**
     * @var \Madj2k\AiCore\Connection\Resilience\RetryExecutor
     */
    protected RetryExecutor $retryExecutor;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected LoggerInterface $logger;


    /**
     * Constructor.
     *
     * @param \Psr\Log\LoggerInterface|null $logger Logger.
     * @param \Madj2k\AiCore\Connection\Factory\QdrantClientFactoryInterface|null $clientFactory Client factory.
     * @param \Madj2k\AiCore\Connection\Resilience\RetryPolicy|null $retryPolicy Retry and timeout policy.
     */
    public function __construct(
        ?LoggerInterface $logger = null,
        ?QdrantClientFactoryInterface $clientFactory = null,
        ?RetryPolicy $retryPolicy = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->clientFactory = $clientFactory ?? new QdrantClientFactory();
        $this->retryPolicy = $retryPolicy ?? new RetryPolicy();
        $this->retryExecutor = new RetryExecutor(
            $this->retryPolicy,
            logger: $this->logger,
        );
    }


    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'qdrant';
    }


    /**
     * Creates a Qdrant client for the given connection.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface $connection Vector store connection.
     * @return \Qdrant\Qdrant Qdrant client.
     * @throws \Madj2k\AiCore\Exception\VectorDatabaseException
     */
    protected function createClient(VectorStoreConnectionConfigurationInterface $connection): Client
    {
        if ($connection->getEndpoint() === '') {
            throw new VectorDatabaseException('Missing endpoint in selected vector store connection.', 1780573201);
        }

        /** @var string $cacheKey */
        $cacheKey = sha1($connection->getEndpoint() . '|' . $connection->getApiKey());

        if (isset($this->clients[$cacheKey])) {
            return $this->clients[$cacheKey];
        }

        $this->clients[$cacheKey] = $this->clientFactory->create($connection, $this->retryPolicy);

        return $this->clients[$cacheKey];
    }


    /**
     * @inheritDoc
     */
    public function ensureCollection(VectorStoreConnectionConfigurationInterface $connection, VectorCollection $collection): bool
    {
        $cacheKey = $this->createCollectionCacheKey($connection, $collection);
        if (isset($this->validatedCollections[$cacheKey])) {
            return true;
        }

        try {
            /** @var \Qdrant\Response $response */
            $response = $this->executeRequest(
                'ensureCollection.exists',
                fn () => $this->createClient($connection)->collections($collection->getName())->exists(),
            );

            if ($response->offsetExists('result')) {
                /** @var mixed $result */
                $result = $response->offsetGet('result');

                if (is_array($result) && !empty($result['exists'])) {
                    /** @var \Qdrant\Response $infoResponse */
                    $infoResponse = $this->executeRequest(
                        'ensureCollection.info',
                        fn () => $this->createClient($connection)
                            ->collections($collection->getName())
                            ->info(),
                    );
                    $this->validateCollectionConfiguration($infoResponse, $collection);
                    $this->ensurePayloadIndexes($connection, $collection, $infoResponse);
                    $this->validatedCollections[$cacheKey] = true;
                    return true;
                }

                /** @var \Qdrant\Models\Request\CreateCollection $createCollection */
                $createCollection = new CreateCollection();
                $createCollection->addVector(
                    new VectorParams(
                        $collection->getVectorSize(),
                        $collection->getDistance(),
                    ),
                    $collection->getName()
                );

                /** @var \Qdrant\Response $createResponse */
                $createResponse = $this->executeRequest(
                    'ensureCollection.create',
                    fn () => $this->createClient($connection)
                        ->collections($collection->getName())
                        ->create($createCollection),
                );

                if ($createResponse->offsetExists('result')) {
                    /** @var bool $created */
                    $created = (bool)$createResponse->offsetGet('result');

                    if ($created) {
                        $this->ensurePayloadIndexes($connection, $collection);
                        $this->validatedCollections[$cacheKey] = true;
                        $this->logger->info('Qdrant collection created', [
                            'collection' => $collection->getName(),
                            'vector_size' => $collection->getVectorSize(),
                        ]);
                    }

                    return $created;
                }
            }

            $this->logger->error('Qdrant collection exists check returned no result', [
                'collection' => $collection->getName(),
            ]);

            return false;
        } catch (\Throwable $exception) {
            $this->logger->error('Qdrant ensureCollection failed', [
                'collection' => $collection->getName(),
                'vector_size' => $collection->getVectorSize(),
                'exception' => $exception,
            ]);
            throw $this->createVectorDatabaseException('ensureCollection', $exception);
        }
    }


    /**
     * @inheritDoc
     */
    public function upsert(VectorStoreConnectionConfigurationInterface $connection, VectorCollection $collection, array $documents): VectorWriteResult
    {
        try {
            $this->ensureCollection($connection, $collection);

            /** @var \Qdrant\Models\PointsStruct $points */
            $points = new PointsStruct();

            /** @var int $written */
            $written = 0;

            foreach ($documents as $document) {
                if (!$document instanceof VectorDocument) {
                    continue;
                }

                /** @var string $vectorName */
                $vectorName = $document->getVectorName() !== ''
                    ? $document->getVectorName()
                    : $collection->getName();

                $points->addPoint(
                    new PointStruct(
                        $document->getId(),
                        new VectorStruct($document->getVector(), $vectorName),
                        $document->getPayload()
                    )
                );

                $written++;
            }

            if ($written === 0) {
                return new VectorWriteResult(0);
            }

            /** @var mixed $response */
            $response = $this->executeRequest(
                'upsert',
                fn () => $this->createClient($connection)
                    ->collections($collection->getName())
                    ->points()
                    ->upsert($points, ['wait' => 'true', 'ordering' => 'strong']),
            );

            return new VectorWriteResult($written, $response);
        } catch (\Throwable $exception) {
            $this->logger->error('Qdrant upsert failed', [
                'collection' => $collection->getName(),
                'vector_count' => count($documents),
                'exception' => $exception,
            ]);
            throw $this->createVectorDatabaseException('upsert', $exception);
        }
    }


    /**
     * @inheritDoc
     */
    public function search(VectorStoreConnectionConfigurationInterface $connection, VectorSearchRequest $request): array
    {
        /** @var string $vectorName */
        $vectorName = $request->getVectorName() !== ''
            ? $request->getVectorName()
            : $request->getCollection();

        /** @var array<string, mixed> $params */
        $params = $request->getParams();

        $filter = $request->getFilter();

        try {
            /** @var \Qdrant\Models\Request\SearchRequest $searchRequest */
            $searchRequest = new SearchRequest(new VectorStruct($request->getVector(), $vectorName));
            $searchRequest
                ->setLimit($request->getLimit())
                ->setParams($params)
                ->setWithPayload($request->getWithPayload())
                ->setWithVector($request->getWithVector());

            if ($filter !== null) {
                $searchRequest->setFilter($this->buildQdrantFilter($filter));
            }

            /** @var \Qdrant\Response $response */
            $response = $this->executeRequest(
                'search',
                fn () => $this->createClient($connection)
                    ->collections($request->getCollection())
                    ->points()
                    ->search($searchRequest),
            );
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), 'doesn\'t exist')) {
                return [];
            }

            $this->logger->error('Qdrant search failed', [
                'collection' => $request->getCollection(),
                'limit' => $request->getLimit(),
                'vector_name' => $vectorName,
                'with_payload' => $request->getWithPayload(),
                'with_vector' => $request->getWithVector(),
                'exception' => $exception,
            ]);
            throw $this->createVectorDatabaseException('search', $exception);
        }

        if (!$response->offsetExists('result')) {
            return [];
        }

        /** @var array<int, \Madj2k\AiCore\Connection\VectorStore\DTO\VectorSearchResult> $results */
        $results = [];

        foreach ($response['result'] as $item) {
            /** @var array<string, mixed> $payload */
            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];

            $results[] = new VectorSearchResult(
                (string)($item['id'] ?? ''),
                (float)($item['score'] ?? 0.0),
                $payload
            );
        }

        return $results;
    }


    /**
     * @inheritDoc
     */
    public function listCollections(VectorStoreConnectionConfigurationInterface $connection): array
    {
        try {
            /** @var \Qdrant\Response $response */
            $response = $this->executeRequest(
                'listCollections',
                fn () => $this->createClient($connection)->collections()->list(),
            );

            /** @var mixed $result */
            $result = $response->offsetExists('result') ? $response->offsetGet('result') : [];
            /** @var mixed $collections */
            $collections = is_array($result) ? ($result['collections'] ?? []) : [];

            /** @var array<int, string> $names */
            $names = [];
            foreach ((array)$collections as $collection) {
                if (!is_array($collection)) {
                    continue;
                }

                $name = trim((string)($collection['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }

            sort($names);
            return array_values(array_unique($names));
        } catch (\Throwable $exception) {
            $this->logger->error('Qdrant list collections failed', [
                'endpoint' => $connection->getEndpoint(),
                'exception' => $exception,
            ]);
            throw $this->createVectorDatabaseException('listCollections', $exception);
        }
    }


    /**
     * @inheritDoc
     */
    public function deleteBySourceHash(VectorStoreConnectionConfigurationInterface $connection, VectorCollection $collection, string $sourceHash): VectorDeleteResult
    {
        try {

            $this->ensureCollection($connection, $collection);

            /** @var string $sourceHash */
            $sourceHash = trim($sourceHash);

            if ($sourceHash === '') {
                return new VectorDeleteResult(0);
            }

            /** @var \Qdrant\Models\Filter\Filter $filter */
            $filter = (new Filter())
                ->addMust(new MatchString('meta.source_hash', $sourceHash));


            /** @var mixed $response */
            $response = $this->executeRequest(
                'deleteBySourceHash',
                fn () => $this->createClient($connection)
                    ->collections($collection->getName())
                    ->points()
                    ->deleteByFilter($filter),
            );

            return new VectorDeleteResult(0, $response);
        } catch (\Throwable $exception) {
            $this->logger->error('Qdrant delete by source hash failed', [
                'collection' => $collection->getName(),
                'source_hash' => $sourceHash,
                'exception' => $exception,
            ]);
            throw $this->createVectorDatabaseException('deleteBySourceHash', $exception);
        }
    }


    /**
     * @inheritDoc
     */
    public function deleteObsoleteSourceGenerations(
        VectorStoreConnectionConfigurationInterface $connection,
        VectorCollection $collection,
        string $sourceHash,
        string $indexGeneration,
    ): VectorDeleteResult {
        try {
            $this->ensureCollection($connection, $collection);

            $sourceHash = trim($sourceHash);
            $indexGeneration = trim($indexGeneration);
            if ($sourceHash === '' || $indexGeneration === '') {
                return new VectorDeleteResult(0);
            }

            $filter = (new Filter())
                ->addMust(new MatchString('meta.source_hash', $sourceHash))
                ->addMustNot(new MatchString('meta.index_generation', $indexGeneration));

            $response = $this->executeRequest(
                'deleteObsoleteSourceGenerations',
                fn () => $this->createClient($connection)
                    ->collections($collection->getName())
                    ->points()
                    ->deleteByFilter($filter),
            );

            return new VectorDeleteResult(0, $response);
        } catch (\Throwable $exception) {
            $this->logger->error('Qdrant obsolete source generation cleanup failed', [
                'collection' => $collection->getName(),
                'source_hash' => $sourceHash,
                'index_generation' => $indexGeneration,
                'exception' => $exception,
            ]);
            throw $this->createVectorDatabaseException('deleteObsoleteSourceGenerations', $exception);
        }
    }


    /**
     * @inheritDoc
     */
    public function deleteCollection(VectorStoreConnectionConfigurationInterface $connection, VectorCollection $collection): VectorDeleteResult
    {
        try {
            /** @var mixed $response */
            $response = $this->executeRequest(
                'deleteCollection',
                fn () => $this->createClient($connection)->collections($collection->getName())->delete(),
            );
            $this->validatedCollections = [];

            return new VectorDeleteResult(0, $response);
        } catch (\Throwable $exception) {
            $this->logger->error('Qdrant delete collection failed', [
                'collection' => $collection->getName(),
                'exception' => $exception,
            ]);
            throw $this->createVectorDatabaseException('deleteCollection', $exception);
        }
    }


    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    protected function executeRequest(string $operationName, callable $operation): mixed
    {
        return $this->retryExecutor->execute('qdrant', $operationName, $operation);
    }


    /**
     * @param string $operation
     * @param \Throwable $exception
     * @return \Madj2k\AiCore\Exception\VectorDatabaseException
     */
    protected function createVectorDatabaseException(
        string $operation,
        \Throwable $exception,
    ): VectorDatabaseException {
        if ($exception instanceof VectorDatabaseException) {
            return $exception;
        }

        if ($exception instanceof RetryExhaustedException) {
            return new VectorDatabaseException(
                'Qdrant ' . $operation . ' failed: ' . $exception->getMessage(),
                1780572973,
                $exception->getPrevious() ?? $exception,
                $exception->getProvider(),
                $exception->getOperation(),
                $exception->getStatusCode(),
                $exception->isRetryable(),
                $exception->getAttempts(),
            );
        }

        return new VectorDatabaseException(
            'Qdrant ' . $operation . ' failed: ' . $exception->getMessage(),
            1780572973,
            $exception,
            'qdrant',
            $operation,
            null,
            false,
        );
    }


    /**
     * Validates the vector parameters of an existing collection.
     *
     * @param \Qdrant\Response $response Collection info response.
     * @param \Madj2k\AiCore\Connection\VectorStore\DTO\VectorCollection $collection Expected collection.
     */
    private function validateCollectionConfiguration(\Qdrant\Response $response, VectorCollection $collection): void
    {
        $result = $response->offsetExists('result') ? $response->offsetGet('result') : null;
        $vectors = is_array($result) ? ($result['config']['params']['vectors'] ?? null) : null;
        $vectorParams = is_array($vectors) ? ($vectors[$collection->getName()] ?? null) : null;
        if (!is_array($vectorParams)) {
            throw new \UnexpectedValueException(sprintf(
                'Qdrant collection "%s" does not contain the expected named vector configuration.',
                $collection->getName(),
            ));
        }

        $actualVectorSize = (int)($vectorParams['size'] ?? 0);
        $actualDistance = trim((string)($vectorParams['distance'] ?? ''));
        if (
            $actualVectorSize !== $collection->getVectorSize()
            || strcasecmp($actualDistance, $collection->getDistance()) !== 0
        ) {
            throw new \UnexpectedValueException(sprintf(
                'Qdrant collection "%s" uses vector size %d and distance "%s"; expected %d and "%s". Recreate the collection and reindex its documents.',
                $collection->getName(),
                $actualVectorSize,
                $actualDistance,
                $collection->getVectorSize(),
                $collection->getDistance(),
            ));
        }
    }


    /**
     * Ensures payload indexes for standard and configured additional metadata.
     *
     * Additional fields are configured under `payload_indexes` and are stored
     * below the metadata `additional` container unless the full
     * `meta.additional.` path is supplied.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface $connection
     * @param \Madj2k\AiCore\Connection\VectorStore\DTO\VectorCollection $collection
     * @param \Qdrant\Response|null $collectionInfo
     * @return void
     */
    private function ensurePayloadIndexes(
        VectorStoreConnectionConfigurationInterface $connection,
        VectorCollection $collection,
        ?\Qdrant\Response $collectionInfo = null,
    ): void {
        $existingIndexes = $this->resolveExistingPayloadIndexes($collectionInfo);
        foreach ($this->resolvePayloadIndexDefinitions($connection) as $fieldName => $fieldSchema) {
            if (isset($existingIndexes[$fieldName])) {
                $existingSchema = $existingIndexes[$fieldName]['data_type']
                    ?? $existingIndexes[$fieldName]['type']
                    ?? null;
                $expectedSchema = is_array($fieldSchema) ? ($fieldSchema['type'] ?? null) : $fieldSchema;
                if ($existingSchema !== null && $existingSchema !== $expectedSchema) {
                    throw new \UnexpectedValueException(sprintf(
                        'Qdrant payload index "%s" uses schema "%s"; expected "%s". Recreate the collection.',
                        $fieldName,
                        (string)$existingSchema,
                        (string)$expectedSchema,
                    ));
                }
                continue;
            }

            $this->executeRequest(
                'ensureCollection.payloadIndex',
                fn () => $this->createClient($connection)
                    ->collections($collection->getName())
                    ->index()
                    ->create(new CreateIndex($fieldName, $fieldSchema), ['wait' => 'true']),
            );
        }
    }


    /**
     * Extracts existing Qdrant payload indexes from a collection info response.
     *
     * @param \Qdrant\Response|null $collectionInfo Collection info response.
     * @return array<string, array<string, mixed>>
     */
    private function resolveExistingPayloadIndexes(?\Qdrant\Response $collectionInfo): array
    {
        if ($collectionInfo === null || !$collectionInfo->offsetExists('result')) {
            return [];
        }

        $result = $collectionInfo->offsetGet('result');
        $payloadSchema = is_array($result) ? ($result['payload_schema'] ?? []) : [];

        return is_array($payloadSchema) ? $payloadSchema : [];
    }


    /**
     * Resolves Qdrant payload indexes from DocumentMetadata and connection JSON.
     *
     * DocumentMetadata::toArray() is the source of truth for standard fields,
     * so newly exported scalar metadata fields are indexed automatically.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface $connection
     * @return array<string, string|array<string, mixed>>
     * @throws \InvalidArgumentException For invalid additional index options.
     */
    private function resolvePayloadIndexDefinitions(
        VectorStoreConnectionConfigurationInterface $connection,
    ): array {
        $definitions = [];
        foreach ((new DocumentMetadata())->toArray() as $fieldName => $value) {
            if ($fieldName === 'additional') {
                continue;
            }

            $schema = $this->resolvePayloadSchemaFromValue($value);
            if ($schema !== null) {
                $definitions['meta.' . $fieldName] = $schema;
            }
        }

        $additionalIndexes = $connection->getAdditionalOptionsArray()['payload_indexes'] ?? [];
        if ($additionalIndexes === null) {
            return $definitions;
        }
        if (!is_array($additionalIndexes)) {
            throw new \InvalidArgumentException(
                'Vector store option "payload_indexes" must be a JSON object.',
                1781002101,
            );
        }

        foreach ($additionalIndexes as $fieldName => $schema) {
            if (!is_string($fieldName) || trim($fieldName) === '') {
                throw new \InvalidArgumentException(
                    'Every payload index must define a non-empty field name.',
                    1781002102,
                );
            }

            $fieldName = trim($fieldName);
            if (!str_starts_with($fieldName, 'meta.additional.')) {
                $fieldName = 'meta.additional.' . ltrim($fieldName, '.');
            }

            $definitions[$fieldName] = $this->normalizePayloadSchema($schema, $fieldName);
        }

        return $definitions;
    }


    /**
     * Maps a metadata value to the corresponding Qdrant scalar schema.
     *
     * @param mixed $value Metadata value.
     * @return string|null Qdrant schema type.
     */
    private function resolvePayloadSchemaFromValue(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => 'keyword',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_bool($value) => 'bool',
            default => null,
        };
    }


    /**
     * Validates one configured Qdrant payload schema.
     *
     * @param mixed $schema String schema or Qdrant schema options.
     * @param string $fieldName Payload field name.
     * @return string|array<string, mixed>
     * @throws \InvalidArgumentException For unsupported schemas.
     */
    private function normalizePayloadSchema(mixed $schema, string $fieldName): string|array
    {
        if (is_string($schema)) {
            $schema = trim($schema);
            if ($schema === '') {
                throw new \InvalidArgumentException(
                    sprintf('Payload index "%s" must define a schema.', $fieldName),
                    1781002103,
                );
            }

            $this->assertSupportedPayloadSchema($schema, $fieldName);
            return $schema;
        }

        if (is_array($schema) && isset($schema['type']) && is_string($schema['type'])) {
            $schema['type'] = trim($schema['type']);
            $this->assertSupportedPayloadSchema($schema['type'], $fieldName);
            return $schema;
        }

        throw new \InvalidArgumentException(
            sprintf('Payload index "%s" must use a schema string or object with a type.', $fieldName),
            1781002104,
        );
    }


    /**
     * @param string $schema Qdrant payload schema type.
     * @param string $fieldName Payload field name.
     * @return void
     * @throws \InvalidArgumentException For unsupported schemas.
     */
    private function assertSupportedPayloadSchema(string $schema, string $fieldName): void
    {
        if (!in_array($schema, ['bool', 'datetime', 'float', 'geo', 'integer', 'keyword', 'text', 'uuid'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported Qdrant payload schema "%s" for field "%s".', $schema, $fieldName),
                1781002105,
            );
        }
    }


    /**
     * Creates the cache key for one validated collection configuration.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface $connection
     * @param \Madj2k\AiCore\Connection\VectorStore\DTO\VectorCollection $collection
     * @return string
     */
    private function createCollectionCacheKey(
        VectorStoreConnectionConfigurationInterface $connection,
        VectorCollection $collection,
    ): string {
        return sha1(implode('|', [
            $connection->getEndpoint(),
            $connection->getApiKey(),
            $collection->getName(),
            (string)$collection->getVectorSize(),
            $collection->getDistance(),
        ]));
    }


    /**
     * Builds a native Qdrant filter from the generic filter configuration.
     *
     * @param FilterGroup $filter Generic filter configuration.
     * @return \Qdrant\Models\Filter\Filter Qdrant filter.
     */
    protected function buildQdrantFilter(FilterGroup $filter): Filter
    {
        $qdrantFilter = new Filter();
        foreach ($filter->conditions as $condition) {
            $key = trim($condition->field);
            if ($key === '') {
                continue;
            }

            $target = $filter->conjunction === FilterConjunction::Or ? 'addShould' : 'addMust';
            if ($condition->operator === FilterOperator::Equals) {
                $qdrantFilter->{$target}(new MatchString($key, (string)$condition->value));
                continue;
            }
            if ($condition->operator === FilterOperator::In) {
                $values = is_array($condition->value) ? array_values($condition->value) : [$condition->value];
                $qdrantFilter->{$target}(new MatchAny($key, $values));
                continue;
            }
            if ($condition->operator === FilterOperator::Exists) {
                $qdrantFilter->addMustNot(new IsEmpty($key));
                continue;
            }
        }

        return $qdrantFilter;
    }
}
