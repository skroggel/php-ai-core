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

namespace Madj2k\AiCore\Assistant\Contract\DecisionContract;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;

/**
 * Marks a contract part that can be registered in the pipeline decision contract.
 *
 * @author Steffen Kroggel <developer@steffen-kroggel.de>
 * @copyright Steffen Kroggel <developer@steffen-kroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
interface DecisionContractInterface
{
    /**
     * Returns the stable contract identifier.
     *
     * @return string Contract identifier.
     */
    public function getIdentifier(): string;


    /**
     * Returns instructions describing the contract to an LLM.
     *
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface|null $step
     * @return string
     */
    public function getPromptInstructions(?PipelineStepConfigurationInterface $step = null): string;

}
