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

namespace Madj2k\AiCore\Tests\Assistant\Context\Retrieval;

use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalPlan;
use PHPUnit\Framework\TestCase;

/**
 * Class RetrievalPlanTest
 *
 * Tests default and explicit retrieval selection behavior.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore\Tests
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class RetrievalPlanTest extends TestCase
{
    public function testDefaultPlanAllowsAllTargets(): void
    {
        $plan = new RetrievalPlan();

        self::assertTrue($plan->allows('products'));
        self::assertTrue($plan->allows('faq'));
    }

    public function testSelectedPlanAllowsOnlySelectedTargets(): void
    {
        $plan = new RetrievalPlan('selected', ['products']);

        self::assertTrue($plan->allows('products'));
        self::assertFalse($plan->allows('faq'));
    }

    public function testAllExceptPlanExcludesConfiguredTargets(): void
    {
        $plan = new RetrievalPlan('all_except', ['careers']);

        self::assertTrue($plan->allows('products'));
        self::assertFalse($plan->allows('careers'));
    }
}
