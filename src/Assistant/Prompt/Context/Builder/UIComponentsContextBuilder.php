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
use Madj2k\AiCore\Assistant\Presentation\UIComponents\UIComponentProviderInterface;
use Madj2k\AiCore\Assistant\DTO\PromptRole;
use Madj2k\AiCore\Assistant\DTO\PromptType;

/**
 * Class UIComponentsContextBuilder
 *
 * Adds UI component instructions to the central prompt builder context.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class UIComponentsContextBuilder extends AbstractContextBuilder
{
    /**
     * Constructor.
     *
     * @param \Madj2k\AiCore\Assistant\Presentation\UIComponents\UIComponentProviderInterface $provider Component definition provider.
     */
    public function __construct(private UIComponentProviderInterface $provider)
    {
    }

    /**
     * @inheritDoc
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
            'Use configured UI components exactly where required by the applicable output instructions. Do not add components where those instructions prohibit them.',
            'Output each UI component directly using its documented self-contained @@UI_COMPONENT JSON syntax, without code fences.',
            'Select each component by its configured identifier and provide variable data only through its declared data fields. Configured actions and their properties are resolved by the runtime and must not be generated or overridden.',
            'A UI component is a complete output element. Never use it as a URL or Markdown link destination, wrap it in Markdown link syntax or HTML, or duplicate content it renders itself.',
            'Follow the applicable output instructions for link format and placement. Do not assume that links must always use either Markdown or a UI component.',
            'Definition headings, boundary markers, names, schemas and syntax documentation are internal and must not appear in the answer.',
        ] : [];
    }

    /**
     * @inheritDoc
     */
    public function build(Context $context, PipelineStepConfigurationInterface $step): array
    {
        $section = $this->buildInstructions($context, $step);
        if ($section === null) {
            return [];
        }

        return [$section];
    }

    /**
     * Builds the component instructions included in the current LLM prompt.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Current assistant context.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return \Madj2k\AiCore\Assistant\Prompt\Context\PromptSection|null Prompt section.
     */
    private function buildInstructions(Context $context, PipelineStepConfigurationInterface $step): ?PromptSection
    {
        $definitions = $this->provider->getDefinitions($context, $step);
        if ($definitions === []) {
            return null;
        }

        $lines = $this->getGeneralInstructions($step->getType());
        $sections = [];
        foreach ($definitions as $definition) {
            $componentLines = [];
            $componentLines[] = 'Name: ' . ($definition->title !== '' ? $definition->title : $definition->identifier);
            if ($definition->description !== '') {
                $componentLines[] = 'Purpose: ' . $definition->description;
            }
            if ($definition->placeholders !== []) {
                $componentLines[] = 'Placeholders: ' . implode(', ', $definition->placeholders);
            }
            if ($definition->dataSchema !== []) {
                $componentLines[] = 'Data schema: ' . json_encode($definition->dataSchema, JSON_UNESCAPED_SLASHES);
            }
            $componentType = $definition->type !== '' ? $definition->type : $definition->identifier;
            $usage = $definition->usage !== ''
                ? str_replace(
                    ['{type}', '{identifier}'],
                    [$componentType, $definition->identifier],
                    $definition->usage,
                )
                : '@@UI_COMPONENT {"type":"' . $componentType . '","identifier":"' . $definition->identifier . '","data":{...}}';
            $componentLines[] = 'Usage: ' . $usage;
            $sections[] = new PromptSection(
                'UI COMPONENT: ' . $definition->identifier,
                implode("\n", $componentLines),
            );
        }

        return new PromptSection('Available UI Components', implode("\n", $lines), 80, $sections);
    }
}
