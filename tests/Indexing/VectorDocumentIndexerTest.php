<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Indexing;

use Madj2k\AiCore\Connection\Ai\AiConnectorInterface;
use Madj2k\AiCore\Connection\Ai\DTO\AiRequest;
use Madj2k\AiCore\Connection\Ai\DTO\AiResponse;
use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest;
use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingResponse;
use Madj2k\AiCore\Connection\Ai\Enum\EmbeddingPurpose;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfiguration;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfiguration;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Connection\Resolver\VectorStoreConnectorResolver;
use Madj2k\AiCore\DTO\DocumentMetadata;
use Madj2k\AiCore\Exception\IndexingException;
use Madj2k\AiCore\Indexing\Configuration\IndexingConfigurationInterface;
use Madj2k\AiCore\Indexing\DTO\IndexableDocument;
use Madj2k\AiCore\Indexing\Identity\SourceIdentityGenerator;
use Madj2k\AiCore\Indexing\Enum\ChunkingStrategy;
use Madj2k\AiCore\Indexing\TextChunker;
use Madj2k\AiCore\Indexing\VectorDocumentIndexer;
use Madj2k\AiCore\Tests\Support\RecordingVectorStoreConnector;
use PHPUnit\Framework\TestCase;

final class VectorDocumentIndexerTest extends TestCase
{
    public function testIndexesChunksAndReplacesCurrentAndLegacySourceHashes(): void
    {
        $vectorConnector = new RecordingVectorStoreConnector();
        [$indexer, $configuration, $document, $identity] = $this->createFixture($vectorConnector);

        $written = $indexer->index($configuration, $document, 'documents', false, ['legacy-hash']);

        $sourceHash = $identity->createSourceHash($document);
        self::assertSame(2, $written);
        self::assertSame(['legacy-hash'], $vectorConnector->deletedHashes);
        self::assertSame($sourceHash, $vectorConnector->generationCleanups[0]['sourceHash']);
        self::assertNotSame('', $vectorConnector->generationCleanups[0]['indexGeneration']);
        self::assertSame([
            'upsert',
            'delete:legacy-hash',
            'cleanup:' . $sourceHash,
        ], $vectorConnector->operations);
        self::assertCount(2, $vectorConnector->writtenDocuments);
        self::assertSame(2, $vectorConnector->upsertCollection?->getVectorSize());
        self::assertSame('Dot', $vectorConnector->upsertCollection?->getDistance());
        self::assertSame('documents', $vectorConnector->writtenDocuments[0]->getVectorName());
        self::assertSame($sourceHash, $vectorConnector->writtenDocuments[0]->getPayload()['meta']['source_hash']);
        self::assertSame(
            $vectorConnector->generationCleanups[0]['indexGeneration'],
            $vectorConnector->writtenDocuments[0]->getPayload()['meta']['index_generation'],
        );
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $vectorConnector->writtenDocuments[0]->getId());
    }

    public function testKeepsExistingVectorsWhenUpsertFails(): void
    {
        $vectorConnector = new RecordingVectorStoreConnector();
        $vectorConnector->failUpsert = true;
        [$indexer, $configuration, $document] = $this->createFixture($vectorConnector);

        try {
            $indexer->index($configuration, $document, 'documents', false, ['legacy-hash']);
            self::fail('Expected the simulated upsert failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Simulated upsert failure.', $exception->getMessage());
        }

        self::assertSame(['upsert'], $vectorConnector->operations);
        self::assertSame([], $vectorConnector->deletedHashes);
        self::assertSame([], $vectorConnector->generationCleanups);
    }

    public function testRejectsEmbeddingDimensionThatDoesNotMatchConnection(): void
    {
        $vectorConnector = new RecordingVectorStoreConnector();
        [$indexer, $configuration, $document] = $this->createFixture(
            $vectorConnector,
            embeddingDimension: 3,
        );

        $this->expectException(IndexingException::class);
        $this->expectExceptionMessage('provider returned 2 dimensions, AI connection configuration expects 3');

        $indexer->index($configuration, $document, 'documents');
    }

    public function testAiConnectionDimensionDefinesVectorCollectionDimension(): void
    {
        $vectorConnector = new RecordingVectorStoreConnector();
        [$indexer, $configuration, $document] = $this->createFixture(
            $vectorConnector,
            embeddingDimension: 2,
        );

        $written = $indexer->index($configuration, $document, 'documents');

        self::assertSame(2, $written);
        self::assertSame(2, $vectorConnector->upsertCollection?->getVectorSize());
    }

    public function testRejectsInconsistentEmbeddingDimensions(): void
    {
        $vectorConnector = new RecordingVectorStoreConnector();
        [$indexer, $configuration, $document] = $this->createFixture(
            $vectorConnector,
            embeddings: [[1.0, 2.0], [1.0, 2.0, 3.0]],
        );

        $this->expectException(IndexingException::class);
        $this->expectExceptionMessage('response 1 has 3 dimensions, expected 2');

        $indexer->index($configuration, $document, 'documents');
    }

    public function testAtomicChunkingKeepsCompleteTextAndRejectsOversizedDocumentsBeforeEmbedding(): void
    {
        $vectorConnector = new RecordingVectorStoreConnector();
        [$indexer, $configuration, $document] = $this->createFixture(
            $vectorConnector,
            embeddings: [[1.0, 2.0]],
            strategy: ChunkingStrategy::Atomic,
        );

        $this->expectException(IndexingException::class);
        $this->expectExceptionMessage('Atomic chunking cannot index');

        $indexer->index($configuration, $document, 'documents', false, ['existing-hash']);
        self::fail('The oversized atomic document must fail before embedding.');
    }

    /**
     * @param array<int, array<int, float>> $embeddings
     * @return array{VectorDocumentIndexer,IndexingConfigurationInterface,IndexableDocument,SourceIdentityGenerator}
     */
    private function createFixture(
        RecordingVectorStoreConnector $vectorConnector,
        array $embeddings = [[1.0, 2.0], [1.0, 2.0]],
        int $embeddingDimension = 2,
        ChunkingStrategy $strategy = ChunkingStrategy::Fixed,
    ): array
    {
        $aiConnector = new class($embeddings) implements AiConnectorInterface {
            public function __construct(private readonly array $embeddings) {}
            public function getIdentifier(): string { return 'test-ai'; }
            public function chat(AiConnectionConfigurationInterface $connection, AiRequest $request): AiResponse { return new AiResponse(); }
            public function streamChat(AiConnectionConfigurationInterface $connection, AiRequest $request, callable $onData): void {}
            public function embed(AiConnectionConfigurationInterface $connection, EmbeddingRequest $request): EmbeddingResponse { return new EmbeddingResponse($this->embeddings[0] ?? []); }
            public function embedBatch(AiConnectionConfigurationInterface $connection, array $requests): array
            {
                foreach ($requests as $request) {
                    \PHPUnit\Framework\Assert::assertSame(
                        EmbeddingPurpose::RetrievalDocument,
                        $request->getPurpose(),
                    );
                }
                return array_map(
                    fn (int $index): EmbeddingResponse => new EmbeddingResponse($this->embeddings[$index] ?? []),
                    array_keys($requests),
                );
            }
        };
        $aiConnection = new AiConnectionConfiguration(
            apiKey: 'secret',
            connectorIdentifier: 'test-ai',
            embeddingDimension: $embeddingDimension,
        );
        $vectorConnection = new VectorStoreConnectionConfiguration(
            endpoint: 'https://vector.test',
            connectorIdentifier: 'test-vector',
            distance: 'Dot',
        );
        $configuration = new class($aiConnection, $vectorConnection, $strategy) implements IndexingConfigurationInterface {
            public function __construct(
                private AiConnectionConfigurationInterface $ai,
                private VectorStoreConnectionConfigurationInterface $vector,
                private ChunkingStrategy $strategy,
            ) {}
            public function getCollection(): string { return 'documents'; }
            public function getAiConnection(): ?AiConnectionConfigurationInterface { return $this->ai; }
            public function getVectorStoreConnection(): ?VectorStoreConnectionConfigurationInterface { return $this->vector; }
            public function getChunkSize(): int { return 5; }
            public function getChunkingStrategy(): ChunkingStrategy { return $this->strategy; }
            public function getChunkOverlap(): int { return 1; }
            public function getMaxChunks(): int { return 0; }
            public function getMinChunkChars(): int { return 1; }
        };
        $identity = new SourceIdentityGenerator();
        $indexer = new VectorDocumentIndexer(
            new AiConnectorResolver([$aiConnector]),
            new VectorStoreConnectorResolver([$vectorConnector]),
            new TextChunker(),
            $identity,
        );
        $document = new IndexableDocument('abcdefgh', new DocumentMetadata('page', '42', languageId: 1));

        return [$indexer, $configuration, $document, $identity];
    }
}
