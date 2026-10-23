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

namespace Madj2k\AiCore\Assistant\Prompt\Context\Builder;

use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;

/**
 * Class QueryContextBuilder
 *
 * Builds prompt sections for original and current user queries.
 *
 * @internal Register custom prompt context through ContextBuilderInterface.
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class QueryContextBuilder extends AbstractContextBuilder
{
    /**
     * @inheritDoc
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return in_array($type, [
            AssistantPipelineProcessorType::QueryOptimizer,
            AssistantPipelineProcessorType::IntentSelector,
            AssistantPipelineProcessorType::ContextOptimizer,
            AssistantPipelineProcessorType::AnswerGenerator,
            AssistantPipelineProcessorType::QualityGate,
        ], true);
    }


    /**
     * @inheritDoc
     */
    public function build(Context $context, PipelineStepConfigurationInterface $step): array
    {
        return match ($step->getType()) {
            AssistantPipelineProcessorType::QueryOptimizer,
            AssistantPipelineProcessorType::IntentSelector => $this->filterSections([
                $this->section('Current Query', $context->getCurrentQuery(), 10),
                $this->section('Original User Query', $context->getRequest()->getQuery(), 20),
            ]),
            AssistantPipelineProcessorType::ContextOptimizer => $this->filterSections([
                $this->section('Current Query', $context->getCurrentQuery(), 10),
            ]),
            AssistantPipelineProcessorType::QualityGate => $this->filterSections([
                $this->section('Original User Query', $context->getRequest()->getQuery(), 10),
            ]),
            AssistantPipelineProcessorType::AnswerGenerator => $this->filterSections([
                $this->section('Original User Query', $context->getRequest()->getQuery(), 10),
                $this->section('Current Query', $context->getCurrentQuery(), 20),
            ]),
            default => [],
        };
    }
}
