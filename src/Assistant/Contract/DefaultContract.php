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

namespace Madj2k\AiCore\Assistant\Contract;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;

/**
 * Default plain-text contract for ordinary LLM responses.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class DefaultContract implements ContractInterface
{

    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'default';
    }

    /**
     * @inheritDoc
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return !in_array($type, [
            AssistantPipelineProcessorType::IntentSelector,
            AssistantPipelineProcessorType::QueryOptimizer,
        ], true);
    }

    /**
     * @inheritDoc
     */
    public function getPromptInstructions(?PipelineStepConfigurationInterface $step = null): string
    {
        return 'Return the response as normal plain text. Do not return a JSON contract.';
    }

    /**
     * @inheritDoc
     */
    public function parse(string $raw, PipelineStepConfigurationInterface $step): mixed
    {
        return trim($raw);
    }
}
