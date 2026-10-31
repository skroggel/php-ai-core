<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant\Context\Retrieval;

use Madj2k\AiCore\Assistant\Context\Retrieval\AnswerContextBuilder;
use Madj2k\AiCore\Assistant\DTO\RetrievalDocument;
use Madj2k\AiCore\DTO\DocumentMetadata;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\TestCase;

final class AnswerContextBuilderTest extends TestCase
{
    public function testFormatsConfiguredMetadataAndLimitsChunks(): void
    {
        $documents = [
            $this->document('one', 'First page', 'https://example.test/first', 'First content'),
            $this->document('two', 'Second page', 'https://example.test/second', 'Second content'),
        ];
        $step = new PipelineStep(
            maxContextChunks: 1,
            maxContextCharacters: 0,
            metadataFields: ['title', 'url'],
        );

        $context = (new AnswerContextBuilder())->build($documents, $step);

        self::assertSame(
            "source:\n  title: First page\n  url: https://example.test/first\n\ncontent:\nFirst content",
            $context,
        );
        self::assertStringNotContainsString('Second page', $context);
    }

    public function testSkipsChunkThatWouldExceedCharacterLimit(): void
    {
        $step = new PipelineStep(
            maxContextChunks: 0,
            maxContextCharacters: 10,
            metadataFields: [],
        );

        self::assertSame('', (new AnswerContextBuilder())->build([
            $this->document('one', '', '', 'Content longer than ten characters'),
        ], $step));
    }

    public function testLimitsOptimizedContext(): void
    {
        $builder = new AnswerContextBuilder();

        self::assertSame('12345', $builder->limit(
            '123456789',
            new PipelineStep(maxContextCharacters: 5),
        ));
    }

    public function testTrimsIndividualChunkToConfiguredCharacterLimit(): void
    {
        $step = new PipelineStep(
            maxContextChunks: 0,
            maxContextCharacters: 0,
            maxChunkCharacters: 10,
            metadataFields: [],
        );

        self::assertSame(
            "content:\n123456789…",
            (new AnswerContextBuilder())->build([
                $this->document('one', '', '', '123456789012345'),
            ], $step),
        );
    }

    private function document(string $id, string $title, string $url, string $text): RetrievalDocument
    {
        return new RetrievalDocument(
            $id,
            0.9,
            $text,
            new DocumentMetadata('page', $id, $title, $url),
        );
    }
}
