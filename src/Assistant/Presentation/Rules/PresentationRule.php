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

namespace Madj2k\AiCore\Assistant\Presentation\Rules;


/**
 * Interface PresentationRule
 *
 * Reusable, framework-independent presentation instructions.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class PresentationRule
{
    /**
     * @param string $identifier Stable rule identifier.
     * @param string $content Rule content inserted into prompts.
     * @param string $title Human-readable title.
     * @param array<int,string> $references Nested rule identifiers.
     */
    public function __construct(
        public string $identifier,
        public string $content,
        public string $title = '',
        public array $references = [],
    ) {
    }

    /** @return array<string,mixed> Serialized rule. */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'title' => $this->title, 'content' => $this->content, 'references' => $this->references];
    }
}
