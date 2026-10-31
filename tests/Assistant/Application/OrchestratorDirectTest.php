<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant\Application;

use Madj2k\AiCore\Assistant\Application\Orchestrator;
use Madj2k\AiCore\Assistant\Configuration\AssistantConfigurationInterface;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContextFactory;
use Madj2k\AiCore\Assistant\Context\ContextFactory;
use Madj2k\AiCore\Assistant\DTO\AssistantRequest;
use Madj2k\AiCore\Assistant\DTO\ChatOptions;
use Madj2k\AiCore\Assistant\DTO\DirectInteraction;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Memory\MemoryInterface;
use Madj2k\AiCore\Assistant\Pipeline\Pipeline;
use Madj2k\AiCore\Assistant\Pipeline\PipelineValidator;
use Madj2k\AiCore\Assistant\Pipeline\Registry\ProcessorRegistry;
use Madj2k\AiCore\Connection\Ai\AiConnectorInterface;
use Madj2k\AiCore\Connection\Ai\DTO\AiRequest;
use Madj2k\AiCore\Connection\Ai\DTO\AiResponse;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use PHPUnit\Framework\TestCase;

/**
 * Class OrchestratorDirectTest
 *
 * Verifies direct provider interactions without pipeline or vector-store access.
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class OrchestratorDirectTest extends TestCase
{
    public function testDirectInteractionUsesDefaultModelWithoutPipelineOrMemory(): void
    {
        $connection = $this->createStub(AiConnectionConfigurationInterface::class);
        $connection->method('getConnectorIdentifier')->willReturn('test.ai');
        $connection->method('getDefaultModel')->willReturn('configured-model');
        $connection->method('getDefaultTemperature')->willReturn(0.2);

        $profile = $this->createStub(AssistantConfigurationInterface::class);
        $profile->method('getUid')->willReturn(1);
        $profile->method('getAiConnection')->willReturn($connection);
        $profile->method('getChatPipelineSteps')->willThrowException(
            new \RuntimeException('The configured pipeline must not be accessed.'),
        );

        $recordedRequest = null;
        $connector = $this->createMock(AiConnectorInterface::class);
        $connector->method('getIdentifier')->willReturn('test.ai');
        $connector->expects(self::once())
            ->method('chat')
            ->willReturnCallback(static function (
                AiConnectionConfigurationInterface $usedConnection,
                AiRequest $request,
            ) use ($connection, &$recordedRequest): AiResponse {
                self::assertSame($connection, $usedConnection);
                $recordedRequest = $request;

                return new AiResponse('سأتحدث معك الآن باللغة العربية.');
            });

        $logger = $this->createStub(PipelineLoggerInterface::class);
        $logger->method('createMetaData')->willReturn(new PipelineLogMetaData(
            'trace-id',
            'العربية (ar)',
            'chat-id',
            $profile,
            'direct',
        ));
        $memory = $this->createMock(MemoryInterface::class);
        $memory->expects(self::never())->method('addMessage');
        $pipeline = new Pipeline(
            new ProcessorRegistry([]),
            new PipelineValidator(),
            $logger,
        );
        $orchestrator = new Orchestrator(
            new ContextFactory(new AssistantContextFactory()),
            $pipeline,
            $logger,
            $memory,
            new AiConnectorResolver([$connector]),
        );

        $response = $orchestrator->handleDirect(
            new AssistantRequest(
                'العربية (ar)',
                1,
                $profile,
                'chat-id',
                null,
                chatOptions: new ChatOptions(
                    responseLanguage: 'العربية (ar)',
                    languageCode: 'ar',
                    plainLanguage: true,
                ),
            ),
            new DirectInteraction('Confirm the selected response language.', 60),
        );

        self::assertSame('سأتحدث معك الآن باللغة العربية.', $response->answer);
        self::assertSame(['route' => 'direct'], $response->context);
        self::assertInstanceOf(AiRequest::class, $recordedRequest);
        self::assertSame('configured-model', $recordedRequest->getModel());
        self::assertSame(60, $recordedRequest->getMaxTokens());
        self::assertStringContainsString('Respond in this language: العربية (ar).', $recordedRequest->getMessages()[0]->getContent());
        self::assertStringContainsString('Use plain language', $recordedRequest->getMessages()[0]->getContent());
    }
}
