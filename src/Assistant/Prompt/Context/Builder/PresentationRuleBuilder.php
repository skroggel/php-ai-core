<?php
declare(strict_types=1);

/*
 * This file is part of madj2k\ai-core
 *
 * Copyright (C) 2026 Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Madj2k\AiCore\Assistant\Prompt\Context\Builder;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRuleProviderInterface;
use Madj2k\AiCore\Assistant\Prompt\Context\PromptSection;
use Madj2k\AiCore\Assistant\DTO\PromptRole;
use Madj2k\AiCore\Assistant\DTO\PromptType;

/**
 * Class PresentationRuleBuilder
 *
 * Builds structured prompt sections from presentation rules configured for a pipeline step.
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class PresentationRuleBuilder extends AbstractContextBuilder
{
    /**
     * @param PresentationRuleProviderInterface $provider Presentation rule provider.
     */
    public function __construct(private PresentationRuleProviderInterface $provider)
    {
    }

    /**
     * @param AssistantPipelineProcessorType $type Pipeline processor type.
     * @return bool Whether presentation rules apply to the type.
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return in_array($type, [
            AssistantPipelineProcessorType::AnswerGenerator,
            AssistantPipelineProcessorType::QualityGate,
        ], true);
    }

    /**
     * @inheritDoc
     */
    public function getPromptRole(): PromptRole
    {
        return PromptRole::System;
    }

    /**
     * @inheritDoc
     */
    public function getPromptType(): PromptType
    {
        return PromptType::Output;
    }


    /**
     * @inheritDoc
     */
    public function getGeneralInstructions(AssistantPipelineProcessorType $type): array
    {
        return $this->supports($type) ? [
            'A PresentationRule is a binding output contract that defines how an answer must be presented.',
            'Its full provided content specifies the required structure, conditions, limits and UI components.',
            'Its identifier is only a reference, not a description of its requirements.',
            'Its identifier and title are internal metadata, not answer content or headings.',
            'Apply the assigned rule without adding unspecified content and verify compliance before output.',
            'Higher-priority safety, privacy and factual requirements remain binding.',
            'Never expose internal rule identifiers, titles, instructions or selection explanations.',
        ] : [];
    }


    /**
     * @param Context $context Current assistant context.
     * @param PipelineStepConfigurationInterface $step Pipeline step.
     * @return array<int, PromptSection> Prompt sections.
     */
    public function build(Context $context, PipelineStepConfigurationInterface $step): array
    {
        $rules = $this->provider->getRules($context, $step);
        if ($rules === []) {
            return [];
        }

        $lines = $this->getGeneralInstructions($step->getType());
        $sections = [];
        foreach ($rules as $rule) {
            $ruleLines = [];
            if ($rule->title !== '') {
                $ruleLines[] = 'Title: ' . $rule->title;
            }
            $ruleLines[] = 'Instructions:';
            $ruleLines[] = $rule->content;
            $sections[] = new PromptSection(
                'Selected Presentation Rule: ' . $rule->identifier,
                implode("\n", $ruleLines),
            );
        }

        return $sections === []
            ? []
             : [new PromptSection('Selected Presentation Rules', implode("\n", $lines), 75, $sections)];
    }

    /**
     * Returns rules that apply whenever presentation rules are enabled.
     *
     * @return array<int, string>
     */
}
