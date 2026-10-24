<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Connection;

use GuzzleHttp\Psr7\Response;
use Madj2k\AiCore\Connection\Ai\DTO\AiMessage;
use Madj2k\AiCore\Connection\Ai\DTO\AiRequest;
use Madj2k\AiCore\Connection\Ai\OpenAiConnector;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfiguration;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Factory\OpenAiClientFactoryInterface;
use Madj2k\AiCore\Connection\Resilience\RetryPolicy;
use Madj2k\AiCore\Exception\ApiException;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Chat\CreateStreamedResponse;
use OpenAI\Responses\StreamResponse;
use OpenAI\Testing\ClientFake;
use PHPUnit\Framework\TestCase;

final class OpenAiConnectorTest extends TestCase
{
    public function testUsesInjectedClientAndRetriesRateLimit(): void
    {
        $client = new ClientFake([
            new ErrorException(['message' => 'slow down', 'type' => 'rate_limit', 'code' => 429], 429),
            CreateResponse::fake(),
        ]);
        $factory = $this->factoryFor($client);
        $policy = new RetryPolicy(maxAttempts: 2, initialDelayMilliseconds: 0);
        $connector = new OpenAiConnector(
            clientFactory: $factory,
            retryPolicy: $policy,
        );

        $response = $connector->chat($this->connection(), $this->request());

        self::assertStringContainsString('fake chat response', $response->getContent());
        $client->assertSent(\OpenAI\Resources\Chat::class, 2);
    }

    public function testExposesStructuredProviderError(): void
    {
        $client = new ClientFake([
            new ErrorException(['message' => 'unavailable', 'type' => 'server_error', 'code' => 503], 503),
            new ErrorException(['message' => 'unavailable', 'type' => 'server_error', 'code' => 503], 503),
        ]);
        $policy = new RetryPolicy(maxAttempts: 2, initialDelayMilliseconds: 0);
        $connector = new OpenAiConnector(
            clientFactory: $this->factoryFor($client),
            retryPolicy: $policy,
        );

        try {
            $connector->chat($this->connection(), $this->request());
            self::fail('Expected API exception was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame('openai', $exception->getProvider());
            self::assertSame('chat', $exception->getOperation());
            self::assertSame(503, $exception->getStatusCode());
            self::assertSame(2, $exception->getAttempts());
            self::assertTrue($exception->isRetryable());
        }
    }

    public function testStreamsThroughInjectedClient(): void
    {
        $body = <<<'SSE'
data: {"id":"chatcmpl-test","object":"chat.completion.chunk","created":1,"model":"test","choices":[{"delta":{"content":"Hallo"},"index":0,"finish_reason":null}]}
data: {"id":"chatcmpl-test","object":"chat.completion.chunk","created":1,"model":"test","choices":[{"delta":{"content":" Welt"},"index":0,"finish_reason":null}]}
data: [DONE]

SSE;
        $stream = new StreamResponse(
            CreateStreamedResponse::class,
            new Response(200, ['Content-Type' => 'text/event-stream'], $body),
        );
        $client = new ClientFake([$stream]);
        $connector = new OpenAiConnector(clientFactory: $this->factoryFor($client));
        $chunks = [];

        $connector->streamChat($this->connection(), $this->request(), static function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        });

        self::assertSame(['Hallo', ' Welt'], $chunks);
    }

    public function testDoesNotRetryStreamAfterDataWasEmitted(): void
    {
        $body = <<<'SSE'
data: {"id":"chatcmpl-test","object":"chat.completion.chunk","created":1,"model":"test","choices":[{"delta":{"content":"Einmal"},"index":0,"finish_reason":null}]}
data: {"error":{"message":"rate limited","type":"rate_limit","code":429}}

SSE;
        $failingStream = new StreamResponse(
            CreateStreamedResponse::class,
            new Response(429, ['Content-Type' => 'text/event-stream'], $body),
        );
        $client = new ClientFake([$failingStream, CreateResponse::fake()]);
        $policy = new RetryPolicy(maxAttempts: 3, initialDelayMilliseconds: 0);
        $connector = new OpenAiConnector(
            clientFactory: $this->factoryFor($client),
            retryPolicy: $policy,
        );
        $chunks = [];

        try {
            $connector->streamChat(
                $this->connection(),
                $this->request(),
                static function (string $chunk) use (&$chunks): void {
                    $chunks[] = $chunk;
                },
            );
            self::fail('Expected streaming exception was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame(['Einmal'], $chunks);
            self::assertSame(1, $exception->getAttempts());
            self::assertSame(429, $exception->getStatusCode());
            $client->assertSent(\OpenAI\Resources\Chat::class, 1);
        }
    }

    private function connection(): AiConnectionConfiguration
    {
        return new AiConnectionConfiguration(apiKey: 'test-key');
    }

    private function request(): AiRequest
    {
        return new AiRequest([new AiMessage('user', 'Hallo')]);
    }

    private function factoryFor(ClientContract $client): OpenAiClientFactoryInterface
    {
        return new class($client) implements OpenAiClientFactoryInterface {
            public function __construct(private readonly ClientContract $client) {}

            public function create(
                AiConnectionConfigurationInterface $connection,
                RetryPolicy $policy,
                ?string $endpointOverride = null,
                ?string $baseUrlOverride = null,
            ): ClientContract {
                return $this->client;
            }
        };
    }
}
