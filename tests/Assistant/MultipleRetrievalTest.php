<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant;

use Madj2k\AiCore\Assistant\Context\Retrieval\AnswerContextBuilder;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalGroup;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult;
use Madj2k\AiCore\Assistant\Context\Answer\AnswerState;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\Request\History;
use Madj2k\AiCore\Assistant\Context\Request\Request;
use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;
use Madj2k\AiCore\Assistant\DTO\RetrievalDocument;
use Madj2k\AiCore\Assistant\DTO\LastRetrievalResult;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Prompt\Context\Builder\RetrievalContextBuilder;
use Madj2k\AiCore\DTO\DocumentMetadata;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\TestCase;

final class MultipleRetrievalTest extends TestCase
{
    public function testAppendsNamedRetrievalsAndExposesDocuments(): void
    {
        $state = new RetrievalResult();
        $state->storeGroup($this->group('search', 'Search result'));
        $state->storeGroup($this->group('knowledge', 'Knowledge result'));

        self::assertSame(['search', 'knowledge'], array_map(
            static fn (RetrievalGroup $group): string => $group->identifier,
            $state->getGroups(),
        ));
        self::assertSame(['Search result', 'Knowledge result'], array_map(
            static fn (RetrievalDocument $document): string => $document->text,
            $state->getDocuments(),
        ));

    }

    public function testRejectsDuplicateRetrievalNames(): void
    {
        $state = new RetrievalResult();
        $state->storeGroup($this->group('search', 'First result'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Retrieval name "search" is already present');

        $state->storeGroup($this->group('search', 'Second result'));
    }

    public function testEachRetrievalGroupUsesItsOwnCharacterBudget(): void
    {
        $builder = new AnswerContextBuilder();
        $short = $this->group('short', '12345', 20);
        $tooSmall = $this->group('small', '12345', 4);

        self::assertStringContainsString('12345', $builder->buildGroup($short));
        self::assertSame('', $builder->buildGroup($tooSmall));
    }

    public function testPromptContextKeepsRetrieverStepTitlesVisible(): void
    {
        $retrieval = new RetrievalResult();
        $retrieval->storeGroup($this->group('search-results', 'Search'));
        $retrieval->storeGroup($this->group('qdrant', 'Knowledge'));
        $context = new Context(
            new AssistantContext(),
            new Request('query'),
            new History([]),
            $retrieval,
            new AnswerState(),
            new ProcessingTrace(),
        );

        $sections = (new RetrievalContextBuilder(new AnswerContextBuilder()))->build(
            $context,
            new PipelineStep(
                type: AssistantPipelineProcessorType::ContextOptimizer,
                maxContextCharacters: 10000,
            ),
        );

        self::assertCount(1, $sections);
        self::assertStringContainsString('----- BEGIN RETRIEVAL: search-results -----', $sections[0]->content);
        self::assertStringContainsString('----- BEGIN RETRIEVAL: qdrant -----', $sections[0]->content);
    }

    public function testGlobalLimitSharesSpaceAcrossRetrievals(): void
    {
        $retrieval = new RetrievalResult();
        $retrieval->storeGroup($this->group('first', str_repeat('A', 100)));
        $retrieval->storeGroup($this->group('second', str_repeat('B', 100)));
        $context = new Context(
            new AssistantContext(),
            new Request('query'),
            new History([]),
            $retrieval,
            new AnswerState(),
            new ProcessingTrace(),
        );

        $sections = (new RetrievalContextBuilder(new AnswerContextBuilder()))->build(
            $context,
            new PipelineStep(
                type: AssistantPipelineProcessorType::ContextOptimizer,
                maxContextCharacters: 500,
            ),
        );

        self::assertStringContainsString('----- BEGIN RETRIEVAL: first -----', $sections[0]->content);
        self::assertStringContainsString('----- BEGIN RETRIEVAL: second -----', $sections[0]->content);
        self::assertLessThanOrEqual(500, strlen($sections[0]->content));
    }

    public function testRetrievalGroupsSurviveMemorySerialization(): void
    {
        $snapshot = new LastRetrievalResult(groups: [
            $this->group('search-results', 'Search'),
            $this->group('qdrant', 'Knowledge'),
        ]);
        $restored = new LastRetrievalResult(groups: $snapshot->toArray()['groups']);

        self::assertSame(['search-results', 'qdrant'], array_map(
            static fn (RetrievalGroup $group): string => $group->identifier,
            $restored->groups,
        ));
        self::assertSame('Knowledge', $restored->groups[1]->documents[0]->text);
    }

    private function group(string $identifier, string $text, int $characters = 1000): RetrievalGroup
    {
        return new RetrievalGroup(
            identifier: $identifier,
            processorIdentifier: 'test.retriever',
            query: 'query',
            documents: [new RetrievalDocument(
                id: $identifier,
                score: 1.0,
                text: $text,
                documentMetadata: new DocumentMetadata(),
            )],
            maxContextCharacters: $characters,
        );
    }
}
