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
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Pipeline\Processor\AbstractLlmProcessor;
use Madj2k\AiCore\Assistant\Pipeline\Processor\ProcessorStreamingInterface;
use Madj2k\AiCore\Assistant\Prompt\PromptBuilder;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;

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
        $answer = (string)$this->callAi($context, $messages, $step, $logContext);
        $context->getAnswer()->setFinal($answer !== '' ? $answer : $context->getAnswer()->getCandidate());

        // trace
        $context->getProcessingTrace()->add('quality_gate.completed',
            $step->getUid(),
            $messages,
            $context->getAnswer()->getFinal()
        );
    }


    /**
     * @inheritDoc
     */
    public function processStream(
        Context $context,
        PipelineStepConfigurationInterface $step,
        callable $onData,
        ?PipelineLogMetaData $logContext = null
    ): void {

        $messages = $this->promptBuilder->buildMessages($context, $step);
        $answer = $this->callAiStream($context, $messages, $step, $onData, $logContext);
        $context->getAnswer()->setFinal($answer !== '' ? $answer : $context->getAnswer()->getCandidate());

        // trace
        $context->getProcessingTrace()->add('quality_gate.completed',
            $step->getUid(),
            $messages,
            $context->getAnswer()->getFinal()
        );
    }

}
