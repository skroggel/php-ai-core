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

use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Pipeline\Processor\AbstractLlmProcessor;
use Madj2k\AiCore\Assistant\Prompt\PromptBuilder;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Exception\AppException;

/**
 * Class ContextOptimizerProcessor
 *
 * Uses the LLM to condense retrieved documents into a focused answer context.
 *
 * @internal Register custom pipeline behavior through ProcessorInterface.
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class ContextOptimizerProcessor extends AbstractLlmProcessor
{
    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'aiassistant.context_optimizer.default';
    }


    /**
     * @inheritDoc
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return $type === AssistantPipelineProcessorType::ContextOptimizer;
    }


    /**
     * @inheritDoc
     */
    public function canProcess(Context $context, PipelineStepConfigurationInterface $step): bool
    {
        return $context->getRetrieval()->getDocumentCount() > 0;
    }


    /**
     * @inheritDoc
     * @throws \Madj2k\AiCore\Exception\AppException
     * @throws \Madj2k\AiCore\Exception\AssistantException
     */
    public function process(Context $context, PipelineStepConfigurationInterface $step, ?PipelineLogMetaData $logContext = null): void
    {
        $rawContext = $this->promptBuilder->getContextSectionContent(
            $context,
            $step,
            'Retrieved Context'
        );
        if ($rawContext === '') {
            return;
        }

        $messages = $this->promptBuilder->buildMessages($context, $step);

        $answerContext = (string)$this->callAi($context, $messages, $step, $logContext);
        $context->getRetrieval()->setAnswerContext($answerContext !== '' ? $answerContext : $rawContext);

        // trace
        $context->getProcessingTrace()->add('context_optimizer.completed',
            $step->getUid(),
            $messages,
            $context->getRetrieval()->getAnswerContext(),
        );
    }
}
