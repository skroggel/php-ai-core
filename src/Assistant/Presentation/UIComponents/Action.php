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

namespace Madj2k\AiCore\Assistant\Presentation\UIComponents;

/**
 * Class Action
 *
 * Describes one interaction that can be triggered by a UI component.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class Action
{
    /**
     * Constructor.
     *
     * @param string $identifier Action identifier.
     * @param string $promptTemplate Prompt template sent as a normal user prompt.
     * @param array<int,string> $placeholders Allowed placeholder names.
     * @param string $label Visible button label.
     * @param string $type Action type: prompt or link.
     * @param string $url Link target for link actions.
     */
    public function __construct(
        public string $identifier,
        public string $promptTemplate,
        public array $placeholders = [],
        public string $label = '',
        public string $type = 'prompt',
        public string $url = '',
    ) {
    }
}
