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
use Madj2k\AiCore\Assistant\DTO\QualityGateDecision;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;

/**
 * Parses the strict KEEP/replacement sentinel contract used by the Quality Gate.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class QualityGateContract implements ContractInterface
{
    public const string KEEP_SENTINEL = 'KEEP';

    /** @inheritDoc */
    public function getIdentifier(): string
    {
        return 'quality_gate';
    }

    /** @inheritDoc */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return $type === AssistantPipelineProcessorType::QualityGate;
    }

    /** @inheritDoc */
    public function getPromptInstructions(?PipelineStepConfigurationInterface $step = null): string
    {
        return 'Return exactly ' . self::KEEP_SENTINEL . ' if the existing answer candidate is fully correct. '
            . 'Otherwise return only the complete corrected answer, including all required UI markers. '
            . 'Do not return JSON, explanations, prefixes, suffixes or partial corrections.';
    }

    /** @inheritDoc */
    public function parse(string $raw, PipelineStepConfigurationInterface $step): mixed
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        return $value === self::KEEP_SENTINEL
            ? new QualityGateDecision('keep', null)
            : new QualityGateDecision('replace', $value);
    }

    /**
     * Determines whether a streamed prefix can still become the keep sentinel.
     *
     * @param string $value Trimmed or left-trimmed response prefix.
     * @return bool Whether the prefix can still become KEEP.
     */
    public function isKeepPrefix(string $value): bool
    {
        $value = rtrim($value);

        return $value === '' || str_starts_with(self::KEEP_SENTINEL, $value);
    }
}
