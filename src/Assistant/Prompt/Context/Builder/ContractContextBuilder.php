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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Madj2k\AiCore\Assistant\Prompt\Context\Builder;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Contract\ContractRegistry;
use Madj2k\AiCore\Assistant\Contract\DecisionContract;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Retrieval\RetrievalTargetProviderInterface;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRuleProviderInterface;
use Madj2k\AiCore\Assistant\Prompt\Context\PromptSection;

/**
 * Adds configured response contract instructions to LLM prompts.
 *
 * @author Steffen Kroggel <developer@steffen-kroggel.de>
 * @copyright Steffen Kroggel <developer@steffen-kroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class ContractContextBuilder extends AbstractContextBuilder
{
    /**
     * Constructor.
     *
     * @param \Madj2k\AiCore\Assistant\Contract\ContractRegistry $contractRegistry Contract registry.
     * @param \Madj2k\AiCore\Assistant\Retrieval\RetrievalTargetProviderInterface $retrievalTargetProvider Retrieval target provider.
     * @param \Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRuleProviderInterface $presentationRuleProvider Presentation rule provider.
     */
    public function __construct(
        private ContractRegistry $contractRegistry,
        private RetrievalTargetProviderInterface $retrievalTargetProvider,
        private PresentationRuleProviderInterface $presentationRuleProvider,
    )
    {
    }


    /**
     * @inheritDoc
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return !in_array($type, [
            AssistantPipelineProcessorType::Retriever,
            AssistantPipelineProcessorType::Memory,
        ], true);
    }


    /**
     * @inheritDoc
     */
    public function build(Context $context, PipelineStepConfigurationInterface $step): array
    {
        if (!$this->supports($step->getType())) {
            return [];
        }

        $contract = $this->contractRegistry->resolveForStep($step);
        $instructions = [$contract->getPromptInstructions($step)];

        if ($contract instanceof DecisionContract) {
            $contractIdentifiers = $step->getDecisionContracts();
            if (in_array('retrieval', $contractIdentifiers, true)) {
                $instructions[] = $this->buildRetrievalTargets($context, $step);
            }
            if (in_array('presentationRules', $contractIdentifiers, true)) {
                $instructions[] = $this->buildPresentationRules($context, $step);
            }
        }

        if ($instructions === []) {
            return [];
        }

        return [new PromptSection(
            'Response Contracts',
            implode("\n", $instructions),
            65,
        )];
    }

    /**
     * Lists the retrieval targets available to the contract.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Current context.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return string Available retrieval targets.
     */
    private function buildRetrievalTargets(Context $context, PipelineStepConfigurationInterface $step): string
    {
        $lines = [];
        if ($retrievalTargets = $this->retrievalTargetProvider->getTargets($context, $step)) {
            $lines = ['Available retrieval targets:'];
            foreach ($retrievalTargets  as $target) {
                $lines[] = sprintf(
                    '- %s: %s',
                    $target->identifier,
                    $target->description !== '' ? $target->description : $target->title,
                );
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Lists the presentation rules available to the contract.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Current context.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return string Available presentation rules.
     */
    private function buildPresentationRules(Context $context, PipelineStepConfigurationInterface $step): string
    {

        $lines = [];
        if ($presentationRules = $this->presentationRuleProvider->getAll()) {
            $lines = ['Available presentation rules:'];
            foreach ($presentationRules as $rule) {
                $lines[] = sprintf(
                    '- %s: %s',
                    $rule->identifier,
                    $rule->title !== '' ? $rule->title : $rule->content,
                );
            }
        }
        return implode("\n", $lines);
    }
}
