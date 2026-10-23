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

namespace Madj2k\AiCore\Assistant\Prompt\Context;

/**
 * Class PromptSection
 *
 * Represents one structured section of a prompt context before final formatting.
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class PromptSection
{
    /**
     * Constructor.
     *
     * @param string $title Section title.
     * @param string $content Section content.
     * @param int|null $priority Sorting priority for top-level sections.
     * @param array<int, self> $sections Nested prompt sections.
     */
    public function __construct(
        public string $title,
        public string $content,
        public ?int $priority = null,
        /** @var array<int, self> */
        public array $sections = [],
    ) {
    }


    /**
     * Returns whether the section has no usable title or content.
     *
     * @return bool Empty section flag.
     */
    public function isEmpty(): bool
    {
        return trim($this->title) === ''
            || (trim($this->content) === '' && $this->sections === []);
    }


}
