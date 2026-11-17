<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant\Presentation\UIComponents;

use Madj2k\AiCore\Assistant\Presentation\UIComponents\Definition;
use PHPUnit\Framework\TestCase;

/**
 * Class DefinitionTest
 *
 * Verifies frontend-neutral UI component definitions, including custom elements.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class DefinitionTest extends TestCase
{
    /**
     * Verifies that custom element metadata is exported to the frontend.
     *
     * @return void
     */
    public function testExportsCustomElementMetadata(): void
    {
        $definition = new Definition(
            identifier: 'card_slider',
            description: 'Card slider',
            template: '<div>{{text}}</div>',
            renderMode: 'block',
            tagName: 'custom-card-slider',
        );

        $serialized = $definition->toArray();

        self::assertSame('block', $serialized['renderMode']);
        self::assertSame('custom-card-slider', $serialized['tagName']);
    }

    /**
     * Verifies that definition copies preserve custom element metadata.
     *
     * @return void
     */
    public function testPreservesCustomElementMetadataWhenAddingCssClass(): void
    {
        $definition = new Definition(
            identifier: 'card_slider',
            description: 'Card slider',
            renderMode: 'block',
            tagName: 'custom-card-slider',
        );

        $copy = $definition->withCssClass('custom-slider');

        self::assertSame('block', $copy->renderMode);
        self::assertSame('custom-card-slider', $copy->tagName);
        self::assertSame('custom-slider', $copy->cssClass);
    }
}
