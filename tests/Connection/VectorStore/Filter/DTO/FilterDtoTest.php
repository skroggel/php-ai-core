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

namespace Madj2k\AiCore\Tests\Connection\VectorStore\Filter\DTO;

use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterCondition;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterOperator;
use Madj2k\AiCore\Connection\VectorStore\DTO\VectorSearchRequest;
use Madj2k\AiCore\Connection\VectorStore\Filter\IdentityFilterValueResolver;
use PHPUnit\Framework\TestCase;

/**
 * Class FilterDtoTest
 *
 * Tests the generic vector-store filter DTOs and request integration.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore\Tests
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class FilterDtoTest extends TestCase
{
    /**
     * Tests that conditions are grouped and attached to a vector request.
     *
     * @return void
     */
    public function testAttachesFilterGroupToSearchRequest(): void
    {
        $filter = new FilterGroup(conditions: [
            new FilterCondition('meta.document_type', FilterOperator::Equals, 'faq'),
            new FilterCondition('meta.language', FilterOperator::In, ['de', 'en']),
        ]);
        $request = new VectorSearchRequest(filter: $filter);

        self::assertSame($filter, $request->getFilter());
        self::assertFalse($filter->isEmpty());
        self::assertSame(FilterOperator::In, $filter->conditions[1]->operator);
    }

    /**
     * Tests that the default resolver does not change a filter.
     *
     * @return void
     */
    public function testIdentityResolverKeepsFilterUnchanged(): void
    {
        $filter = new FilterGroup(conditions: [
            new FilterCondition('meta.language', FilterOperator::Equals, 'de'),
        ]);
        $context = (new \ReflectionClass(Context::class))->newInstanceWithoutConstructor();

        self::assertSame($filter, (new IdentityFilterValueResolver())->resolve($filter, $context));
    }
}
