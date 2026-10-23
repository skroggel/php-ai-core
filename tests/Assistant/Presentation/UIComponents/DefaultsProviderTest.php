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

use Madj2k\AiCore\Assistant\Presentation\UIComponents\Action;
use Madj2k\AiCore\Assistant\Presentation\UIComponents\Defaults\Provider;
use PHPUnit\Framework\TestCase;

/**
 * Class DefaultsProviderTest
 *
 * Tests the built-in UI component catalog.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore\Tests
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class DefaultsProviderTest extends TestCase
{
    /**
     * Tests all built-in component definitions are available.
     *
     * @return void
     */
    public function testProvidesAllDefaultComponents(): void
    {
        $provider = new Provider();
        $identifiers = array_map(
            static fn ($definition): string => $definition->identifier,
            $provider->all(),
        );

        self::assertSame([
            'select-list',
            'link-list',
            'link',
            'buttons',
            'progress-indicator',
            'decorative',
            'headline',
        ], $identifiers);
    }

    /**
     * Tests the navigation defaults use real links with safe new-tab behavior.
     *
     * @return void
     */
    public function testProvidesLinkDefaults(): void
    {
        $provider = new Provider();

        self::assertStringContainsString('target="_blank"', $provider->get('link')->template);
        self::assertStringContainsString('rel="noopener noreferrer"', $provider->get('link-list')->template);
        self::assertSame([], $provider->get('link')->actions);
    }

    /**
     * Tests built-in component wrapper classes are defined.
     *
     * @return void
     */
    public function testProvidesDefaultCssClasses(): void
    {
        $provider = new Provider();

        self::assertSame('ai-ui-select-list', $provider->get('select-list')->cssClass);
        self::assertSame('ai-ui-link-list', $provider->get('link-list')->cssClass);
        self::assertSame('ai-ui-link', $provider->get('link')->cssClass);
        self::assertSame('ai-ui-buttons', $provider->get('buttons')->cssClass);
        self::assertSame('ai-ui-progress-indicator', $provider->get('progress-indicator')->cssClass);
        self::assertSame('ai-ui-decorative', $provider->get('decorative')->cssClass);
        self::assertSame('ai-ui-headline', $provider->get('headline')->cssClass);
    }

    /**
     * Tests button actions can be supplied by the configuration provider.
     *
     * @return void
     */
    public function testAddsConfiguredButtonActions(): void
    {
        $provider = new Provider();
        $definition = $provider->get('buttons')->withActions([
            new Action('button-1', 'Continue.', [], 'Continue'),
        ]);

        self::assertSame('buttons', $definition->identifier);
        self::assertSame('Continue', $definition->actions[0]->label);
        self::assertSame('Continue.', $definition->actions[0]->promptTemplate);
        self::assertSame('button-1', $definition->actions[0]->identifier);
    }

    /**
     * Tests metadata is included in the frontend definition payload.
     *
     * @return void
     */
    public function testSerializesTitleAndCssClass(): void
    {
        $definition = (new Provider())
            ->get('buttons')
            ->withTitle('Product choices')
            ->withCssClass('product-choices');

        self::assertSame('Product choices', $definition->toArray()['title']);
        self::assertSame('ai-ui-buttons product-choices', $definition->toArray()['cssClass']);
    }
}
