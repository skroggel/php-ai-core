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
use Madj2k\AiCore\Assistant\Contract\DecisionContract;
use Madj2k\AiCore\Assistant\Prompt\PromptBuilder;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;

/**
 * Class QueryOptimizerProcessor
 *
 * Rewrites the user query for retrieval while preserving the original query.
 *
 * @internal Register custom pipeline behavior through ProcessorInterface.
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class QueryOptimizerProcessor extends AbstractLlmProcessor
{

    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'aiassistant.query_optimizer.default';
    }


    /**
     * @inheritDoc
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return $type === AssistantPipelineProcessorType::QueryOptimizer;
    }


    /**
     * @inheritDoc
     */
    public function canProcess(Context $context, PipelineStepConfigurationInterface $step): bool
    {
        return trim($context->getRequest()->getQuery()) !== '';
    }


    /**
     * @inheritDoc
     */
    public function process(Context $context, PipelineStepConfigurationInterface $step, ?PipelineLogMetaData $logContext = null): void
    {
        $messages = $this->promptBuilder->buildMessages($context, $step);
        $decision = $this->callAi($context, $messages, $step, $logContext);
        if (!$decision instanceof \Madj2k\AiCore\Assistant\DTO\DecisionResult) {
            throw new \UnexpectedValueException('Query optimizer did not return a decision result.');
        }
        if ($decision === null) {
            return;
        }

        if ($decision->query !== '') {
            $context->setCurrentQuery($decision->query);
        }
        $context->setRetrievalPlan($decision->retrievalPlan);

        // trace
        $context->getProcessingTrace()->add('query_optimizer.completed',
            $step->getUid(),
            $messages,
            $context->getCurrentQuery()
        );
    }


    /**
     * Normalizes the LLM output to a single query string.
     *
     * @param string $optimizedQuery Raw optimized query.
     * @return string Normalized query.
     */
    private function normalizeOptimizedQuery(string $optimizedQuery): string
    {
        $optimizedQuery = trim($optimizedQuery);
        if ($optimizedQuery === '') {
            return '';
        }

        if (str_contains($optimizedQuery, "\n")) {
            $lines = array_values(array_filter(
                array_map('trim', preg_split('/\R+/', $optimizedQuery) ?: []),
                static fn (string $line): bool => $line !== ''
            ));

            return $lines[0] ?? '';
        }

        return $optimizedQuery;
    }
}
