<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant\Pipeline\Processor\Llm;

use Madj2k\AiCore\Assistant\Context\Answer\AnswerState;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\Request\History;
use Madj2k\AiCore\Assistant\Context\Request\Request;
use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Pipeline\Processor\Llm\QueryOptimizerProcessor;
use Madj2k\AiCore\Assistant\Contract\DecisionContract;
use Madj2k\AiCore\Assistant\Contract\ContractRegistry;
use Madj2k\AiCore\Assistant\Prompt\Context\Formatter;
use Madj2k\AiCore\Assistant\Prompt\Context\Registry\ContextBuilderRegistry;
use Madj2k\AiCore\Assistant\Prompt\PromptBuilder;
use Madj2k\AiCore\Connection\Ai\AiConnectorInterface;
use Madj2k\AiCore\Connection\Ai\DTO\AiResponse;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfiguration;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\TestCase;

/**
 * Tests the query optimizer's shared pipeline decision contract.
 */
final class QueryOptimizerProcessorTest extends TestCase
{
    public function testJsonDecisionUpdatesQueryAndRetrievalPlan(): void
    {
        $connector = $this->createStub(AiConnectorInterface::class);
        $connector->method('getIdentifier')->willReturn('test-ai');
        $connector->method('chat')->willReturn(new AiResponse(<<<'JSON'
{
  "query": "detail question",
  "retrieval": {"mode": "selected", "targets": ["detail_retrieval"]}
}
JSON));

        $processor = new QueryOptimizerProcessor(
            new AiConnectorResolver([$connector]),
            new PromptBuilder(new ContextBuilderRegistry([]), new Formatter()),
            new ContractRegistry([new DecisionContract([])]),
            $this->createStub(PipelineLoggerInterface::class),
        );
        $context = new Context(
            new AssistantContext(
                aiConnection: new AiConnectionConfiguration(
                    apiKey: 'test',
                    connectorIdentifier: 'test-ai',
                ),
            ),
            new Request('original question'),
            new History([]),
            new \Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult(),
            new AnswerState(),
            new ProcessingTrace(),
        );

        $processor->process(
            $context,
            new PipelineStep(
                type: AssistantPipelineProcessorType::QueryOptimizer,
                decisionContracts: ['retrieval'],
            ),
        );

        self::assertSame('detail question', $context->getCurrentQuery());
        self::assertSame('selected', $context->getRetrievalPlan()->mode);
        self::assertSame(['detail_retrieval'], $context->getRetrievalPlan()->targets);
    }
}
