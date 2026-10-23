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

namespace Madj2k\AiCore\Assistant\Context\Presentation;

/**
 * Class RetrievalPlan
 *
 * Holds the optional runtime selection of presentation-rules. The default is
 * deliberately permissive so existing pipelines keep all presentation-rules enabled.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class PresentationRulePlan
{
    /**
     * Constructor.
     *
     * @param string $mode
     * @param array $rules
     * @param array $metadata
     */
    public function __construct(
        public string $mode = 'all',
        public array $rules = [],
        public array $metadata = [],
    ) {
    }

    /**
     * Determines whether one presentationRule may execute.
     *
     * @param string $identifier
     * @return bool
     */
    public function allows(string $identifier): bool
    {
        return match ($this->mode) {
            'selected' => in_array($identifier, $this->rules, true),
            'all_except' => !in_array($identifier, $this->rules, true),
            'none' => false,
            default => true,
        };
    }
}
