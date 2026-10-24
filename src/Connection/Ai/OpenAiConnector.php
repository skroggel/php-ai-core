<?php
declare(strict_types=1);

/*
 * This file is part of madj2k\ai-core
 *
 * Copyright (C) 2026 Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Madj2k\AiCore\Connection\Ai;

use Madj2k\AiCore\Connection\Ai\DTO\AiRequest;
use Madj2k\AiCore\Connection\Ai\DTO\AiResponse;
use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest;
use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingResponse;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Factory\OpenAiClientFactory;
use Madj2k\AiCore\Connection\Factory\OpenAiClientFactoryInterface;
use Madj2k\AiCore\Connection\Resilience\ExceptionClassifier;
use Madj2k\AiCore\Connection\Resilience\RetryExecutor;
use Madj2k\AiCore\Connection\Resilience\RetryExhaustedException;
use Madj2k\AiCore\Connection\Resilience\RetryPolicy;
use Madj2k\AiCore\Exception\ApiException;
use OpenAI\Contracts\ClientContract;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class OpenAiConnector
 *
 * Provides OpenAI chat and embedding operations for shared usage.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class OpenAiConnector extends AbstractConnector implements AiConnectorInterface
{
    protected const DEFAULT_CHAT_MODEL = 'gpt-4o-mini';

    protected const DEFAULT_EMBEDDING_MODEL = 'text-embedding-3-small';


    /**
     * @var \Madj2k\AiCore\Connection\Factory\OpenAiClientFactoryInterface $clientFactory
     */
    protected OpenAiClientFactoryInterface $clientFactory;


    /**
     * Constructor.
     *
     * @param \Psr\Log\LoggerInterface|null $logger Logger.
     * @param \Madj2k\AiCore\Connection\Factory\OpenAiClientFactoryInterface|null $clientFactory Client factory.
     * @param \Madj2k\AiCore\Connection\Resilience\RetryPolicy|null $retryPolicy Retry and timeout policy.
     */
    public function __construct(
        ?LoggerInterface $logger = null,
        ?OpenAiClientFactoryInterface $clientFactory = null,
        ?RetryPolicy $retryPolicy = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->clientFactory = $clientFactory ?? new OpenAiClientFactory($this->logger);
        $this->retryPolicy = $retryPolicy ?? new RetryPolicy();
        $this->exceptionClassifier = new ExceptionClassifier();
        $this->retryExecutor = new RetryExecutor(
            $this->retryPolicy,
            logger: $this->logger,
        );
    }


    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'openai';
    }


    /**
     * @inheritDoc
     */
    public function embed(AiConnectionConfigurationInterface $connection, EmbeddingRequest $request): EmbeddingResponse
    {
        /** @var string $configuredModel */
        $configuredModel = $this->resolveEmbeddingModel($connection, $request);

        try {
            /** @var object $response */
            $response = $this->retryExecutor->execute(
                'openai',
                'embed',
                fn (): object => $this->createEmbeddingClient($connection)->embeddings()->create(array_merge([
                    'model' => $configuredModel,
                    'input' => $request->getText(),
                    'temperature' => $this->resolveEmbeddingTemperature($connection, $request),
                ], $connection->getAdditionalOptionsArray(), $request->getOptions())),
            );

            /** @var array<int, float> $embedding */
            $embedding = $response->embeddings[0]->embedding ?? [];

            return new EmbeddingResponse(
                $embedding,
                $configuredModel,
                method_exists($response, 'toArray') ? $response->toArray() : []
            );
        } catch (RetryExhaustedException $exception) {
            $this->logger->error('OpenAI embedding failed', [
                'operation' => 'embed',
                'configured_model' => $configuredModel,
                'exception' => $exception,
            ]);
            throw $this->createApiException('Embedding failed', $exception);
        }
    }


    /**
     * @inheritDoc
     */
    public function embedBatch(AiConnectionConfigurationInterface $connection, array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        /** @var \Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest $firstRequest */
        $firstRequest = reset($requests);

        /** @var string $configuredModel */
        $configuredModel = $this->resolveEmbeddingModel($connection, $firstRequest);

        /** @var array<int, string> $texts */
        $texts = array_map(
            static fn (EmbeddingRequest $request): string => $request->getText(),
            $requests
        );

        try {
            /** @var object $response */
            $response = $this->retryExecutor->execute(
                'openai',
                'embedBatch',
                fn (): object => $this->createEmbeddingClient($connection)->embeddings()->create(array_merge([
                    'model' => $configuredModel,
                    'input' => $texts,
                    'temperature' => $this->resolveEmbeddingTemperature($connection, $firstRequest),
                ], $connection->getAdditionalOptionsArray(), $firstRequest->getOptions())),
            );

            return $this->buildEmbeddingResponses($response, $configuredModel);
        } catch (RetryExhaustedException $exception) {
            $this->logger->error('OpenAI batch embedding failed', [
                'operation' => 'embedBatch',
                'configured_model' => $configuredModel,
                'exception' => $exception,
            ]);
            throw $this->createApiException('Batch embedding failed', $exception);
        }
    }


    /**
     * @inheritDoc
     */
    public function streamChat(AiConnectionConfigurationInterface $connection, AiRequest $request, callable $onData): void
    {
        $streamState = new class {
            public bool $emittedData = false;
        };
        try {
            /** @var array<string, mixed> $payload */
            $payload = $this->buildChatPayload($connection, $request);

            $this->retryExecutor->execute(
                'openai',
                'streamChat',
                function () use ($connection, $payload, $onData, $streamState): void {
                    /** @var iterable<object> $stream */
                    $stream = $this->createChatClient($connection)->chat()->createStreamed($payload);
                    foreach ($stream as $event) {
                        if (isset($event->choices[0]->delta->content)) {
                            $streamState->emittedData = true;
                            $onData($event->choices[0]->delta->content);
                        }
                    }
                },
                function (\Throwable $exception) use ($streamState): bool {
                    return !$streamState->emittedData
                        && $this->exceptionClassifier->isRetryable($exception, $this->retryPolicy);
                },
            );
        } catch (RetryExhaustedException $exception) {
            $this->logger->error('OpenAI chat streaming failed', [
                'operation' => 'streamChat',
                'model' => $this->resolveChatModel($connection, $request),
                'message_count' => count($request->getMessages()),
                'exception' => $exception,
            ]);
            throw $this->createApiException('Chat streaming failed', $exception);
        }
    }


    /**
     * @inheritDoc
     */
    public function chat(AiConnectionConfigurationInterface $connection, AiRequest $request): AiResponse
    {
        try {
            /** @var object $response */
            $response = $this->retryExecutor->execute(
                'openai',
                'chat',
                fn (): object => $this->createChatClient($connection)->chat()->create(
                    $this->buildChatPayload($connection, $request),
                ),
            );

            /** @var array<string, mixed> $rawResponse */
            $rawResponse = method_exists($response, 'toArray') ? $response->toArray() : [];

            return new AiResponse(
                (string)($response->choices[0]->message->content ?? ''),
                $rawResponse
            );
        } catch (RetryExhaustedException $exception) {
            $this->logger->error('OpenAI chat request failed', [
                'operation' => 'chat',
                'model' => $this->resolveChatModel($connection, $request),
                'message_count' => count($request->getMessages()),
                'exception' => $exception,
            ]);
            throw $this->createApiException('Chat request failed', $exception);
        }
    }


    /**
     * Creates an OpenAI client for the given connection.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $connection AI connection.
     * @return \OpenAI\Contracts\ClientContract OpenAI client.
     */
    protected function createClient(
        AiConnectionConfigurationInterface $connection,
        ?string $endpointOverride = null,
        ?string $baseUrlOverride = null,
    ): ClientContract
    {
        $cacheKey = sha1(implode('|', [
            $connection->getApiKey(),
            $connection->getBaseUrl(),
            $connection->getOrganization(),
            $connection->getProject(),
            method_exists($connection, 'getAuthentication') ? (string)$connection->getAuthentication() : 'api_key',
            method_exists($connection, 'getOauthTokenEndpoint') ? (string)$connection->getOauthTokenEndpoint() : '',
            method_exists($connection, 'getOauthClientId') ? (string)$connection->getOauthClientId() : '',
            trim($endpointOverride ?? ''),
            trim($baseUrlOverride ?? ''),
        ]));

        if (isset($this->clients[$cacheKey])) {
            return $this->clients[$cacheKey];
        }

        $usesOAuth = method_exists($connection, 'getAuthentication')
            && $connection->getAuthentication() === 'oauth_client_credentials';
        if ($connection->getApiKey() === '' && !$usesOAuth) {
            throw new ApiException('Missing OpenAI API key in selected AI connection.', 1780573101);
        }

        $this->clients[$cacheKey] = $this->clientFactory->create(
            $connection,
            $this->retryPolicy,
            $endpointOverride,
            $baseUrlOverride,
        );

        return $this->clients[$cacheKey];
    }

    /**
     * Creates the client used for embedding requests.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $connection AI connection.
     * @return \OpenAI\Contracts\ClientContract OpenAI client.
     */
    protected function createEmbeddingClient(AiConnectionConfigurationInterface $connection): ClientContract
    {
        $embeddingUrl = $connection->getEmbeddingBaseUrl();
        if ($embeddingUrl === '') {
            return $this->createClient(
                $connection,
                $connection->isBaseUrlNoExtend() ? $connection->getBaseUrl() : null,
            );
        }

        if ($connection->isEmbeddingBaseUrlNoExtend()) {
            return $this->createClient($connection, $embeddingUrl);
        }

        return $this->createClient($connection, null, $embeddingUrl);
    }

    /**
     * Creates the client used for chat requests.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $connection AI connection.
     * @return \OpenAI\Contracts\ClientContract OpenAI client.
     */
    protected function createChatClient(AiConnectionConfigurationInterface $connection): ClientContract
    {
        return $this->createClient(
            $connection,
            $connection->isBaseUrlNoExtend() ? $connection->getBaseUrl() : null,
        );
    }


    protected function createApiException(string $message, RetryExhaustedException $exception): ApiException
    {
        return new ApiException(
            $message . ': ' . $exception->getMessage(),
            1780572973,
            $exception->getPrevious() ?? $exception,
            $exception->getProvider(),
            $exception->getOperation(),
            $exception->getStatusCode(),
            $exception->isRetryable(),
            $exception->getAttempts(),
        );
    }


    /**
     * Builds the chat payload.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $connection AI connection.
     * @param \Madj2k\AiCore\Connection\Ai\DTO\AiRequest $request AI request.
     * @return array<string, mixed> Chat payload.
     */
    protected function buildChatPayload(AiConnectionConfigurationInterface $connection, AiRequest $request): array
    {
        return array_merge([
            'model' => $this->resolveChatModel($connection, $request),
            'messages' => $request->toMessageArray(),
            'temperature' => $this->resolveChatTemperature($connection, $request),
            'max_completion_tokens' => $request->getMaxTokens(),
        ], $connection->getAdditionalOptionsArray(), $request->getOptions());
    }


    /**
     * Resolves the chat model.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $connection AI connection.
     * @param \Madj2k\AiCore\Connection\Ai\DTO\AiRequest $request AI request.
     * @return string Chat model.
     */
    protected function resolveChatModel(AiConnectionConfigurationInterface $connection, AiRequest $request): string
    {
        if ($request->getModel() !== '') {
            return $request->getModel();
        }

        return $connection->getDefaultModel() !== ''
            ? $connection->getDefaultModel()
            : self::DEFAULT_CHAT_MODEL;
    }


    /**
     * Resolves the chat temperature.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $connection AI connection.
     * @param \Madj2k\AiCore\Connection\Ai\DTO\AiRequest $request AI request.
     * @return float Chat temperature.
     */
    protected function resolveChatTemperature(AiConnectionConfigurationInterface $connection, AiRequest $request): float
    {
        if ($request->getTemperature() !== null) {
            return $request->getTemperature();
        }

        return $connection->getDefaultTemperature();
    }


    /**
     * Resolves the embedding model.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $connection AI connection.
     * @param \Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest $request Embedding request.
     * @return string Embedding model.
     */
    protected function resolveEmbeddingModel(AiConnectionConfigurationInterface $connection, EmbeddingRequest $request): string
    {
        if ($request->getModel() !== '') {
            return $request->getModel();
        }

        return $connection->getEmbeddingModel() !== ''
            ? $connection->getEmbeddingModel()
            : self::DEFAULT_EMBEDDING_MODEL;
    }


    /**
     * Resolves the embedding temperature.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $connection AI connection.
     * @param \Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest $request Embedding request.
     * @return float Embedding temperature.
     */
    protected function resolveEmbeddingTemperature(AiConnectionConfigurationInterface $connection, EmbeddingRequest $request): float
    {
        if ($request->getTemperature() !== null) {
            return $request->getTemperature();
        }

        return $connection->getEmbeddingTemperature();
    }


    /**
     * Builds embedding response DTOs.
     *
     * @param object $response Response object.
     * @param string $model Model.
     * @return array<int, \Madj2k\AiCore\Connection\Ai\DTO\EmbeddingResponse> Embedding responses.
     */
    protected function buildEmbeddingResponses(object $response, string $model): array
    {
        /** @var array<int, \Madj2k\AiCore\Connection\Ai\DTO\EmbeddingResponse> $responses */
        $responses = [];

        foreach ($response->embeddings ?? [] as $embeddingData) {
            $responses[] = new EmbeddingResponse(
                $embeddingData->embedding ?? [],
                $model,
                method_exists($response, 'toArray') ? $response->toArray() : []
            );
        }

        return $responses;
    }
}
