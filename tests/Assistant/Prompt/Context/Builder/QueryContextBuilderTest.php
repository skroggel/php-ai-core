<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant\Prompt\Context\Builder;

use Madj2k\AiCore\Assistant\Context\Answer\AnswerState;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\Request\History;
use Madj2k\AiCore\Assistant\Context\Request\Request;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult;
use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Prompt\Context\Builder\QueryContextBuilder;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\TestCase;

final class QueryContextBuilderTest extends TestCase
{
    public function testQualityGateUsesOriginalUserQueryInsteadOfOptimizedQuery(): void
    {
        $context = new Context(
            new AssistantContext(),
            new Request('How is my pet called?', 'chat-id'),
            new History([]),
            new RetrievalResult(),
            new AnswerState(),
            new ProcessingTrace(),
        );
        $context->setCurrentQuery('Stored');

        $sections = (new QueryContextBuilder())->build(
            $context,
            new PipelineStep(type: AssistantPipelineProcessorType::QualityGate),
        );

        self::assertCount(1, $sections);
        self::assertSame('Original User Query', $sections[0]->title);
        self::assertSame('How is my pet called?', $sections[0]->content);
    }
}
