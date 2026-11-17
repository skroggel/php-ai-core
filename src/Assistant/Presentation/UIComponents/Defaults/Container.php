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

namespace Madj2k\AiCore\Assistant\Presentation\UIComponents\Defaults;

use Madj2k\AiCore\Assistant\Presentation\UIComponents\Definition;

/**
 * Class Container
 *
 * Provides the structural container definition.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class Container
{
    /**
     * Returns the structural container definition.
     *
     * @return Definition Container definition.
     */
    public static function definition(): Definition
    {
        return new Definition(
            identifier: 'container',
            description: 'Opens a structural content container for the following grouped content. Emit this component once before the group and never emit a separate closing component; the renderer closes the previous container when a new one starts and closes the last one at the end. Optionally provide a space-separated CSS class list in the data field `class`.',
            template: '',
            dataSchema: ['class' => ['type' => 'string']],
            placeholders: ['class'],
            cssClass: 'ai-ui-container',
            title: 'Container',
            renderMode: 'container',
        );
    }
}
