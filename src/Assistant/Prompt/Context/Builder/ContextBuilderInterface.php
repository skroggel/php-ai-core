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
use Madj2k\AiCore\Assistant\DTO\PromptRole;
use Madj2k\AiCore\Assistant\DTO\PromptType;

/**
 * Interface ContextBuilderInterface
 *
 * Defines a builder that contributes structured sections to a prompt context.
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
interface ContextBuilderInterface
{
    /**
     * Returns whether this builder contributes sections for the processor type.
     *
     * @param \Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType $type Pipeline processor type.
     * @return bool Supports flag.
     */
    public function supports(AssistantPipelineProcessorType $type): bool;

    /**
     * Returns the chat message role for sections produced by this builder.
     */
    public function getPromptRole(): PromptRole;

    /**
     * Returns the parent prompt section type for sections produced by this builder.
     *
     * @return \Madj2k\AiCore\Assistant\DTO\PromptType Parent prompt section type.
     */
    public function getPromptType(): PromptType;


    /**
     * Returns general instructions
     *
     * @param \Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType $type
     * @return array<int, string> General instructions.
     */
    public function getGeneralInstructions(AssistantPipelineProcessorType $type): array;

    /**
     * Builds prompt sections for the given context and pipeline step.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Current chat context.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return array<int, \Madj2k\AiCore\Assistant\Prompt\Context\PromptSection> Prompt sections.
     */
    public function build(Context $context, PipelineStepConfigurationInterface $step): array;
}
