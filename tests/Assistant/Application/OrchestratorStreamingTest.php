<?php
declare(strict_types=1);

/*
 * This file is part of madj2k/ai-core.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Madj2k\AiCore\Tests\Assistant\Application;

use Madj2k\AiCore\Assistant\Application\Orchestrator;
use Madj2k\AiCore\Assistant\Configuration\AssistantConfigurationInterface;
use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContextFactory;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\ContextFactory;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult;
use Madj2k\AiCore\Assistant\DTO\AssistantRequest;
use Madj2k\AiCore\Assistant\DTO\ChatOptions;
use Madj2k\AiCore\Assistant\DTO\LastRetrievalResult;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Memory\MemoryInterface;
use Madj2k\AiCore\Assistant\Pipeline\Pipeline;
use Madj2k\AiCore\Assistant\Pipeline\PipelineValidator;
use Madj2k\AiCore\Assistant\Pipeline\Processor\ProcessorInterface;
use Madj2k\AiCore\Assistant\Pipeline\Processor\ProcessorStreamingInterface;
use Madj2k\AiCore\Assistant\Pipeline\Registry\ProcessorRegistry;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\TestCase;

/**
 * Verifies consistent user-visible and persisted output for streaming pipelines.
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class OrchestratorStreamingTest extends TestCase
{
    public function testStoresTheSameFinalAnswerThatWasStreamed(): void
    {
        $answerGenerator = new class implements ProcessorInterface, ProcessorStreamingInterface {
            public function getIdentifier(): string { return 'test.answer'; }
            public function supports(AssistantPipelineProcessorType $type): bool { return $type === AssistantPipelineProcessorType::AnswerGenerator; }
            public function canProcess(Context $context, PipelineStepConfigurationInterface $step): bool { return true; }
            public function process(Context $context, PipelineStepConfigurationInterface $step, ?PipelineLogMetaData $logContext = null): void
            {
                $context->getAnswer()->setCandidate('Draft answer');
            }
            public function processStream(Context $context, PipelineStepConfigurationInterface $step, callable $onData, ?PipelineLogMetaData $logContext = null): void
            {
                $onData('Draft answer');
                $context->getAnswer()->setCandidate('Draft answer');
            }
        };
        $qualityGate = new class implements ProcessorInterface, ProcessorStreamingInterface {
            public function getIdentifier(): string { return 'test.quality'; }
            public function supports(AssistantPipelineProcessorType $type): bool { return $type === AssistantPipelineProcessorType::QualityGate; }
            public function canProcess(Context $context, PipelineStepConfigurationInterface $step): bool { return true; }
            public function process(Context $context, PipelineStepConfigurationInterface $step, ?PipelineLogMetaData $logContext = null): void
            {
                $context->getAnswer()->setFinal('Final answer');
            }
            public function processStream(Context $context, PipelineStepConfigurationInterface $step, callable $onData, ?PipelineLogMetaData $logContext = null): void
            {
                $onData('Final ');
                $onData('answer');
                $context->getAnswer()->setFinal('Final answer');
            }
        };
        $steps = [
            new PipelineStep(identifier: 'test.answer'),
            new PipelineStep(type: AssistantPipelineProcessorType::QualityGate, identifier: 'test.quality'),
        ];
        $profile = new class($steps) implements AssistantConfigurationInterface {
            public function __construct(private readonly array $steps) {}
            public function getUid(): ?int { return 1; }
            public function getTitle(): string { return 'Test profile'; }
            public function getAssistantName(): string { return 'Test assistant'; }
            public function getIdentityPrompt(): string { return ''; }
            public function getBehaviorRules(): string { return ''; }
            public function getRetrievalRules(): string { return ''; }
            public function getOutputRules(): string { return ''; }
            public function getAiConnection(): ?AiConnectionConfigurationInterface { return null; }
            public function getVectorStoreConnection(): ?VectorStoreConnectionConfigurationInterface { return null; }
            public function getChatPipelineSteps(): iterable { return $this->steps; }
        };
        $memoryState = (object)['messages' => []];
        $memory = new class($memoryState) implements MemoryInterface {
            public function __construct(private readonly \stdClass $state) {}
            public function start(string $chatIdentifier, int $chatStartTimestamp): void {}
            public function getMessages(string $chatIdentifier): array { return $this->state->messages; }
            public function addMessage(string $chatIdentifier, string $role, string $content): void
            {
                $this->state->messages[] = ['role' => $role, 'content' => $content];
            }
            public function setLastRetrievalResult(string $chatIdentifier, RetrievalResult $retrievalResult): ?LastRetrievalResult { return null; }
            public function getLastRetrievalResult(string $chatIdentifier): ?LastRetrievalResult { return null; }
        };
        $logger = $this->createStub(PipelineLoggerInterface::class);
        $logger->method('createMetaData')->willReturn(new PipelineLogMetaData(
            'trace-id',
            'Question',
            'chat-id',
            $profile,
        ));
        $pipeline = new Pipeline(
            new ProcessorRegistry([$answerGenerator, $qualityGate]),
            new PipelineValidator(),
            $logger,
        );
        $orchestrator = new Orchestrator(
            new ContextFactory(new AssistantContextFactory()),
            $pipeline,
            $logger,
            $memory,
            new AiConnectorResolver([]),
        );
        $chunks = [];

        $response = $orchestrator->handleStream(
            new AssistantRequest('Question', 1, $profile, 'chat-id', null, new ChatOptions()),
            static function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
        );

        self::assertSame('Final answer', implode('', $chunks));
        self::assertSame('Final answer', $response->answer);
        self::assertSame([
            ['role' => 'user', 'content' => 'Question'],
            ['role' => 'assistant', 'content' => 'Final answer'],
        ], $memoryState->messages);
    }
}
