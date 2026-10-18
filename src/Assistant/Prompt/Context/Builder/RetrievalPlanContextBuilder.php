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

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Prompt\Context\PromptSection;
use Madj2k\AiCore\Assistant\DTO\PromptRole;
use Madj2k\AiCore\Assistant\DTO\PromptType;

/**
 * Class RetrievalPlanContextBuilder
 *
 * Exposes the selector result to later LLM pipeline steps.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class RetrievalPlanContextBuilder extends AbstractContextBuilder
{

    /**
     * @param AssistantPipelineProcessorType $type Pipeline step type.
     * @return bool Support flag.
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return in_array($type, [
            AssistantPipelineProcessorType::ContextOptimizer,
            AssistantPipelineProcessorType::AnswerGenerator,
            AssistantPipelineProcessorType::QualityGate,
        ], true);
    }

    /**
     * @inheritDoc
     */
    public function getPromptRole(): PromptRole
    {
        return PromptRole::User;
    }

    /**
     * @inheritDoc
     */
    public function getPromptType(): PromptType
    {
        return PromptType::Retrieval;
    }

    /**
     * @inheritDoc
     */
    public function build(Context $context, PipelineStepConfigurationInterface $step): array
    {
        $plan = $context->getRetrievalPlan();
        $lines = [
            'Mode: ' . $plan->mode,
            'Targets: ' . ($plan->targets === [] ? '(all configured targets)' : implode(', ', $plan->targets)),
        ];

        if ($plan->metadata !== []) {
            $lines[] = 'Metadata:';
            $encoded = json_encode($plan->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $lines[] = is_string($encoded) ? $encoded : '{}';
        }

        return [new PromptSection('Retrieval Selection Result', implode("\n", $lines), 75)];
    }
}
