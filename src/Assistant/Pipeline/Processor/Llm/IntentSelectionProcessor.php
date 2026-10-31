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
use Madj2k\AiCore\Assistant\Contract\DecisionContract;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Pipeline\Processor\AbstractLlmProcessor;
use Madj2k\AiCore\Assistant\Prompt\PromptBuilder;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterCondition;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterConjunction;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterOperator;
/**
 * Class IntentSelectionProcessor
 *
 * Uses a configurable JSON contract to select retrieval targets, presentation
 * rules and optional retriever payload filters.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class IntentSelectionProcessor extends AbstractLlmProcessor
{

    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'aiassistant.intent_selector.default';
    }


    /**
     * @inheritDoc
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return $type === AssistantPipelineProcessorType::IntentSelector;
    }


    /**
     * @inheritDoc
     */
    public function canProcess(Context $context, PipelineStepConfigurationInterface $step): bool
    {
        return trim($context->getCurrentQuery()) !== '';
    }


    /**
     * @inheritDoc
     */
    public function process(Context $context, PipelineStepConfigurationInterface $step, ?PipelineLogMetaData $logContext = null): void
    {
        $decision = $this->callAi($context, $this->promptBuilder->buildMessages($context, $step), $step, $logContext);
        if (!$decision instanceof \Madj2k\AiCore\Assistant\DTO\DecisionResult) {
            throw new \UnexpectedValueException('Intent selector did not return a pipeline decision result.');
        }
        if ($decision === null) {
            return;
        }

        $contracts = $step->getContracts();
        if (in_array('retrieval', $contracts, true)) {
            $context->setRetrievalPlan($decision->retrievalPlan);
        }
        if (in_array('presentationRules', $contracts, true)) {
            $context->setPresentationRulePlan($decision->presentationRulePlan);
        }
    }


}
