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
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalTargetProviderInterface;
use Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRuleProviderInterface;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Prompt\Context\PromptSection;
use Madj2k\AiCore\Assistant\DTO\PromptRole;

/**
 * Class IntentSelectionContextBuilder
 *
 * Adds available retrieval targets and presentation rules to the LLM context.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class IntentSelectionContextBuilder extends AbstractContextBuilder
{

    /**
     * Constructor.
     *
     * @param RetrievalTargetProviderInterface $targetProvider Retrieval target provider.
     * @param PresentationRuleProviderInterface $presentationRuleProvider Presentation rule provider.
     */
    public function __construct(
        private RetrievalTargetProviderInterface $targetProvider,
        private PresentationRuleProviderInterface $presentationRuleProvider,
    )
    {
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
    public function getGeneralInstructions(AssistantPipelineProcessorType $type): array
    {
        return $this->supports($type) ? [
            'Select retrieval targets and presentation rules based on the original user intent. Do not answer the query.',
            'Apply explicit exclusions before general inclusion rules.',
            'Return only JSON in this exact format: {"retrieval":{"mode":"all","targets":[]},"presentationRules":{"mode":"all","rules":[]},"metadata":{}}.',
            'Each area supports mode all, selected, all_except or none. Use only available identifiers; default both areas to all.',
            'For mode all or none, the corresponding targets or rules array must be empty. Lists are used only with selected or all_except.',
            'If there is no explicit reason to restrict either area, return exactly: {"retrieval":{"mode":"all","targets":[]},"presentationRules":{"mode":"all","rules":[]},"metadata":{}}.',
            'Never combine mode all or none with non-empty targets or rules arrays.',
            'Ensure both modes and lists are consistent. Metadata is optional.',
        ] : [];
    }

    /**
     * @inheritDoc
     */
    public function build(Context $context, PipelineStepConfigurationInterface $step): array
    {
        $lines = $this->getGeneralInstructions($step->getType());

        foreach ($this->targetProvider->getTargets($context, $step) as $target) {
            $lines[] = sprintf('- %s: %s', $target->identifier, $target->description !== '' ? $target->description : $target->title);
        }
        $lines[] = '';
        $lines[] = 'Available presentation rules:';
        foreach ($this->presentationRuleProvider->getAll() as $rule) {
            $lines[] = sprintf('- %s: %s', $rule->identifier, $rule->title !== '' ? $rule->title : $rule->content);
        }
        if (method_exists($step, 'getIntentSelectionInstructions') && $step->getIntentSelectionInstructions() !== '') {
            $lines[] = '';
            $lines[] = 'Additional selection rules:';
            $lines[] = $step->getIntentSelectionInstructions();
        }
        if (method_exists($step, 'getIntentSelectionMetadata') && $step->getIntentSelectionMetadata() !== '') {
            $lines[] = '';
            $lines[] = 'Return metadata according to this schema:';
            $lines[] = $step->getIntentSelectionMetadata();
        }

        return [new PromptSection('Available Intent Selections', implode("\n", $lines), 70)];
    }
}
