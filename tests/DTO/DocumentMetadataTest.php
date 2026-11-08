<?php

declare(strict_types=1);

namespace Madj2k\AiCore\Tests\DTO;

use Madj2k\AiCore\DTO\DocumentMetadata;
use Madj2k\AiCore\Indexing\DTO\IndexableDocument;
use Madj2k\AiCore\Indexing\Identity\SourceIdentityGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests language metadata semantics.
 */
final class DocumentMetadataTest extends TestCase
{
    /**
     * Verifies the unset language defaults.
     *
     * @return void
     */
    public function testLanguageDefaultsToUnsetValues(): void
    {
        $metadata = new DocumentMetadata();

        self::assertSame('', $metadata->getLanguage());
        self::assertSame(-1, $metadata->getLanguageId());
    }

    /**
     * Verifies ISO language and TYPO3 language ID are stored independently.
     *
     * @return void
     */
    public function testLanguageAndLanguageIdAreStoredSeparately(): void
    {
        $metadata = new DocumentMetadata(
            sourceType: 'page',
            sourceIdentifier: '42',
            sourceGroupIdentifier: 'page:42',
            language: 'de',
            languageId: 1,
        );

        self::assertSame('de', $metadata->getLanguage());
        self::assertSame(1, $metadata->getLanguageId());
        self::assertSame('de', $metadata->getValue('language'));
        self::assertSame(1, $metadata->getValue('language_id'));

        $serialized = $metadata->toArray();
        self::assertSame('de', $serialized['language']);
        self::assertSame(1, $serialized['language_id']);

        $restored = DocumentMetadata::fromArray($serialized);
        self::assertSame('de', $restored->getLanguage());
        self::assertSame(1, $restored->getLanguageId());
        self::assertSame('page:42', $restored->getSourceGroupIdentifier());
    }

    public function testDotNotationReadsNestedAdditionalMetadata(): void
    {
        $metadata = new DocumentMetadata(title: 'NFC Raw Material Portfolio');
        $metadata->addAdditional('regions', ['Europe']);
        $metadata->addAdditional('ingredients', ['Fruit ingredients']);

        self::assertSame(['Europe'], $metadata->getValue('additional.regions'));
        self::assertSame(
            ['additional.regions' => ['Europe'], 'additional.ingredients' => ['Fruit ingredients']],
            $metadata->toSelectedArray(['additional.regions', 'additional.ingredients']),
        );
    }

    public function testSingleDocumentUsesSameSourceAndGroupHash(): void
    {
        $document = new IndexableDocument('single', new DocumentMetadata('file', '/single.txt'));
        $identity = new SourceIdentityGenerator();

        self::assertSame($identity->createSourceHash($document), $identity->createSourceGroupHash($document));
    }

    public function testMultiDocumentUsesDistinctSourceHashesAndOneGroupHash(): void
    {
        $identity = new SourceIdentityGenerator();
        $firstMetadata = new DocumentMetadata('file', '/book.pdf#page-1');
        $firstMetadata->setSourceGroupIdentifier('/book.pdf');
        $secondMetadata = new DocumentMetadata('file', '/book.pdf#page-2');
        $secondMetadata->setSourceGroupIdentifier('/book.pdf');

        $first = new IndexableDocument('first', $firstMetadata);
        $second = new IndexableDocument('second', $secondMetadata);

        self::assertNotSame($identity->createSourceHash($first), $identity->createSourceHash($second));
        self::assertSame($identity->createSourceGroupHash($first), $identity->createSourceGroupHash($second));
        self::assertSame(
            $identity->createSourceGroupHash($first),
            $first->createPayload(0, 'first', $identity->createSourceHash($first))['meta']['source_group_hash'],
        );
    }
}
