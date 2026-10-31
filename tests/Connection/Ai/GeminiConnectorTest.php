<?php
declare(strict_types=1);

/*
 * This file is part of madj2k/ai-core.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Madj2k\AiCore\Tests\Connection\Ai;

use ArrayObject;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Madj2k\AiCore\Connection\Ai\DTO\AiMessage;
use Madj2k\AiCore\Connection\Ai\DTO\AiRequest;
use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest;
use Madj2k\AiCore\Connection\Ai\GeminiConnector;
use Madj2k\AiCore\Connection\Ai\Enum\EmbeddingPurpose;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfiguration;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Factory\GeminiClientFactoryInterface;
use Madj2k\AiCore\Connection\Resilience\RetryPolicy;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class GeminiConnectorTest
 *
 * Verifies Gemini payload mapping, streaming, embeddings and retry handling.
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class GeminiConnectorTest extends TestCase
{
    /**
     * @throws \JsonException If a fixture or recorded request contains malformed JSON.
     * @throws \Madj2k\AiCore\Exception\ApiException If the mocked Gemini request fails.
     */
    public function testMapsChatRequestAndNormalizesResponse(): void
    {
        /** @var \ArrayObject<int, array<mixed>> $history */
        $history = new ArrayObject();
        $connector = $this->createConnector([
            new Response(200, [], json_encode([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => 'Hallo'], ['text' => ' Welt']],
                    ],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 3,
                    'candidatesTokenCount' => 2,
                    'totalTokenCount' => 5,
                ],
            ], JSON_THROW_ON_ERROR)),
        ], $history);
        $request = new AiRequest([
            new AiMessage('system', 'Sei knapp.'),
            new AiMessage('user', 'Hallo?'),
            new AiMessage('assistant', 'Vorherige Antwort'),
        ], temperature: 0.4, maxTokens: 250);

        $response = $connector->chat($this->connection([
            'chat' => ['generationConfig' => ['topP' => 0.8]],
            'embedding' => ['outputDimensionality' => 1536],
        ]), $request);

        self::assertSame('Hallo Welt', $response->getContent());
        self::assertSame(3, $response->getPromptTokens());
        self::assertSame(2, $response->getCompletionTokens());
        self::assertSame(5, $response->getTotalTokens());
        self::assertCount(1, $history);
        self::assertSame(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent',
            (string)$this->historyRequest($history, 0)->getUri(),
        );
        self::assertSame('test-key', $this->historyRequest($history, 0)->getHeaderLine('x-goog-api-key'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode(
            (string)$this->historyRequest($history, 0)->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame([['text' => 'Sei knapp.']], $payload['systemInstruction']['parts']);
        self::assertSame('user', $payload['contents'][0]['role']);
        self::assertSame('model', $payload['contents'][1]['role']);
        self::assertSame(0.4, $payload['generationConfig']['temperature']);
        self::assertSame(250, $payload['generationConfig']['maxOutputTokens']);
        self::assertSame(0.8, $payload['generationConfig']['topP']);
        self::assertArrayNotHasKey('outputDimensionality', $payload);
    }

    /**
     * @throws \Madj2k\AiCore\Exception\ApiException If the mocked Gemini stream fails.
     */
    public function testStreamsGeneratedContent(): void
    {
        /** @var \ArrayObject<int, array<mixed>> $history */
        $history = new ArrayObject();
        $body = <<<'SSE'
data: {"candidates":[{"content":{"parts":[{"text":"Hallo"}]}}]}

data: {"candidates":[{"content":{"parts":[{"text":" Welt"}]}}]}

SSE;
        $connector = $this->createConnector([
            new Response(200, ['Content-Type' => 'text/event-stream'], $body),
        ], $history);
        $chunks = [];

        $connector->streamChat(
            $this->connection(),
            new AiRequest([new AiMessage('user', 'Hallo?')]),
            static function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
        );

        self::assertSame(['Hallo', ' Welt'], $chunks);
        self::assertStringEndsWith(
            ':streamGenerateContent?alt=sse',
            (string)$this->historyRequest($history, 0)->getUri(),
        );
    }

    /**
     * @throws \Madj2k\AiCore\Exception\ApiException If the mocked Gemini stream fails.
     */
    public function testReadsStreamIncrementally(): void
    {
        /** @var \ArrayObject<int, array<mixed>> $history */
        $history = new ArrayObject();
        $stream = new class(Utils::streamFor(
            "data: {\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Hallo\"}]}}]}\n\n",
        )) implements StreamInterface {
            use StreamDecoratorTrait;

            private StreamInterface $stream;

            /** @var array<int, int> */
            public array $readLengths = [];

            public function read($length): string
            {
                $this->readLengths[] = $length;
                return $this->stream->read($length);
            }
        };
        $connector = $this->createConnector([
            new Response(200, ['Content-Type' => 'text/event-stream'], $stream),
        ], $history);

        $connector->streamChat(
            $this->connection(),
            new AiRequest([new AiMessage('user', 'Hallo?')]),
            static function (string $chunk): void {},
        );

        self::assertNotEmpty($stream->readLengths);
        self::assertSame([1], array_values(array_unique($stream->readLengths)));
    }

    /**
     * @throws \JsonException If the recorded batch request contains malformed JSON.
     * @throws \Madj2k\AiCore\Exception\ApiException If a mocked Gemini embedding request fails.
     */
    public function testCreatesSingleAndBatchEmbeddings(): void
    {
        /** @var \ArrayObject<int, array<mixed>> $history */
        $history = new ArrayObject();
        $connector = $this->createConnector([
            new Response(200, [], '{"embedding":{"values":[0.1,0.2]}}'),
            new Response(200, [], '{"embeddings":[{"values":[0.3]},{"values":[0.4]}]}'),
        ], $history);
        $connection = $this->connection([
            'chat' => ['generationConfig' => ['topP' => 0.8]],
            'embedding' => [
                'outputDimensionality' => 3072,
                'embedContentConfig' => ['autoTruncate' => true],
            ],
        ], 'gemini-embedding-001');

        $single = $connector->embed($connection, new EmbeddingRequest(
            text: 'eins',
            purpose: EmbeddingPurpose::RetrievalQuery,
        ));
        $batch = $connector->embedBatch($connection, [
            new EmbeddingRequest(text: 'zwei', purpose: EmbeddingPurpose::RetrievalDocument),
            new EmbeddingRequest(text: 'drei', purpose: EmbeddingPurpose::RetrievalDocument),
        ]);

        self::assertSame([0.1, 0.2], $single->getEmbedding());
        self::assertSame([0.3], $batch[0]->getEmbedding());
        self::assertSame([0.4], $batch[1]->getEmbedding());
        self::assertStringEndsWith(':embedContent', (string)$this->historyRequest($history, 0)->getUri());
        self::assertStringEndsWith(':batchEmbedContents', (string)$this->historyRequest($history, 1)->getUri());

        /** @var array<string, mixed> $singlePayload */
        $singlePayload = json_decode(
            (string)$this->historyRequest($history, 0)->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame(1536, $singlePayload['outputDimensionality']);
        self::assertSame(1536, $singlePayload['embedContentConfig']['outputDimensionality']);
        self::assertTrue($singlePayload['embedContentConfig']['autoTruncate']);
        self::assertSame('RETRIEVAL_QUERY', $singlePayload['taskType']);
        self::assertSame('RETRIEVAL_QUERY', $singlePayload['embedContentConfig']['taskType']);

        /** @var array<string, mixed> $batchPayload */
        $batchPayload = json_decode(
            (string)$this->historyRequest($history, 1)->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('models/gemini-embedding-001', $batchPayload['requests'][0]['model']);
        self::assertSame('zwei', $batchPayload['requests'][0]['content']['parts'][0]['text']);
        self::assertSame('drei', $batchPayload['requests'][1]['content']['parts'][0]['text']);
        self::assertSame(1536, $batchPayload['requests'][0]['outputDimensionality']);
        self::assertSame(
            1536,
            $batchPayload['requests'][0]['embedContentConfig']['outputDimensionality'],
        );
        self::assertTrue($batchPayload['requests'][0]['embedContentConfig']['autoTruncate']);
        self::assertSame(
            'RETRIEVAL_DOCUMENT',
            $batchPayload['requests'][0]['taskType'],
        );
        self::assertSame(
            'RETRIEVAL_DOCUMENT',
            $batchPayload['requests'][0]['embedContentConfig']['taskType'],
        );
        self::assertArrayNotHasKey('chat', $batchPayload['requests'][0]);
    }

    /**
     * @throws \JsonException If the recorded request contains malformed JSON.
     * @throws \Madj2k\AiCore\Exception\ApiException If the mocked Gemini embedding request fails.
     */
    public function testLeavesPurposeUnmappedForOtherEmbeddingModels(): void
    {
        /** @var \ArrayObject<int, array<mixed>> $history */
        $history = new ArrayObject();
        $connector = $this->createConnector([
            new Response(200, [], '{"embedding":{"values":[0.1,0.2]}}'),
        ], $history);

        $connector->embed(
            $this->connection(),
            new EmbeddingRequest(
                text: 'eins',
                purpose: EmbeddingPurpose::RetrievalQuery,
            ),
        );

        /** @var array<string, mixed> $payload */
        $payload = json_decode(
            (string)$this->historyRequest($history, 0)->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertArrayNotHasKey('taskType', $payload);
        self::assertArrayNotHasKey('taskType', $payload['embedContentConfig']);
    }

    /**
     * @throws \Madj2k\AiCore\Exception\ApiException If the mocked Gemini request fails after retries.
     */
    public function testUsesCustomBaseUrlAndRetriesRateLimit(): void
    {
        /** @var \ArrayObject<int, array<mixed>> $history */
        $history = new ArrayObject();
        $connector = $this->createConnector([
            new Response(429, [], '{"error":{"message":"slow down"}}'),
            new Response(200, [], '{"candidates":[{"content":{"parts":[{"text":"Okay"}]}}]}'),
        ], $history, new RetryPolicy(maxAttempts: 2, initialDelayMilliseconds: 0));
        $connection = new AiConnectionConfiguration(
            apiKey: 'test-key',
            baseUrl: 'https://gemini-proxy.example/v1/',
            defaultModel: 'models/gemini-test',
        );

        $response = $connector->chat(
            $connection,
            new AiRequest([new AiMessage('user', 'Hallo?')]),
        );

        self::assertSame('Okay', $response->getContent());
        self::assertCount(2, $history);
        self::assertStringStartsWith(
            'https://gemini-proxy.example/v1/models/gemini-test:',
            (string)$this->historyRequest($history, 1)->getUri(),
        );
    }

    /**
     * Creates a Gemini connector backed by queued HTTP responses.
     *
     * @param array<int, \Psr\Http\Message\ResponseInterface|\Throwable> $responses Queued responses.
     * @param \ArrayObject<int, array<mixed>> $history Recorded HTTP transactions.
     * @param \Madj2k\AiCore\Connection\Resilience\RetryPolicy|null $retryPolicy Retry policy.
     * @return \Madj2k\AiCore\Connection\Ai\GeminiConnector Test connector.
     */
    private function createConnector(
        array $responses,
        ArrayObject $history,
        ?RetryPolicy $retryPolicy = null,
    ): GeminiConnector {
        $handler = new MockHandler($responses);
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        $factory = new class($client) implements GeminiClientFactoryInterface {
            public function __construct(private readonly ClientInterface $client) {}

            public function create(
                AiConnectionConfigurationInterface $connection,
                RetryPolicy $policy,
            ): ClientInterface {
                return $this->client;
            }
        };

        return new GeminiConnector(
            clientFactory: $factory,
            retryPolicy: $retryPolicy,
        );
    }

    /**
     * Creates a complete Gemini test connection.
     *
     * @param array<string, mixed> $additionalOptions Provider-specific options.
     * @param string $embeddingModel Embedding model identifier.
     * @return \Madj2k\AiCore\Connection\Configuration\AiConnectionConfiguration Test connection.
     */
    private function connection(
        array $additionalOptions = [],
        string $embeddingModel = 'gemini-embedding-test',
    ): AiConnectionConfiguration {
        return new AiConnectionConfiguration(
            apiKey: 'test-key',
            defaultModel: 'gemini-test',
            embeddingModel: $embeddingModel,
            additionalOptions: $additionalOptions,
            connectorIdentifier: 'gemini',
        );
    }

    /**
     * Returns one recorded HTTP request.
     *
     * @param \ArrayObject<int, array<mixed>> $history Recorded HTTP transactions.
     * @param int $index Transaction index.
     * @return \Psr\Http\Message\RequestInterface Recorded request.
     * @throws \RuntimeException If the expected request was not recorded.
     */
    private function historyRequest(ArrayObject $history, int $index): RequestInterface
    {
        $transaction = $history[$index] ?? null;
        $request = is_array($transaction) ? ($transaction['request'] ?? null) : null;
        if (!$request instanceof RequestInterface) {
            throw new \RuntimeException('Expected HTTP request was not recorded.');
        }

        return $request;
    }
}
