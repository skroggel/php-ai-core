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

namespace Madj2k\AiCore\Assistant\Contract;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;

/**
 * Defines a reusable, prompt-aware data contract.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
interface ContractInterface
{
    /**
     * Returns the stable contract identifier.
     *
     * @return string Contract identifier.
     */
    public function getIdentifier(): string;


    /**
     * Returns whether this contract is applicable to a processor type.
     *
     * @param \Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType $type Processor type.
     * @return bool Applicability flag.
     */
    public function supports(AssistantPipelineProcessorType $type): bool;


    /**
     * Returns instructions describing the contract to an LLM.
     *
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface|null $step
     * @return string
     */
    public function getPromptInstructions(?PipelineStepConfigurationInterface $step = null): string;


    /**
     * Parses one LLM response according to this contract.
     *
     * @param string $raw LLM response.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return mixed Parsed contract result.
     */
    public function parse(string $raw, PipelineStepConfigurationInterface $step): mixed;
}
