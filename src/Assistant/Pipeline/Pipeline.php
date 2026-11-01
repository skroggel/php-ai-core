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

namespace Madj2k\AiCore\Assistant\Pipeline;

use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\DTO\AssistantResponse;
use Madj2k\AiCore\Assistant\DTO\RetrievalDocument;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineFailureStrategy;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Pipeline\Processor\ProcessorStreamingInterface;
use Madj2k\AiCore\Assistant\Pipeline\Registry\ProcessorRegistry;
use Madj2k\AiCore\Exception\ApiException;
use Madj2k\AiCore\Exception\VectorDatabaseException;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * Class Pipeline
 *
 * Executes configured chat steps in their sorting order.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class Pipeline
{
    /**
     * Constructor.
     *
     * @param \Madj2k\AiCore\Assistant\Pipeline\Registry\ProcessorRegistry $processorRegistry Processor registry.
     * @param \Madj2k\AiCore\Assistant\Pipeline\PipelineValidator $validator Pipeline validator.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface $pipelineLogger Logger.
     */
    public function __construct(
        private readonly ProcessorRegistry $processorRegistry,
        private readonly PipelineValidator $validator,
        private readonly PipelineLoggerInterface    $pipelineLogger,
    ) {
    }


    /**
     * Runs the pipeline and returns the final result.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Current chat context.
     * @param iterable<\Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface> $steps Configured steps.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLogMetaData|null $logContext Optional log context.
     * @return \Madj2k\AiCore\Assistant\DTO\AssistantResponse
     * @throws \Madj2k\AiCore\Exception\ApiException
     * @throws \Throwable
     */
    public function run(Context $context, iterable $steps, ?PipelineLogMetaData $logContext = null): AssistantResponse
    {
        return $this->execute($context, $steps, null, $logContext);
    }


    /**
     * Runs the pipeline and streams only the final user-visible answer stage.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Current chat context.
     * @param iterable<\Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface> $steps Configured steps.
     * @param callable $onData Callback for streamed chunks.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLogMetaData|null $logContext Optional log context.
     * @return \Madj2k\AiCore\Assistant\DTO\AssistantResponse
     * @throws \Madj2k\AiCore\Exception\ApiException
     * @throws \Throwable
     */
    public function runStream(
        Context $context,
        iterable $steps,
        callable $onData,
        ?PipelineLogMetaData $logContext = null
    ): AssistantResponse {
        return $this->execute($context, $steps, $onData, $logContext);
    }


    /**
     * Executes the configured pipeline.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Current chat context.
     * @param iterable<\Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface> $steps Configured steps.
     * @param callable|null $onData Optional streaming callback.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLogMetaData|null $logContext Optional log context.
     * @return \Madj2k\AiCore\Assistant\DTO\AssistantResponse
     * @throws \Throwable
     */
    private function execute(
        Context $context,
        iterable $steps,
        ?callable $onData = null,
        ?PipelineLogMetaData $logContext = null
    ): AssistantResponse {
        $steps = is_array($steps) ? array_values($steps) : iterator_to_array($steps, false);

        foreach ($this->validator->validate($steps, $this->processorRegistry) as $validationMessage) {
            if ($logContext instanceof PipelineLogMetaData) {
                $this->pipelineLogger->event('pipeline.validation.warning', $logContext, [
                    'message' => $validationMessage,
                ]);
            }
        }

        /** @var array<int,string> $sourceFields */
        $sourceFields = [];
        $visibleAnswerStepIndex = $onData !== null
            ? $this->resolveVisibleAnswerStepIndex($steps)
            : null;
        $streamedData = false;
        $streamCallback = $onData !== null
            ? static function (string $chunk) use ($onData, &$streamedData): void {
                if ($chunk !== '') {
                    $streamedData = true;
                }
                $onData($chunk);
            }
            : null;

        /**
         * @var \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step
         */
        foreach ($steps as $stepIndex => $step) {
            if ($step->getPromptMetadataFieldList() !== []) {
                $sourceFields = $step->getPromptMetadataFieldList();
            }

            $processor = $this->processorRegistry->get($step->getProcessorIdentifier(), $step->getType());

            if (!$processor->canProcess($context, $step)) {
                $payload = [
                    'step_uid' => (int)$step->getUid(),
                    'step_title' => $step->getTitle(),
                    'processor_type' => $step->getType()->value,
                    'reason' => 'Required context slot missing or execution blocked.',
                ];

                if ($logContext instanceof PipelineLogMetaData) {
                    $this->pipelineLogger->event('step.skipped', $logContext, $payload);
                }
                continue;
            }

            $startedAt = null;
            if ($logContext instanceof PipelineLogMetaData) {
                $startedAt = $this->pipelineLogger->startStep(
                    $logContext,
                    $step->getTitle(),
                    $step->getType()->value,
                    [
                        'step_uid' => (int)$step->getUid(),
                        'stage' => $step->getStage()->value,
                    ]
                );
            }

            try {
                if (
                    $stepIndex === $visibleAnswerStepIndex
                    && $streamCallback !== null
                    && $processor instanceof ProcessorStreamingInterface
                ) {
                    $processor->processStream($context, $step, $streamCallback, $logContext);
                } else {
                    $processor->process($context, $step, $logContext);
                }

                if ($logContext instanceof PipelineLogMetaData && is_float($startedAt)) {
                    $this->pipelineLogger->finishStep(
                        $logContext,
                        $step->getTitle(),
                        $step->getType()->value,
                        $startedAt,
                        [
                            'step_uid' => (int)$step->getUid(),
                            'stage' => $step->getStage()->value,
                        ]
                    );
                }
            } catch (\Throwable $exception) {
                if ($logContext instanceof PipelineLogMetaData) {
                    $this->pipelineLogger->error('step.failed', $logContext, [
                        'step_uid' => (int)$step->getUid(),
                        'step_title' => $step->getTitle(),
                        'processor_type' => $step->getType()->value,
                        'exception_message' => $exception->getMessage(),
                        'exception_class' => get_class($exception),
                    ]);
                }

                if (
                    $exception instanceof ApiException
                    || $exception instanceof VectorDatabaseException
                    ||
                    $step->getFailureStrategy() === AssistantPipelineFailureStrategy::Stop
                    || ($stepIndex === $visibleAnswerStepIndex && $streamedData)
                ) {
                    throw $exception;
                }
            }
        }

        $response = $this->createResponse($context, $sourceFields);
        if ($onData !== null && !$streamedData && $response->answer !== '') {
            $onData($response->answer);
        }

        return $response;
    }


    /**
     * Returns the last step that can define the user-visible answer.
     *
     * @param array<int, \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface> $steps Pipeline steps.
     * @return int|null Step index.
     */
    private function resolveVisibleAnswerStepIndex(array $steps): ?int
    {
        for ($index = count($steps) - 1; $index >= 0; --$index) {
            if (in_array($steps[$index]->getType(), [
                AssistantPipelineProcessorType::AnswerGenerator,
                AssistantPipelineProcessorType::QualityGate,
            ], true)) {
                return $index;
            }
        }

        return null;
    }


    /**
     * Creates the assistant response from the context.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Current chat context.
     * @param array<int,string> $sourceFields Source field names.
     * @return \Madj2k\AiCore\Assistant\DTO\AssistantResponse
     */
    private function createResponse(Context $context, array $sourceFields): AssistantResponse
    {
        $answer = $context->getAnswer()->getFinal();
        if ($answer === '') {
            $answer = $context->getAnswer()->getCandidate();
        }

        return new AssistantResponse(
            answer: $answer,
            context: [
                'originalQuery' => $context->getRequest()->getQuery(),
                'currentQuery' => $context->getCurrentQuery(),
                'answerContext' => $context->getRetrieval()->getAnswerContext(),
                'retrievalCount' => $context->getRetrieval()->getDocumentCount(),
                'retrievals' => array_map(
                    static fn ($group): array => [
                        'identifier' => $group->identifier,
                        'processorIdentifier' => $group->processorIdentifier,
                        'query' => $group->query,
                        'collection' => $group->collection,
                        'documentCount' => count($group->documents),
                    ],
                    $context->getRetrieval()->getGroups(),
                ),
                'sources' => $this->createFrontendSources(
                    $context->getRetrieval()->getDocuments(),
                    $sourceFields
                ),
            ]
        );
    }


    /**
     * Creates deduplicated presentation-layer sources from retrieved documents.
     *
     * @param array<int, \Madj2k\AiCore\Assistant\DTO\RetrievalDocument> $documents Retrieved documents.
     * @param array<int,string> $fields Source field names.
     * @return array<int,array<string,mixed>> Frontend sources.
     */
    private function createFrontendSources(array $documents, array $fields): array
    {
        if ($fields === []) {
            return [];
        }

        $sources = [];
        $seen = [];

        foreach ($documents as $document) {
            if (!$document instanceof RetrievalDocument) {
                continue;
            }

            $source = $document->toSourceArray($fields);
            if ($source === []) {
                continue;
            }

            $deduplicationKey = (string)($source['source_identifier'] ?? $source['url'] ?? md5(json_encode($source)));
            if (isset($seen[$deduplicationKey])) {
                continue;
            }

            $seen[$deduplicationKey] = true;
            $sources[] = $source;
        }

        return $sources;
    }
}
