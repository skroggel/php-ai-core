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

namespace Madj2k\AiCore\Assistant\Presentation\UIComponents\Defaults;

use Madj2k\AiCore\Assistant\Presentation\UIComponents\Definition;

/**
 * Class Provider
 *
 * Provides the built-in UI component definitions.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class Provider
{
    /**
     * Returns one built-in definition.
     *
     * @param string $identifier
     * @return \Madj2k\AiCore\Assistant\Presentation\UIComponents\Definition|null
     */
    public function get(string $identifier): ?Definition
    {
        return match (trim($identifier)) {
            'select-list' => SelectList::definition(),
            'link-list' => LinkList::definition(),
            'link' => Link::definition(),
            'buttons' => Buttons::definition(),
            'progress-indicator' => ProgressIndicator::definition(),
            'decorative' => Decorative::definition(),
            'headline' => Headline::definition(),
            default => null,
        };
    }

    /**
     * Returns all built-in definitions.
     *
     * @return array<int,Definition> Component definitions.
     */
    public function all(): array
    {
        return array_values(array_filter(array_map(
            fn (string $identifier): ?Definition => $this->get($identifier),
            ['select-list', 'link-list', 'link', 'buttons', 'progress-indicator', 'decorative', 'headline'],
        )));
    }
}
