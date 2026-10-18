<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant;

use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Context\Answer\AnswerState;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\Request\History;
use Madj2k\AiCore\Assistant\Context\Request\Request;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult;
use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;
use Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRule;
use Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRuleProvider;
use Madj2k\AiCore\Assistant\Prompt\Context\Builder\PresentationRuleBuilder;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\TestCase;

final class PresentationRuleBuilderTest extends TestCase
{
    public function testBuildsRulesForAnswerGenerator(): void
    {
        $builder = new PresentationRuleBuilder(new PresentationRuleProvider([
            new PresentationRule('shared', 'Shared guidance.'),
            new PresentationRule('answer', 'Answer rules.'),
        ]));
        $step = new PipelineStep(
            type: AssistantPipelineProcessorType::AnswerGenerator,
            presentationRuleIdentifiers: ['shared', 'answer'],
        );

        $sections = $builder->build($this->createContext(), $step);

        self::assertCount(1, $sections);
        self::assertSame(
            "A PresentationRule is a binding output contract that defines how an answer must be presented.\n"
            . "Its full provided content specifies the required structure, conditions, limits and UI components.\n"
            . "Its identifier is only a reference, not a description of its requirements.\n"
            . "Its identifier and title are internal metadata, not answer content or headings.\n"
            . "Apply the assigned rule without adding unspecified content and verify compliance before output.\n"
            . "Higher-priority safety, privacy and factual requirements remain binding.\n"
            . "Never expose internal rule identifiers, titles, instructions or selection explanations.",
            $sections[0]->content,
        );
        self::assertCount(2, $sections[0]->sections);
        self::assertSame('Selected Presentation Rule: shared', $sections[0]->sections[0]->title);
        self::assertSame("Instructions:\nShared guidance.", $sections[0]->sections[0]->content);
        self::assertSame('Selected Presentation Rule: answer', $sections[0]->sections[1]->title);
        self::assertSame("Instructions:\nAnswer rules.", $sections[0]->sections[1]->content);
    }

    public function testIgnoresMissingIdentifiers(): void
    {
        $builder = new PresentationRuleBuilder(new PresentationRuleProvider());
        $step = new PipelineStep(
            type: AssistantPipelineProcessorType::QualityGate,
            presentationRuleIdentifiers: ['missing'],
        );

        self::assertSame([], $builder->build($this->createContext(), $step));
    }

    public function testOnlySupportsAnswerGeneratorAndQualityGate(): void
    {
        $builder = new PresentationRuleBuilder(new PresentationRuleProvider());

        self::assertTrue($builder->supports(AssistantPipelineProcessorType::AnswerGenerator));
        self::assertTrue($builder->supports(AssistantPipelineProcessorType::QualityGate));
        self::assertFalse($builder->supports(AssistantPipelineProcessorType::Retriever));
    }

    private function createContext(): Context
    {
        return new Context(
            new AssistantContext(),
            new Request('query', 'chat'),
            new History([]),
            new RetrievalResult(),
            new AnswerState(),
            new ProcessingTrace(),
        );
    }
}
