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

namespace Madj2k\AiCore\Assistant\Application;

use Madj2k\AiCore\Assistant\Context\ContextFactory;
use Madj2k\AiCore\Assistant\Configuration\AssistantConfigurationInterface;
use Madj2k\AiCore\Assistant\DTO\AssistantRequest;
use Madj2k\AiCore\Assistant\DTO\AssistantResponse;
use Madj2k\AiCore\Assistant\DTO\DirectInteraction;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Memory\MemoryInterface;
use Madj2k\AiCore\Assistant\Pipeline\Pipeline;
use Madj2k\AiCore\Assistant\Prompt\Context\Formatter;
use Madj2k\AiCore\Exception\AssistantException;
use Madj2k\AiCore\Connection\Ai\DTO\AiMessage;
use Madj2k\AiCore\Connection\Ai\DTO\AiRequest;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;

/**
 * Class Orchestrator
 *
 * Coordinates history, pipeline execution and tracing for one assistant turn.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class Orchestrator
{
    /**
     * Constructor.
     *
     * @param \Madj2k\AiCore\Assistant\Context\ContextFactory $contextFactory Runtime context factory.
     * @param \Madj2k\AiCore\Assistant\Pipeline\Pipeline $pipeline Pipeline.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface $pipelineLogger Pipeline logger.
     * @param \Madj2k\AiCore\Assistant\Memory\MemoryInterface $sessionMemory Conversation memory.
     * @param \Madj2k\AiCore\Connection\Resolver\AiConnectorResolver $aiConnectorResolver AI connector resolver for direct interactions.
     */
    public function __construct(
        private ContextFactory $contextFactory,
        private Pipeline       $pipeline,
        private PipelineLoggerInterface $pipelineLogger,
        private MemoryInterface  $sessionMemory,
        private AiConnectorResolver $aiConnectorResolver,
    ) {
    }


    /**
     * Sends an explicit lightweight interaction directly to the configured AI connector.
     *
     * No pipeline processor, retrieval or vector-store query is executed. The caller
     * remains responsible for deciding that a direct interaction is appropriate.
     *
     * @param \Madj2k\AiCore\Assistant\DTO\AssistantRequest $assistantRequest Assistant request.
     * @param \Madj2k\AiCore\Assistant\DTO\DirectInteraction $interaction Direct interaction configuration.
     * @return \Madj2k\AiCore\Assistant\DTO\AssistantResponse Direct assistant response.
     * @throws \Throwable
     */
    public function handleDirect(
        AssistantRequest $assistantRequest,
        DirectInteraction $interaction,
    ): AssistantResponse {

        $query = $this->resolveQuery($assistantRequest);
        $assistantProfile = $this->resolveAssistantProfile($assistantRequest);
        $aiConnection = $assistantProfile->getAiConnection();
        if ($aiConnection === null) {
            throw new AssistantException('No AI connection available for direct interaction.', 1780681001);
        }

        $logMetaData = $this->pipelineLogger->createMetaData($assistantRequest, 'direct');
        $messages = $this->buildDirectMessages($assistantRequest, $interaction, $query);
        $model = $aiConnection->getDefaultModel();
        $maxTokens = max(1, $interaction->maxTokens);
        $options = [
            'model' => $model,
            'temperature' => $aiConnection->getDefaultTemperature(),
            'max_tokens' => $maxTokens,
        ];

        $this->pipelineLogger->startChat($logMetaData, [
            'route' => 'direct',
            'remember' => $interaction->remember,
        ]);

        try {
            $this->pipelineLogger->logLlmRequest(
                $logMetaData,
                'Direct interaction',
                'direct',
                array_map(static fn (AiMessage $message): array => [
                    'role' => $message->getRole(),
                    'content' => $message->getContent(),
                    'source' => 'direct',
                ], $messages),
                $options,
            );

            $response = $this->aiConnectorResolver
                ->get($aiConnection->getConnectorIdentifier())
                ->chat($aiConnection, new AiRequest(
                    messages: $messages,
                    model: $model,
                    temperature: $aiConnection->getDefaultTemperature(),
                    maxTokens: $maxTokens,
                ));
            $answer = trim($response->getContent());
            if ($answer === '') {
                throw new AssistantException('Direct interaction returned an empty answer.', 1780681002);
            }

            $this->pipelineLogger->logLlmResponse(
                $logMetaData,
                'Direct interaction',
                'direct',
                $answer,
                ['usage' => $response->getUsage()],
            );

            if ($interaction->remember) {
                $this->sessionMemory->start($assistantRequest->chatIdentifier, $assistantRequest->startTimestamp);
                $this->sessionMemory->addMessage($assistantRequest->chatIdentifier, 'user', $query);
                $this->sessionMemory->addMessage($assistantRequest->chatIdentifier, 'assistant', $answer);
            }

            $this->pipelineLogger->finishChat($logMetaData, [
                'route' => 'direct',
                'answer_characters' => strlen($answer),
            ]);

            return new AssistantResponse($answer, ['route' => 'direct']);
        } catch (\Throwable $exception) {
            $this->pipelineLogger->failChat($logMetaData, [
                'route' => 'direct',
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }


    /**
     * Executes one chat turn.
     *
     * @param \Madj2k\AiCore\Assistant\DTO\AssistantRequest $assistantRequest Assistant request.
     * @return \Madj2k\AiCore\Assistant\DTO\AssistantResponse
     * @throws \Throwable
     */
    public function handle(AssistantRequest $assistantRequest): AssistantResponse
    {
        return $this->execute($assistantRequest);
    }


    /**
     * Executes one chat turn and streams chunks from the final user-visible answer stage.
     *
     * @param \Madj2k\AiCore\Assistant\DTO\AssistantRequest $assistantRequest Assistant request.
     * @param callable $onData Callback for streamed chunks.
     * @return \Madj2k\AiCore\Assistant\DTO\AssistantResponse
     * @throws \Throwable
     */
    public function handleStream(AssistantRequest $assistantRequest, callable $onData): AssistantResponse
    {
        return $this->executeStream($assistantRequest, $onData);
    }


    /**
     * Prepares one streaming assistant turn before the response stream is created.
     *
     * This keeps session/history handling in the normal controller request phase
     * and delays only the actual pipeline streaming until response emission.
     *
     * @param \Madj2k\AiCore\Assistant\DTO\AssistantRequest $assistantRequest Assistant request.
     * @param callable $onData Callback for streamed chunks.
     * @return callable
     * @throws \Throwable
     */
    public function createStreamProducer(AssistantRequest $assistantRequest, callable $onData): callable
    {
        $query = $this->resolveQuery($assistantRequest);
        $assistantProfile = $this->resolveAssistantProfile($assistantRequest);

        $logMetaData = $this->pipelineLogger->createMetaData(
            $assistantRequest,
            'pipeline',
        );

        $this->sessionMemory->start($assistantRequest->chatIdentifier, $assistantRequest->startTimestamp);

        $steps = $this->resolvePipelineSteps($assistantProfile);

        $this->pipelineLogger->startChat($logMetaData, [
            'step_count' => count($steps),
            'streaming' => true,
        ]);

        /** @var array<int,array{role:string,content:string}> $historyMessages */
        $historyMessages = $this->sessionMemory->getMessages($assistantRequest->chatIdentifier);

        $context = $this->contextFactory->create(
            $assistantRequest,
            $historyMessages
        );

        return function () use ($assistantRequest, $query, $steps, $context, $onData, $logMetaData): AssistantResponse {
            try {
                $result = $this->pipeline->runStream($context, $steps, $onData, $logMetaData);

                $this->sessionMemory->addMessage($assistantRequest->chatIdentifier, 'user', $query);
                $this->sessionMemory->addMessage($assistantRequest->chatIdentifier, 'assistant', $result->answer);

                $this->pipelineLogger->finishChat($logMetaData, [
                    'answer_characters' => strlen($result->answer),
                    'history_count' => count($this->sessionMemory->getMessages($assistantRequest->chatIdentifier)),
                    'streaming' => true,
                ]);

                return new AssistantResponse($result->answer, $result->context, $result->debug);
            } catch (\Throwable $exception) {
                $this->pipelineLogger->failChat($logMetaData, [
                    'exception_class' => get_class($exception),
                    'exception_message' => $exception->getMessage(),
                    'streaming' => true,
                ]);

                throw $exception;
            }
        };
    }


    /**
     * Executes one chat turn synchronously.
     *
     * @param \Madj2k\AiCore\Assistant\DTO\AssistantRequest $assistantRequest Assistant request.
     * @return \Madj2k\AiCore\Assistant\DTO\AssistantResponse
     * @throws \Throwable
     */
    private function execute(AssistantRequest $assistantRequest): AssistantResponse
    {
        $query = $this->resolveQuery($assistantRequest);
        $assistantProfile = $this->resolveAssistantProfile($assistantRequest);

        $logMetaData = $this->pipelineLogger->createMetaData(
            $assistantRequest,
            'pipeline',
        );

        try {
            $this->sessionMemory->start($assistantRequest->chatIdentifier, $assistantRequest->startTimestamp);

            $steps = $this->resolvePipelineSteps($assistantProfile);

            $this->pipelineLogger->startChat($logMetaData, [
                'step_count' => count($steps),
                'streaming' => false,
            ]);

            $context = $this->contextFactory->create(
                $assistantRequest,
                $this->sessionMemory->getMessages($assistantRequest->chatIdentifier)
            );

            $result = $this->pipeline->run($context, $steps, $logMetaData);

            $this->sessionMemory->addMessage($assistantRequest->chatIdentifier, 'user', $query);
            $this->sessionMemory->addMessage($assistantRequest->chatIdentifier, 'assistant', $result->answer);

            $this->pipelineLogger->finishChat($logMetaData, [
                'answer_characters' => strlen($result->answer),
                'history_count' => count($this->sessionMemory->getMessages($assistantRequest->chatIdentifier)),
                'streaming' => false,
            ]);

            return new AssistantResponse($result->answer, $result->context, $result->debug);
        } catch (\Throwable $exception) {
            $this->pipelineLogger->failChat($logMetaData, [
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'streaming' => false,
            ]);

            throw $exception;
        }
    }


    /**
     * Executes one chat turn with streaming.
     *
     * @param \Madj2k\AiCore\Assistant\DTO\AssistantRequest $assistantRequest Assistant request.
     * @param callable $onData Callback for streamed chunks.
     * @return \Madj2k\AiCore\Assistant\DTO\AssistantResponse
     * @throws \Throwable
     */
    private function executeStream(AssistantRequest $assistantRequest, callable $onData): AssistantResponse
    {
        $streamProducer = $this->createStreamProducer($assistantRequest, $onData);

        return $streamProducer();
    }


    /**
     * Resolves and validates the user query.
     *
     * @param \Madj2k\AiCore\Assistant\DTO\AssistantRequest $assistantRequest Assistant request.
     * @return string Query.
     * @throws \Madj2k\AiCore\Exception\AssistantException
     */
    private function resolveQuery(AssistantRequest $assistantRequest): string
    {
        $query = trim($assistantRequest->query);
        if ($query === '') {
            throw new AssistantException('Missing query parameter.', 1760001001);
        }

        return $query;
    }


    /**
     * Resolves and validates the assistant profile.
     *
     * @param \Madj2k\AiCore\Assistant\DTO\AssistantRequest $assistantRequest Assistant request.
     * @return \Madj2k\AiCore\Assistant\Configuration\AssistantConfigurationInterface Assistant profile.
     * @throws \Madj2k\AiCore\Exception\AssistantException
     */
    private function resolveAssistantProfile(AssistantRequest $assistantRequest): AssistantConfigurationInterface
    {
        $assistantProfile = $assistantRequest->assistantProfile;
        if (!$assistantProfile instanceof AssistantConfigurationInterface) {
            throw new AssistantException('No active assistant configuration supplied.', 1760001002);
        }

        return $assistantProfile;
    }


    /**
     * Materializes configured steps so they can be counted and iterated repeatedly.
     *
     * @return array<int, \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface>
     */
    private function resolvePipelineSteps(AssistantConfigurationInterface $assistantProfile): array
    {
        $steps = $assistantProfile->getChatPipelineSteps();

        return is_array($steps) ? array_values($steps) : iterator_to_array($steps, false);
    }


    /**
     * Builds provider messages for a direct interaction.
     *
     * @param \Madj2k\AiCore\Assistant\DTO\AssistantRequest $assistantRequest Assistant request.
     * @param \Madj2k\AiCore\Assistant\DTO\DirectInteraction $interaction Direct interaction configuration.
     * @param string $query Validated direct user input.
     * @return array<int, \Madj2k\AiCore\Connection\Ai\DTO\AiMessage>
     */
    private function buildDirectMessages(
        AssistantRequest $assistantRequest,
        DirectInteraction $interaction,
        string $query,
    ): array {
        $formatter = new Formatter();
        $systemParts = [$formatter->boundary('Direct Interaction', $interaction->instruction)];
        $chatOptions = $assistantRequest->chatOptions;

        if ($chatOptions->responseLanguage !== '') {
            $systemParts[] = $formatter->boundary('Response Language', 'Respond in this language: ' . $chatOptions->responseLanguage . '.');
        }
        if ($chatOptions->plainLanguage) {
            $systemParts[] = $formatter->boundary('Accessibility Requirements', 'Use plain language with familiar words and short sentences.');
        }
        $systemParts[] = $formatter->boundary('Output Requirements', 'Return plain text only. Do not use Markdown.');

        return [
            new AiMessage('system', implode("\n\n", array_filter($systemParts))),
            new AiMessage('user', $query),
        ];
    }
}
