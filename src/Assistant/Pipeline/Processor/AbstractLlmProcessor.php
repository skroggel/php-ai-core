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


namespace Madj2k\AiCore\Assistant\Pipeline\Processor;

use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Contract\ContractInterface;
use Madj2k\AiCore\Assistant\Contract\ContractRegistry;
use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Prompt\PromptBuilder;
use Madj2k\AiCore\Connection\Ai\DTO\AiMessage;
use Madj2k\AiCore\Connection\Ai\DTO\AiRequest;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Exception\AssistantException;

/**
 * Class AbstractLlmProcessor
 *
 * Base class for pipeline steps that call the LLM API.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
abstract class AbstractLlmProcessor implements ProcessorInterface
{

    /**
     * Constructor.
     *
     * @param \Madj2k\AiCore\Connection\Resolver\AiConnectorResolver $aiConnectorResolver AI connector registry.
     * @param \Madj2k\AiCore\Assistant\Prompt\PromptBuilder $promptBuilder Prompt builder.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface $pipelineLogger Pipeline logger.
     */
    public function __construct(
        protected readonly AiConnectorResolver      $aiConnectorResolver,
        protected readonly PromptBuilder            $promptBuilder,
        protected readonly ContractRegistry        $contractRegistry,
        protected readonly PipelineLoggerInterface  $pipelineLogger,
    ) {
    }


    /**
     * Executes a synchronous LLM request and returns the response text.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Context.
     * @param array<int,array{role:string,content:string}> $messages LLM messages.
     * @param PipelineStepConfigurationInterface $step Step configuration.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLogMetaData|null $logContext Optional log context.
     * @return mixed Parsed contract result.
     * @throws \Madj2k\AiCore\Exception\AppException
     * @throws \Madj2k\AiCore\Exception\AssistantException
     */
    protected function callAi(
        Context $context,
        array $messages,
        PipelineStepConfigurationInterface $step,
        ?PipelineLogMetaData $logContext = null
    ): mixed {
        $contract = $this->contractRegistry->resolveForStep($step);

        $aiConnection = $context->getAssistant()->getAiConnection();
        if ($aiConnection === null) {
            throw new AssistantException(
                'No AI connection configured for assistant profile.',
                1780666101
            );
        }

        $options = [
            'model' => $step->getModel() ?? $aiConnection->getDefaultModel(),
            'temperature' => $step->getTemperature(),
            'max_tokens' => $step->getMaxTokens(),
        ];

        if ($logContext instanceof PipelineLogMetaData) {
            $this->pipelineLogger->logLlmRequest(
                $logContext,
                $step->getTitle(),
                $step->getType()->value,
                $this->withMessageSources($messages, $step),
                $options
            );
        }

        $response = $this->aiConnectorResolver
            ->get($aiConnection->getConnectorIdentifier())
            ->chat(
                $aiConnection,
                new AiRequest(
                    messages: $this->createAiMessages($messages),
                    model: $step->getModel() ?? $aiConnection->getDefaultModel(),
                    temperature: $step->getTemperature(),
                    maxTokens: $step->getMaxTokens()
                )
            );

        $answer = trim($response->getContent());
        $parsedResponse = $contract->parse($answer, $step);

        if ($logContext instanceof PipelineLogMetaData) {
            $this->pipelineLogger->logLlmResponse(
                $logContext,
                $step->getTitle(),
                $step->getType()->value,
                is_string($parsedResponse) ? $parsedResponse : $answer,
                [
                    'usage' => $response->getUsage() ?? [],
                ]
            );
        }

        return $parsedResponse;
    }



    /**
     * Executes a streaming LLM request and returns the collected response text.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Context.
     * @param array<int,array{role:string,content:string}> $messages LLM messages.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Step configuration.
     * @param callable $onData Callback for streamed chunks.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLogMetaData|null $logContext Optional log context.
     * @return string
     * @throws \Madj2k\AiCore\Exception\AppException
     * @throws \Madj2k\AiCore\Exception\AssistantException
     */
    protected function callAiStream(
        Context $context,
        array $messages,
        PipelineStepConfigurationInterface $step,
        callable $onData,
        ?PipelineLogMetaData $logContext = null
    ): string {

        $aiConnection = $context->getAssistant()->getAiConnection();
        if ($aiConnection === null) {
            throw new AssistantException(
                'No AI connection configured for assistant profile.',
                1780666101
            );
        }

        $options = [
            'model' => $step->getModel() ?? $aiConnection->getDefaultModel(),
            'temperature' => $step->getTemperature(),
            'max_tokens' => $step->getMaxTokens(),
            'stream' => true,
        ];

        if ($logContext instanceof PipelineLogMetaData) {
            $this->pipelineLogger->logLlmRequest(
                $logContext,
                $step->getTitle(),
                $step->getType()->value,
                $this->withMessageSources($messages, $step),
                $options
            );
        }


        /** @var string $answer */
        $answer = '';

        $this->aiConnectorResolver
            ->get($aiConnection->getConnectorIdentifier())
            ->streamChat(
                $aiConnection,
                new AiRequest(
                    messages: $this->createAiMessages($messages),
                    model: $step->getModel() ?? $aiConnection->getDefaultModel(),
                    temperature: $step->getTemperature(),
                    maxTokens: $step->getMaxTokens()
                ),
                static function (string $chunk) use (&$answer, $onData): void {
                    $answer .= $chunk;
                    $onData($chunk);
                }
            );

        $answer = trim($answer);

        if ($logContext instanceof PipelineLogMetaData) {
            $this->pipelineLogger->logLlmResponse(
                $logContext,
                $step->getTitle(),
                $step->getType()->value,
                $answer,
                [
                    'usage' => [],
                    'streamed' => true,
                ]
            );
        }

        return $answer;
    }


    /**
     * Creates AI connector messages from plain message arrays.
     *
     * @param array<int, array{role:string, content:string}> $messages Messages.
     * @return array<int, \Madj2k\AiCore\Connection\Ai\DTO\AiMessage> AI messages.
     */
    protected function createAiMessages(array $messages): array
    {
        /** @var array<int, \Madj2k\AiCore\Connection\Ai\DTO\AiMessage> $aiMessages */
        $aiMessages = [];

        foreach ($messages as $message) {
            $aiMessages[] = new AiMessage(
                role: (string)($message['role'] ?? ''),
                content: (string)($message['content'] ?? '')
            );
        }

        return $aiMessages;
    }


    /**
     * Adds default source metadata to messages.
     *
     * @param array<int,array{role:string,content:string}> $messages Messages.
     * @param PipelineStepConfigurationInterface $step Step.
     * @return array<int,array<string,mixed>>
     */
    private function withMessageSources(array $messages, PipelineStepConfigurationInterface $step): array
    {
        $resolvedMessages = [];
        foreach ($messages as $index => $message) {
            $resolvedMessages[] = [
                'role' => (string)($message['role'] ?? ''),
                'content' => (string)($message['content'] ?? ''),
                'source' => $step->getType()->value . '.message.' . $index,
                'metadata' => [
                    'step_uid' => (int)$step->getUid(),
                    'step_title' => $step->getTitle(),
                ],
            ];
        }

        return $resolvedMessages;
    }
}
