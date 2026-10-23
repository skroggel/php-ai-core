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
 * Class Buttons
 *
 * Provides the default configurable button group definition.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class Buttons
{
    /**
     * Returns the default definition.
     *
     * @return \Madj2k\AiCore\Assistant\Presentation\UIComponents\Definition
     */
    public static function definition(): Definition
    {
        return new Definition(
            'buttons',
            'Displays configured actions as links or buttons. Supply variable content only through its declared data fields.',
            '<div class="ai-buttons">{{#actions}}{{#url}}<a href="{{url}}" target="_blank" rel="noopener noreferrer">{{label}}</a>{{/url}}{{#promptTemplate}}<button type="button" data-ai-action="{{identifier}}">{{label}}</button>{{/promptTemplate}}{{/actions}}</div>',
            [],
            [],
            [],
            'ai-ui-buttons',
        );
    }
}
