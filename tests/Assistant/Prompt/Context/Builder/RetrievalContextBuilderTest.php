<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant\Prompt\Context\Builder;

use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext;
use Madj2k\AiCore\Assistant\Context\Answer\AnswerState;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\Request\History;
use Madj2k\AiCore\Assistant\Context\Request\Request;
use Madj2k\AiCore\Assistant\Context\Retrieval\AnswerContextBuilder;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult;
use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\TestCase;

final class RetrievalContextBuilderTest extends TestCase
{
    public function testStepCanExcludeRetrievalContext(): void
    {
        $retrieval = new RetrievalResult();
        $retrieval->setAnswerContext('Retrieved context');
        $context = new Context(
            new AssistantContext(),
            new Request('question', 'chat-id'),
            new History([]),
            $retrieval,
            new AnswerState(),
            new ProcessingTrace(),
        );

        $sections = (new \Madj2k\AiCore\Assistant\Prompt\Context\Builder\RetrievalContextBuilder(
            new AnswerContextBuilder(),
        ))->build(
            $context,
            new PipelineStep(
                type: AssistantPipelineProcessorType::AnswerGenerator,
                includeRetrievalContext: false,
            ),
        );

        self::assertSame([], $sections);
    }
}
