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

namespace Madj2k\AiCore\Assistant\Pipeline\Processor\Llm;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Contract\QualityGateContract;
use Madj2k\AiCore\Assistant\DTO\QualityGateDecision;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Pipeline\Processor\AbstractLlmProcessor;
use Madj2k\AiCore\Assistant\Pipeline\Processor\ProcessorStreamingInterface;
use Madj2k\AiCore\Assistant\Prompt\PromptBuilder;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Exception\AppException;

/**
 * Class QualityGateProcessor
 *
 * Checks and finalizes the answer candidate.
 *
 * @internal Register custom pipeline behavior through ProcessorInterface.
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class QualityGateProcessor extends AbstractLlmProcessor implements ProcessorStreamingInterface
{
    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'aiassistant.quality_gate.default';
    }


    /**
     * @inheritDoc
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return $type === AssistantPipelineProcessorType::QualityGate;
    }


    /**
     * @inheritDoc
     */
    public function canProcess(Context $context, PipelineStepConfigurationInterface $step): bool
    {
        return trim($context->getAnswer()->getCandidate()) !== '';
    }


    /**
     * @inheritDoc
     * @throws \Madj2k\AiCore\Exception\AppException
     */
    public function process(Context $context, PipelineStepConfigurationInterface $step, ?PipelineLogMetaData $logContext = null): void
    {
        $messages = $this->promptBuilder->buildMessages($context, $step);
        $decision = $this->callAi($context, $messages, $step, $logContext);
        $answer = $this->applyDecision($context, $decision);

        // trace
        $context->getProcessingTrace()->add('quality_gate.completed',
            $step->getUid(),
            $messages,
            $context->getAnswer()->getFinal()
        );
    }


    /**
     * @inheritDoc
     * @throws \Madj2k\AiCore\Exception\AppException
     */
    public function processStream(
        Context $context,
        PipelineStepConfigurationInterface $step,
        callable $onData,
        ?PipelineLogMetaData $logContext = null
    ): void {

        $messages = $this->promptBuilder->buildMessages($context, $step);
        $contract = $this->contractRegistry->resolveForStep($step);
        if (!$contract instanceof QualityGateContract) {
            throw new AppException('Quality Gate contract could not be resolved.', 1793531002);
        }

        // Buffer only the possible KEEP sentinel. Replacement text is forwarded
        // as soon as it can no longer be the exact sentinel.
        $rawDecision = '';
        $pending = '';
        $replacementStarted = false;
        $rawDecision = $this->callAiStream(
            $context,
            $messages,
            $step,
            function (string $chunk) use (&$rawDecision, &$pending, &$replacementStarted, $onData, $contract): void {
                $rawDecision .= $chunk;
                if ($replacementStarted) {
                    $onData($chunk);
                    return;
                }

                $pending .= $chunk;
                $probe = ltrim($pending);
                if ($probe !== '' && !$contract->isKeepPrefix($probe)) {
                    $replacementStarted = true;
                    $onData($pending);
                    $pending = '';
                }
            },
            $logContext,
        );

        $decision = $contract->parse($rawDecision, $step);
        $answer = $this->applyDecision($context, $decision);
        if (!$replacementStarted) {
            $this->emitAnswerChunks($answer, $onData);
        }

        // trace
        $context->getProcessingTrace()->add('quality_gate.completed',
            $step->getUid(),
            $messages,
            $context->getAnswer()->getFinal()
        );
    }

    /**
     * Applies one validated Quality Gate decision to the answer state.
     *
     * @param Context $context Assistant context.
     * @param mixed $decision Parsed contract result.
     * @return string Final answer.
     * @throws AppException When the contract result is invalid.
     */
    private function applyDecision(Context $context, mixed $decision): string
    {
        if (!$decision instanceof QualityGateDecision) {
            throw new AppException('Quality Gate returned an invalid decision contract.', 1793531001);
        }

        $answer = $decision->decision === 'keep'
            ? $context->getAnswer()->getCandidate()
            : (string)$decision->answer;
        $context->getAnswer()->setFinal($answer);

        return $context->getAnswer()->getFinal();
    }

    /**
     * Emits the validated final answer in UTF-8-safe chunks.
     *
     * @param string $answer Final answer.
     * @param callable $onData Streaming callback.
     * @return void
     */
    private function emitAnswerChunks(string $answer, callable $onData): void
    {
        foreach (mb_str_split($answer, 80) as $chunk) {
            $onData($chunk);
        }
    }

}
