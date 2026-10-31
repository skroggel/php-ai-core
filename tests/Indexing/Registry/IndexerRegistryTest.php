<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Indexing\Registry;

use Madj2k\AiCore\Exception\IndexingException;
use Madj2k\AiCore\Indexing\DTO\IndexingRequest;
use Madj2k\AiCore\Indexing\DTO\IndexingResult;
use Madj2k\AiCore\Indexing\Indexer\IndexerInterface;
use Madj2k\AiCore\Indexing\Registry\IndexerRegistry;
use PHPUnit\Framework\TestCase;

final class IndexerRegistryTest extends TestCase
{
    public function testResolvesIndexersAndSourceTypes(): void
    {
        $pageIndexer = $this->createIndexer('page', 'content');
        $fileIndexer = $this->createIndexer('file', 'asset');
        $registry = new IndexerRegistry([$pageIndexer, $fileIndexer]);

        self::assertSame($pageIndexer, $registry->get('page'));
        self::assertSame([$fileIndexer], $registry->findBySourceType('asset'));
        self::assertSame(['page', 'file'], $registry->getIdentifiers());
    }

    public function testRejectsDuplicateIdentifiers(): void
    {
        $this->expectException(IndexingException::class);
        new IndexerRegistry([$this->createIndexer('same', 'a'), $this->createIndexer('same', 'b')]);
    }

    private function createIndexer(string $identifier, string $sourceType): IndexerInterface
    {
        return new class($identifier, $sourceType) implements IndexerInterface {
            public function __construct(private string $identifier, private string $sourceType) {}
            public function getIdentifier(): string { return $this->identifier; }
            public function getLabel(): string { return $this->identifier; }
            public function getSourceType(): string { return $this->sourceType; }
            public function index(IndexingRequest $request): IndexingResult { return new IndexingResult(); }
        };
    }
}
