<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Assistant\DTO;

/**
 * Defines the target message role for generated prompt sections.
 *
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
enum PromptRole: string
{
    case System = 'system';
    case User = 'user';
}
