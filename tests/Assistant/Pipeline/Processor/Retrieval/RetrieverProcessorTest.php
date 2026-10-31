<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant\Pipeline\Processor\Retrieval;

use Madj2k\AiCore\Assistant\Context\Answer\AnswerState;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\Request\History;
use Madj2k\AiCore\Assistant\Context\Request\Request;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult;
use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Pipeline\Processor\Retrieval\RetrieverProcessor;
use Madj2k\AiCore\Connection\Ai\AiConnectorInterface;
use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest;
use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingResponse;
use Madj2k\AiCore\Connection\Ai\Enum\EmbeddingPurpose;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfiguration;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfiguration;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Connection\Resolver\VectorStoreConnectorResolver;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use Madj2k\AiCore\Tests\Support\RecordingVectorStoreConnector;
use PHPUnit\Framework\TestCase;

final class RetrieverProcessorTest extends TestCase
{
    public function testStepCollectionOverridesConnectionDefault(): void
    {
        $embeddingRequest = null;
        $aiConnector = $this->createStub(AiConnectorInterface::class);
        $aiConnector->method('getIdentifier')->willReturn('test-ai');
        $aiConnector->method('embed')->willReturnCallback(
            static function (mixed $connection, EmbeddingRequest $request) use (&$embeddingRequest): EmbeddingResponse {
                $embeddingRequest = $request;
                return new EmbeddingResponse([0.1, 0.2]);
            },
        );
        $vectorConnector = new RecordingVectorStoreConnector();
        $processor = new RetrieverProcessor(
            new AiConnectorResolver([$aiConnector]),
            new VectorStoreConnectorResolver([$vectorConnector]),
            $this->createStub(PipelineLoggerInterface::class),
        );
        $context = new Context(
            new AssistantContext(
                aiConnection: new AiConnectionConfiguration('', connectorIdentifier: 'test-ai'),
                vectorStoreConnection: new VectorStoreConnectionConfiguration(
                    endpoint: '',
                    connectorIdentifier: 'test-vector',
                    defaultCollection: 'default',
                    collections: ['special'],
                ),
            ),
            new Request('query'),
            new History([]),
            new RetrievalResult(),
            new AnswerState(),
            new ProcessingTrace(),
        );
        $step = new PipelineStep(
            type: AssistantPipelineProcessorType::Retriever,
            title: 'knowledge',
            retrievalCollection: 'special',
            retrievalIdentifier: '2',
        );

        $processor->process($context, $step);

        self::assertSame('special', $vectorConnector->searchRequest?->getCollection());
        self::assertSame(EmbeddingPurpose::RetrievalQuery, $embeddingRequest?->getPurpose());
        self::assertSame('special', $context->getRetrieval()->getGroups()[0]->collection);
        self::assertSame('2', $context->getRetrieval()->getGroups()[0]->identifier);
    }


    public function testStepConnectionOverridesAssistantProfileConnection(): void
    {
        $aiConnector = $this->createStub(AiConnectorInterface::class);
        $aiConnector->method('getIdentifier')->willReturn('test-ai');
        $aiConnector->method('embed')->willReturn(new EmbeddingResponse([0.1, 0.2]));
        $vectorConnector = new RecordingVectorStoreConnector();
        $processor = new RetrieverProcessor(
            new AiConnectorResolver([$aiConnector]),
            new VectorStoreConnectorResolver([$vectorConnector]),
            $this->createStub(PipelineLoggerInterface::class),
        );
        $stepConnection = new VectorStoreConnectionConfiguration(
            endpoint: 'https://step.example.test',
            connectorIdentifier: 'test-vector',
            defaultCollection: 'step-default',
        );
        $context = new Context(
            new AssistantContext(
                aiConnection: new AiConnectionConfiguration('', connectorIdentifier: 'test-ai'),
                vectorStoreConnection: new VectorStoreConnectionConfiguration(
                    endpoint: 'https://profile.example.test',
                    connectorIdentifier: 'test-vector',
                    defaultCollection: 'profile-default',
                ),
            ),
            new Request('query'),
            new History([]),
            new RetrievalResult(),
            new AnswerState(),
            new ProcessingTrace(),
        );
        $step = new PipelineStep(
            type: AssistantPipelineProcessorType::Retriever,
            title: 'step-source',
            retrievalVectorStoreConnection: $stepConnection,
        );

        $processor->process($context, $step);

        self::assertSame($stepConnection, $vectorConnector->searchConnection);
        self::assertSame('step-default', $vectorConnector->searchRequest?->getCollection());
        self::assertSame('step-default', $context->getRetrieval()->getGroups()[0]->collection);
    }
}
