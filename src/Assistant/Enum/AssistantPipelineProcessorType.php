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

namespace Madj2k\AiCore\Assistant\Enum;

/**
 * Enum AssistantPipelineProcessorType
 *
 * Identifies the semantic role of a processor within an assistant pipeline.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
enum AssistantPipelineProcessorType: string
{
    case QueryOptimizer = 'query_optimizer';
    case Retriever = 'retriever';
    case IntentSelector = 'intent_selector';
    case ContextOptimizer = 'context_optimizer';
    case AnswerGenerator = 'answer_generator';
    case QualityGate = 'quality_gate';
    case Memory = 'memory';

    /**
     * Returns a human-readable processor type label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::QueryOptimizer => 'Query optimizer',
            self::Retriever => 'Retriever',
            self::IntentSelector => 'Intent selector',
            self::ContextOptimizer => 'Context optimizer',
            self::AnswerGenerator => 'Answer generator',
            self::QualityGate => 'Quality gate',
            self::Memory => 'Memory',
        };
    }
}
