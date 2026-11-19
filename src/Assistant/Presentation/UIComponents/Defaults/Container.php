<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Assistant\Presentation\UIComponents\Defaults;

use Madj2k\AiCore\Assistant\Presentation\UIComponents\Definition;

/**
 * Provides the built-in structural container definition.
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
            dataSchema: ['class' => ['type' => 'string']],
            placeholders: ['class'],
            cssClass: 'ai-ui-container',
            title: 'Container',
            renderMode: 'container',
        );
    }
}
