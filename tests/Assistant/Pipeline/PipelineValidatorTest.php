<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant\Pipeline;

use Madj2k\AiCore\Assistant\Enum\AssistantPipelineFailureStrategy;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineStage;
use Madj2k\AiCore\Assistant\Pipeline\PipelineValidator;
use Madj2k\AiCore\Assistant\Pipeline\Registry\ProcessorRegistry;
use Madj2k\AiCore\Exception\AssistantException;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\TestCase;

final class PipelineValidatorTest extends TestCase
{
    public function testRequiresAnAnswerProducingStep(): void
    {
        $this->expectException(AssistantException::class);
        $this->expectExceptionMessage('needs at least one answer generator, query-optimizer or memory step');

        (new PipelineValidator())->validate([
            new PipelineStep(
                type: AssistantPipelineProcessorType::Retriever,
                stage: AssistantPipelineStage::Retrieval,
            ),
        ]);
    }

    public function testAcceptsStandardMultiPassPipeline(): void
    {
        $steps = [
            new PipelineStep(type: AssistantPipelineProcessorType::QueryOptimizer, stage: AssistantPipelineStage::PreRetrieval, uid: 1),
            new PipelineStep(type: AssistantPipelineProcessorType::Retriever, stage: AssistantPipelineStage::Retrieval, uid: 2, title: 'Initial retrieval'),
            new PipelineStep(type: AssistantPipelineProcessorType::QueryOptimizer, stage: AssistantPipelineStage::PostRetrieval, uid: 3),
            new PipelineStep(type: AssistantPipelineProcessorType::Retriever, stage: AssistantPipelineStage::Retrieval, uid: 4, title: 'Refined retrieval'),
            new PipelineStep(type: AssistantPipelineProcessorType::ContextOptimizer, stage: AssistantPipelineStage::PostRetrieval, uid: 5),
            new PipelineStep(type: AssistantPipelineProcessorType::AnswerGenerator, stage: AssistantPipelineStage::PreAnswer, uid: 6),
            new PipelineStep(type: AssistantPipelineProcessorType::QualityGate, stage: AssistantPipelineStage::PostAnswer, uid: 7),
        ];

        self::assertSame([], (new PipelineValidator())->validate($steps));
    }

    public function testAcceptsMemoryAsAnswerProducingOrRetrievalSourceStep(): void
    {
        self::assertSame([], (new PipelineValidator())->validate([
            new PipelineStep(type: AssistantPipelineProcessorType::Memory),
            new PipelineStep(
                type: AssistantPipelineProcessorType::ContextOptimizer,
                stage: AssistantPipelineStage::PostRetrieval,
            ),
        ]));
    }

    public function testRejectsQualityGateWithoutAnswerGenerator(): void
    {
        $this->expectException(AssistantException::class);
        $this->expectExceptionMessage('requires a preceding answer generator');

        (new PipelineValidator())->validate([
            new PipelineStep(
                type: AssistantPipelineProcessorType::QualityGate,
                stage: AssistantPipelineStage::PostAnswer,
            ),
        ]);
    }

    public function testRejectsContextOptimizerWithoutRetrievalSource(): void
    {
        $this->expectException(AssistantException::class);
        $this->expectExceptionMessage('requires a preceding retriever or memory step');

        (new PipelineValidator())->validate([
            new PipelineStep(
                type: AssistantPipelineProcessorType::ContextOptimizer,
                stage: AssistantPipelineStage::PostRetrieval,
            ),
            new PipelineStep(),
        ]);
    }

    public function testRejectsAnswerGeneratorAfterQualityGate(): void
    {
        $this->expectException(AssistantException::class);
        $this->expectExceptionMessage('must not run after a quality gate');

        (new PipelineValidator())->validate([
            new PipelineStep(uid: 1),
            new PipelineStep(
                type: AssistantPipelineProcessorType::QualityGate,
                stage: AssistantPipelineStage::PostAnswer,
                uid: 2,
            ),
            new PipelineStep(uid: 3),
        ]);
    }

    public function testRejectsDuplicatePersistedStepIdentifiers(): void
    {
        $this->expectException(AssistantException::class);
        $this->expectExceptionMessage('duplicates pipeline step uid 42');

        (new PipelineValidator())->validate([
            new PipelineStep(uid: 42),
            new PipelineStep(uid: 42),
        ]);
    }

    public function testRejectsDuplicateRetrieverStepTitles(): void
    {
        $this->expectException(AssistantException::class);
        $this->expectExceptionMessage('duplicates retriever step title "knowledge"');

        (new PipelineValidator())->validate([
            new PipelineStep(
                type: AssistantPipelineProcessorType::Retriever,
                stage: AssistantPipelineStage::Retrieval,
                title: 'knowledge',
            ),
            new PipelineStep(
                type: AssistantPipelineProcessorType::Retriever,
                stage: AssistantPipelineStage::Retrieval,
                title: 'knowledge',
            ),
            new PipelineStep(),
        ]);
    }

    public function testRejectsEmptyRetrieverStepTitle(): void
    {
        $this->expectException(AssistantException::class);
        $this->expectExceptionMessage('must define a title that names its retrieval');

        (new PipelineValidator())->validate([
            new PipelineStep(
                type: AssistantPipelineProcessorType::Retriever,
                stage: AssistantPipelineStage::Retrieval,
                title: '',
            ),
            new PipelineStep(),
        ]);
    }

    public function testRejectsUnknownProcessorBeforeExecution(): void
    {
        $this->expectException(AssistantException::class);
        $this->expectExceptionMessage('No chat pipeline processor registered for identifier "missing.processor"');

        (new PipelineValidator())->validate(
            [new PipelineStep(identifier: 'missing.processor')],
            new ProcessorRegistry([]),
        );
    }

    public function testWarnsAboutStageFailureStrategyAndMultipleAnswerSteps(): void
    {
        $warnings = (new PipelineValidator())->validate([
            new PipelineStep(
                stage: AssistantPipelineStage::Retrieval,
                failureStrategy: AssistantPipelineFailureStrategy::Continue,
            ),
            new PipelineStep(),
        ]);

        self::assertCount(3, $warnings);
        self::assertStringContainsString('uses stage "retrieval"', $warnings[0]);
        self::assertStringContainsString('should normally use failure strategy "stop"', $warnings[1]);
        self::assertSame(
            'The pipeline contains multiple answer generators; only the last answer stage can stream.',
            $warnings[2],
        );
    }

    public function testWarnsAboutLegacyFallback(): void
    {
        $warnings = (new PipelineValidator())->validate([
            new PipelineStep(
                type: AssistantPipelineProcessorType::QueryOptimizer,
                stage: AssistantPipelineStage::PreRetrieval,
                failureStrategy: AssistantPipelineFailureStrategy::Fallback,
            ),
            new PipelineStep(),
        ]);

        self::assertStringContainsString('uses deprecated failure strategy "fallback"', $warnings[0]);
    }
}
