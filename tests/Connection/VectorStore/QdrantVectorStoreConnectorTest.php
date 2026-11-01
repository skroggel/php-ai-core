<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Connection\VectorStore;

use GuzzleHttp\Psr7\Response;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfiguration;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Factory\QdrantClientFactoryInterface;
use Madj2k\AiCore\Connection\Resilience\RetryPolicy;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorCollection;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorDocument;
use Madj2k\AiCore\DTO\DocumentMetadata;
use Madj2k\AiCore\Connection\VectorStore\QdrantVectorStoreConnector;
use Madj2k\AiCore\Exception\VectorDatabaseException;
use Madj2k\AiCore\Tests\Support\QueueHttpClient;
use PHPUnit\Framework\TestCase;
use Qdrant\Config;
use Qdrant\Http\Transport;
use Qdrant\Qdrant;

final class QdrantVectorStoreConnectorTest extends TestCase
{
    public function testUsesInjectedClientAndNormalizesCollectionNames(): void
    {
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(200, [
                'result' => ['collections' => [
                    ['name' => 'zeta'],
                    ['name' => 'alpha'],
                    ['name' => 'alpha'],
                ]],
            ]),
        ]);
        $factory = $this->factoryFor($httpClient);
        $connector = new QdrantVectorStoreConnector(clientFactory: $factory);

        self::assertSame(['alpha', 'zeta'], $connector->listCollections($this->connection()));
        self::assertSame(1, $factory->calls);
        self::assertCount(1, $httpClient->requests);
    }

    public function testRetriesTransientQdrantFailure(): void
    {
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(503, ['status' => ['error' => 'temporarily unavailable']]),
            $this->jsonResponse(200, ['result' => ['collections' => [['name' => 'documents']]]]),
        ]);
        $policy = new RetryPolicy(maxAttempts: 2, initialDelayMilliseconds: 0);
        $connector = new QdrantVectorStoreConnector(
            clientFactory: $this->factoryFor($httpClient),
            retryPolicy: $policy,
        );

        self::assertSame(['documents'], $connector->listCollections($this->connection()));
        self::assertCount(2, $httpClient->requests);
    }

    public function testExposesStructuredQdrantErrorAfterRetries(): void
    {
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(503, ['status' => ['error' => 'unavailable']]),
            $this->jsonResponse(503, ['status' => ['error' => 'unavailable']]),
        ]);
        $policy = new RetryPolicy(maxAttempts: 2, initialDelayMilliseconds: 0);
        $connector = new QdrantVectorStoreConnector(
            clientFactory: $this->factoryFor($httpClient),
            retryPolicy: $policy,
        );

        try {
            $connector->listCollections($this->connection());
            self::fail('Expected vector database exception was not thrown.');
        } catch (VectorDatabaseException $exception) {
            self::assertSame('qdrant', $exception->getProvider());
            self::assertSame('listCollections', $exception->getOperation());
            self::assertSame(503, $exception->getStatusCode());
            self::assertSame(2, $exception->getAttempts());
            self::assertTrue($exception->isRetryable());
        }
    }

    public function testUpsertWaitsForStronglyOrderedCompletion(): void
    {
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(200, ['result' => ['exists' => true]]),
            $this->collectionInfo(),
            $this->jsonResponse(200, ['result' => ['status' => 'completed']]),
        ]);
        $connector = new QdrantVectorStoreConnector(clientFactory: $this->factoryFor($httpClient));

        $connector->upsert($this->connection(), new VectorCollection('documents'), [
            new VectorDocument('point-1', [1.0, 2.0], vectorName: 'documents'),
        ]);

        self::assertStringContainsString('wait=true', (string)$httpClient->requests[2]->getUri());
        self::assertStringContainsString('ordering=strong', (string)$httpClient->requests[2]->getUri());
    }

    public function testDeletesOnlyObsoleteSourceGenerations(): void
    {
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(200, ['result' => ['exists' => true]]),
            $this->collectionInfo(),
            $this->jsonResponse(200, ['result' => ['status' => 'completed']]),
        ]);
        $connector = new QdrantVectorStoreConnector(clientFactory: $this->factoryFor($httpClient));

        $connector->deleteObsoleteSourceGenerations(
            $this->connection(),
            new VectorCollection('documents'),
            'source-123',
            'generation-456',
        );

        $body = json_decode((string)$httpClient->requests[2]->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('source-123', $body['filter']['must'][0]['match']['value']);
        self::assertSame('generation-456', $body['filter']['must_not'][0]['match']['value']);
    }

    public function testCreatesCollectionWithConfiguredVectorParameters(): void
    {
        $metadataIndexCount = count(array_filter(
            (new DocumentMetadata())->toArray(),
            static fn (mixed $value, string $field): bool => $field !== 'additional',
            ARRAY_FILTER_USE_BOTH,
        ));
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(200, ['result' => ['exists' => false]]),
            $this->jsonResponse(200, ['result' => true]),
            ...array_map(
                fn (): Response => $this->jsonResponse(200, ['result' => true]),
                range(1, $metadataIndexCount),
            ),
        ]);
        $connector = new QdrantVectorStoreConnector(clientFactory: $this->factoryFor($httpClient));

        self::assertTrue($connector->ensureCollection(
            $this->connection(),
            new VectorCollection('documents', 3072, 'Dot'),
        ));

        $body = json_decode((string)$httpClient->requests[1]->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(3072, $body['vectors']['documents']['size']);
        self::assertSame('Dot', $body['vectors']['documents']['distance']);
        self::assertCount(2 + $metadataIndexCount, $httpClient->requests);

        $indexBody = json_decode((string)$httpClient->requests[2]->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('meta.source_type', $indexBody['field_name']);
        self::assertSame(['type' => 'keyword'], $indexBody['field_schema']);
    }

    public function testCreatesConfiguredAdditionalPayloadIndexes(): void
    {
        $metadataIndexCount = count(array_filter(
            (new DocumentMetadata())->toArray(),
            static fn (mixed $value, string $field): bool => $field !== 'additional',
            ARRAY_FILTER_USE_BOTH,
        ));
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(200, ['result' => ['exists' => false]]),
            $this->jsonResponse(200, ['result' => true]),
            ...array_map(
                fn (): Response => $this->jsonResponse(200, ['result' => true]),
                range(1, $metadataIndexCount + 2),
            ),
        ]);
        $connector = new QdrantVectorStoreConnector(clientFactory: $this->factoryFor($httpClient));
        $connection = new VectorStoreConnectionConfiguration(
            'https://qdrant.test',
            additionalOptions: [
                'payload_indexes' => [
                    'asset_type' => 'keyword',
                    'asset_id' => ['type' => 'integer'],
                ],
            ],
        );

        $connector->ensureCollection($connection, new VectorCollection('documents'));

        $assetTypeBody = json_decode(
            (string)$httpClient->requests[2 + $metadataIndexCount]->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $assetIdBody = json_decode(
            (string)$httpClient->requests[3 + $metadataIndexCount]->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('meta.additional.asset_type', $assetTypeBody['field_name']);
        self::assertSame(['type' => 'keyword'], $assetTypeBody['field_schema']);
        self::assertSame('meta.additional.asset_id', $assetIdBody['field_name']);
        self::assertSame(['type' => 'integer'], $assetIdBody['field_schema']);
    }

    public function testRejectsExistingCollectionWithWrongVectorSize(): void
    {
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(200, ['result' => ['exists' => true]]),
            $this->collectionInfo(vectorSize: 768),
        ]);
        $connector = new QdrantVectorStoreConnector(clientFactory: $this->factoryFor($httpClient));

        $this->expectException(VectorDatabaseException::class);
        $this->expectExceptionMessage('uses vector size 768 and distance "Cosine"; expected 1536 and "Cosine"');

        $connector->ensureCollection($this->connection(), new VectorCollection('documents'));
    }

    public function testRejectsExistingCollectionWithWrongDistance(): void
    {
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(200, ['result' => ['exists' => true]]),
            $this->collectionInfo(distance: 'Dot'),
        ]);
        $connector = new QdrantVectorStoreConnector(clientFactory: $this->factoryFor($httpClient));

        $this->expectException(VectorDatabaseException::class);
        $this->expectExceptionMessage('uses vector size 1536 and distance "Dot"; expected 1536 and "Cosine"');

        $connector->ensureCollection($this->connection(), new VectorCollection('documents'));
    }

    public function testCachesSuccessfulCollectionValidation(): void
    {
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(200, ['result' => ['exists' => true]]),
            $this->collectionInfo(),
        ]);
        $connector = new QdrantVectorStoreConnector(clientFactory: $this->factoryFor($httpClient));
        $collection = new VectorCollection('documents');

        self::assertTrue($connector->ensureCollection($this->connection(), $collection));
        self::assertTrue($connector->ensureCollection($this->connection(), $collection));
        self::assertCount(2, $httpClient->requests);
    }

    public function testAddsMissingPayloadIndexToExistingCollection(): void
    {
        $payloadSchema = $this->standardPayloadSchema();
        unset($payloadSchema['meta.language']);
        $httpClient = new QueueHttpClient([
            $this->jsonResponse(200, ['result' => ['exists' => true]]),
            $this->collectionInfo(payloadSchema: $payloadSchema),
            $this->jsonResponse(200, ['result' => true]),
        ]);
        $connector = new QdrantVectorStoreConnector(clientFactory: $this->factoryFor($httpClient));

        $connector->ensureCollection(
            $this->connection(),
            new VectorCollection('documents'),
        );

        $body = json_decode((string)$httpClient->requests[2]->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('meta.language', $body['field_name']);
        self::assertSame(['type' => 'keyword'], $body['field_schema']);
    }

    private function connection(): VectorStoreConnectionConfiguration
    {
        return new VectorStoreConnectionConfiguration('https://qdrant.test');
    }

    /** @param array<string, mixed> $body */
    private function jsonResponse(int $status, array $body): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            (string)json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, array{data_type:string}>|null $payloadSchema
     */
    private function collectionInfo(
        int $vectorSize = 1536,
        string $distance = 'Cosine',
        ?array $payloadSchema = null,
    ): Response
    {
        return $this->jsonResponse(200, [
            'result' => [
                'config' => [
                    'params' => [
                        'vectors' => [
                            'documents' => [
                                'size' => $vectorSize,
                                'distance' => $distance,
                            ],
                        ],
                    ],
                ],
                'payload_schema' => $payloadSchema ?? $this->standardPayloadSchema(),
            ],
        ]);
    }

    /**
     * @return array<string, array{data_type:string}>
     */
    private function standardPayloadSchema(): array
    {
        $schema = [];
        foreach ((new DocumentMetadata())->toArray() as $fieldName => $value) {
            if ($fieldName === 'additional') {
                continue;
            }

            $type = match (true) {
                is_string($value) => 'keyword',
                is_int($value) => 'integer',
                is_float($value) => 'float',
                is_bool($value) => 'bool',
                default => null,
            };
            if ($type !== null) {
                $schema['meta.' . $fieldName] = ['data_type' => $type];
            }
        }

        return $schema;
    }

    private function factoryFor(QueueHttpClient $httpClient): QdrantClientFactoryInterface
    {
        return new class($httpClient) implements QdrantClientFactoryInterface {
            public int $calls = 0;

            public function __construct(private readonly QueueHttpClient $httpClient) {}

            public function create(
                VectorStoreConnectionConfigurationInterface $connection,
                RetryPolicy $policy,
            ): Qdrant {
                $this->calls++;
                return new Qdrant(new Transport($this->httpClient, new Config($connection->getEndpoint())));
            }
        };
    }
}
