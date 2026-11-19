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
use Madj2k\AiCore\Assistant\Enum\PromptRole;
use Madj2k\AiCore\Assistant\Enum\PromptType;
use Madj2k\AiCore\Assistant\Glossary\GlossaryProviderInterface;
use Madj2k\AiCore\Assistant\Prompt\Context\PromptSection;

/**
 * Class GlossaryContextBuilder
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class GlossaryContextBuilder extends AbstractContextBuilder
{
    /**
     * @param \Madj2k\AiCore\Assistant\Glossary\GlossaryProviderInterface $provider
     */
    public function __construct(private readonly GlossaryProviderInterface $provider)
    {
    }

    /**
     * @param \Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType $type
     * @return bool
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
            'Use the glossary as terminology guidance for the effective response language. It may contain explicit source-to-target mappings or a list of preferred target-language terms without source mappings.',
            'When an explicit source-to-target mapping is present, use the target term whenever the source term or an equivalent expression occurs.',
            'When an entry contains only a preferred target-language term, use that term consistently wherever it fits the meaning and grammar. Do not invent a source-to-target mapping from an incomplete entry.',
            'Do not mention, quote or explain the internal glossary in the answer.',
        ] : [];
    }

    /**
     * @inheritDoc
     */
    public function build(Context $context, PipelineStepConfigurationInterface $step): array
    {
        $glossary = $this->provider->resolve($context);
        if ($glossary === null) {
            return [];
        }

        $language = $glossary->languageCode !== '' ? ' (' . $glossary->languageCode . ')' : '';
        $reference = $glossary->identifier !== '' ? ' [' . $glossary->identifier . ']' : '';
        $title = $glossary->title !== '' ? ': ' . $glossary->title : '';
        $content = implode("\n", $this->getGeneralInstructions($step->getType()))
            . "\n\nTarget language{$language}:\n"
            . $glossary->content;

        return [new PromptSection('Terminology Glossary' . $reference . $title, $content, 70)];
    }
}
