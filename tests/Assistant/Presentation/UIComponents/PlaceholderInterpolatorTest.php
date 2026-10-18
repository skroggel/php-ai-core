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

namespace Madj2k\AiCore\Tests\Assistant\Presentation\UIComponents;

use Madj2k\AiCore\Assistant\Presentation\UIComponents\PlaceholderInterpolator;
use PHPUnit\Framework\TestCase;

/**
 * Class PlaceholderInterpolatorTest
 *
 * Tests controlled component action prompt interpolation.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore\Tests
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class PlaceholderInterpolatorTest extends TestCase
{
    /**
     * Tests component and context values can be used.
     *
     * @return void
     */
    public function testInterpolatesNestedValues(): void
    {
        $result = (new PlaceholderInterpolator())->interpolate(
            'Show {{label}} in step {{context.currentStep}}.',
            ['label' => 'Food', 'context' => ['currentStep' => 2]],
            ['label', 'context.currentStep'],
        );

        self::assertSame('Show Food in step 2.', $result);
    }

    /**
     * Tests values outside the allowed placeholder list remain untouched.
     *
     * @return void
     */
    public function testDoesNotInterpolateDisallowedValues(): void
    {
        $result = (new PlaceholderInterpolator())->interpolate(
            '{{allowed}} {{notAllowed}}',
            ['allowed' => 'yes', 'notAllowed' => 'no'],
            ['allowed'],
        );

        self::assertSame('yes {{notAllowed}}', $result);
    }
}
