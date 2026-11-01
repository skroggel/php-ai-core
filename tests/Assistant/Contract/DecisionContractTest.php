<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant\Contract;

use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Contract\DecisionContract;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterConjunction;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\TestCase;

/**
 * Tests the shared LLM pipeline decision parser.
 */
final class DecisionContractTest extends TestCase
{
    public function testParsesQueryRetrievalAndPresentationDecisions(): void
    {
        $step = new PipelineStep(
            type: AssistantPipelineProcessorType::IntentSelector,
            decisionContracts: ['retrieval', 'presentationRules'],
        );

        $result = (new DecisionContract($this->parts()))->parse(<<<'JSON'
{
    "query": "detail query",
    "retrieval": {"mode": "selected", "targets": ["detail"]},
    "presentationRules": {"mode": "none", "rules": []},
    "metadata": {"intent": "detail"}
}
JSON, $step);

        self::assertNotNull($result);
        self::assertSame('detail query', $result->query);
        self::assertSame('selected', $result->retrievalPlan->mode);
        self::assertSame(['detail'], $result->retrievalPlan->targets);
        self::assertSame('none', $result->presentationRulePlan->mode);
    }

    public function testUsesAllAsDefaultAndRejectsPlainTextResponses(): void
    {
        $step = new PipelineStep(type: AssistantPipelineProcessorType::QueryOptimizer);
        $parser = new DecisionContract($this->parts());

        $jsonResult = $parser->parse('{"query":"rewritten"}', $step);
        $plainTextResult = $parser->parse('rewritten', $step);

        self::assertNotNull($jsonResult);
        self::assertSame('rewritten', $jsonResult->query);
        self::assertSame('all', $jsonResult->retrievalPlan->mode);
        self::assertNull($plainTextResult);
    }

    public function testQueryOptimizerPromptContainsOnlyQueryContract(): void
    {
        $step = new PipelineStep(type: AssistantPipelineProcessorType::QueryOptimizer);

        $instructions = (new DecisionContract($this->parts()))->getPromptInstructions($step);

        self::assertStringContainsString('Return only this JSON structure: {"query":"","metadata":{}}', $instructions);
        self::assertStringNotContainsString('"retrieval"', $instructions);
        self::assertStringNotContainsString('"presentationRules"', $instructions);
        self::assertStringNotContainsString('Available retrieval targets:', $instructions);
    }

    public function testQueryOptimizerPromptIncludesExplicitlyConfiguredOptionalContracts(): void
    {
        $step = new PipelineStep(
            type: AssistantPipelineProcessorType::QueryOptimizer,
            decisionContracts: ['retrieval', 'presentationRules'],
        );

        $instructions = (new DecisionContract($this->parts()))->getPromptInstructions($step);

        self::assertStringContainsString('"query":""', $instructions);
        self::assertStringContainsString('"retrieval"', $instructions);
        self::assertStringContainsString('"presentationRules"', $instructions);
    }

    public function testIgnoresRetrievalDecisionWhenContractIsNotConfigured(): void
    {
        $step = new PipelineStep(type: AssistantPipelineProcessorType::QueryOptimizer);

        $result = (new DecisionContract($this->parts()))->parse(
            '{"query":"rewritten","retrieval":{"mode":"selected","targets":["detail"]}}',
            $step,
        );

        self::assertNotNull($result);
        self::assertSame('all', $result->retrievalPlan->mode);
        self::assertSame([], $result->retrievalPlan->targets);
    }

    public function testBuildsPayloadFilterUsingConfiguredConjunction(): void
    {
        $step = new PipelineStep(
            type: AssistantPipelineProcessorType::IntentSelector,
            decisionContracts: ['retrieverPayload'],
            decisionPayloadField: 'meta.additional.category_ids',
            decisionPayloadConjunction: FilterConjunction::Or,
        );

        $result = (new DecisionContract($this->parts()))->parse(
            '{"retrieverPayload":{"values":[12,15]}}',
            $step,
        );

        self::assertNotNull($result);
        self::assertNotNull($result->retrievalPlan->payloadFilter);
        self::assertSame(FilterConjunction::Or, $result->retrievalPlan->payloadFilter->conjunction);
        self::assertCount(2, $result->retrievalPlan->payloadFilter->conditions);
    }

    /**
     * @return array<int,\Madj2k\AiCore\Assistant\Contract\ContractInterface>
     */
    private function parts(): array
    {
        return [
            new \Madj2k\AiCore\Assistant\Contract\DecisionContract\RetrievalDecisionContract(),
            new \Madj2k\AiCore\Assistant\Contract\DecisionContract\PresentationRulesDecisionContract(),
            new \Madj2k\AiCore\Assistant\Contract\DecisionContract\RetrieverPayloadContract(),
        ];
    }
}
