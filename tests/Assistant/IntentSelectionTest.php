<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant;

use Madj2k\AiCore\Assistant\Context\Answer\AnswerState;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\Presentation\PresentationRulePlan;
use Madj2k\AiCore\Assistant\Context\Request\History;
use Madj2k\AiCore\Assistant\Context\Request\Request;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalTarget;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalTargetProviderInterface;
use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;
use Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRule;
use Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRuleProviderInterface;
use Madj2k\AiCore\Assistant\Prompt\Context\Builder\IntentSelectionContextBuilder;
use Madj2k\AiCore\Assistant\Pipeline\Processor\Llm\IntentSelectionProcessor;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\TestCase;

final class IntentSelectionTest extends TestCase
{
    public function testBothPlansDefaultToAllAndSupportAllSelectionModes(): void
    {
        $retrieval = new \Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalPlan();
        $presentation = new PresentationRulePlan();
        self::assertTrue($retrieval->allows('source'));
        self::assertTrue($presentation->allows('rule'));

        foreach ([
            ['selected', ['one'], ['one', 'rule']],
            ['all_except', ['one'], ['two', 'rule']],
            ['none', [], []],
        ] as [$mode, $values, $allowed]) {
            $retrieval = new \Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalPlan($mode, $values);
            $presentation = new PresentationRulePlan($mode, $values);
            self::assertSame(in_array('one', $allowed, true), $retrieval->allows('one'));
            self::assertSame($mode === 'all_except', $presentation->allows('rule'));
        }
    }

    public function testIntentPromptDefinesContractAndListsBothAreas(): void
    {
        $targetProvider = new class implements RetrievalTargetProviderInterface {
            public function getTargets(Context $context, \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step): array
            {
                return [new RetrievalTarget('source', 'Source')];
            }
        };
        $ruleProvider = new class implements PresentationRuleProviderInterface {
            public function getAll(): array { return [new PresentationRule('rule', 'Instructions', 'Rule')]; }
            public function getRules(Context $context, \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step): array { return $this->getAll(); }
        };
        $builder = new IntentSelectionContextBuilder($targetProvider, $ruleProvider);
        $sections = $builder->build($this->context(), new PipelineStep(
            type: \Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType::IntentSelector,
        ));
        $content = $sections[0]->content;

        self::assertStringContainsString('"retrieval":{"mode":"all","targets":[]}', $content);
        self::assertStringContainsString('"presentationRules":{"mode":"all","rules":[]}', $content);
        self::assertStringContainsString('For mode all or none, the corresponding targets or rules array must be empty.', $content);
        self::assertStringContainsString('If there is no explicit reason to restrict either area, return exactly:', $content);
        self::assertStringContainsString('source', $content);
        self::assertStringContainsString('rule', $content);
    }

    public function testMalformedJsonIsRejectedWithoutASelection(): void
    {
        $method = new \ReflectionMethod(IntentSelectionProcessor::class, 'decodeSelection');
        $method->setAccessible(true);
        self::assertNull($method->invoke($this->newInstanceWithoutConstructor(), '{not-json}'));
    }

    public function testAllAndNoneDiscardExplicitValues(): void
    {
        $method = new \ReflectionMethod(IntentSelectionProcessor::class, 'selection');
        $method->setAccessible(true);
        $processor = $this->newInstanceWithoutConstructor();

        self::assertSame(['all', []], $method->invoke($processor, [
            'mode' => 'all',
            'targets' => ['unexpected'],
        ], 'targets'));
        self::assertSame(['none', []], $method->invoke($processor, [
            'mode' => 'none',
            'rules' => ['unexpected'],
        ], 'rules'));
    }

    private function newInstanceWithoutConstructor(): IntentSelectionProcessor
    {
        return (new \ReflectionClass(IntentSelectionProcessor::class))->newInstanceWithoutConstructor();
    }

    private function context(): Context
    {
        return new Context(
            new AssistantContext(), new Request('query'), new History([]),
            new RetrievalResult(), new AnswerState(), new ProcessingTrace(),
        );
    }
}
