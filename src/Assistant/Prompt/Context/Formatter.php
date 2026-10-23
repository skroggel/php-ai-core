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
 * Class Formatter
 *
 * Formats prompt sections into the plain-text prompt context.
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class Formatter
{

    public const TOP_LEVEL = 'top-level';

    public const SUB_SECTION = 'sub-section';

    public const NESTED = 'nested';


    /**
     * Formats prompt sections into a plain-text prompt context.
     *
     * @param array<int, \Madj2k\AiCore\Assistant\Prompt\Context\PromptSection> $sections Prompt sections.
     * @return string Formatted prompt context.
     */
    public function format(array $sections): string
    {
        $parts = [];

        foreach ($sections as $section) {
            if (!$section instanceof PromptSection || $section->isEmpty()) {
                continue;
            }

            $parts[] = $this->formatSection($section);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Formats one prompt section and its nested sections recursively.
     *
     * @param \Madj2k\AiCore\Assistant\Prompt\Context\PromptSection $section Prompt section.
     * @param int $depth Nesting depth.
     * @return string Formatted section.
     */
    private function formatSection(PromptSection $section, int $depth = 0): string
    {
        $parts = [];
        if (trim($section->content) !== '') {
            $parts[] = trim($section->content);
        }

        foreach ($section->sections as $nestedSection) {
            if (!$nestedSection instanceof PromptSection || $nestedSection->isEmpty()) {
                continue;
            }
            $parts[] = $this->formatSection($nestedSection, $depth + 1);
        }

        return $this->boundary(
            $section->title,
            implode("\n\n", $parts),
            match ($depth) {
                0 => self::TOP_LEVEL,
                1 => self::SUB_SECTION,
                default => self::NESTED,
            },
        );
    }

    /**
     * Formats one named prompt boundary.
     *
     * @param string $name Boundary name.
     * @param string $content Boundary content.
     * @param string $level Boundary level.
     * @return string Formatted boundary.
     */
    public function boundary(string $name, string $content = '', string $level = self::TOP_LEVEL): string
    {
        $name = trim($name);
        $content = trim($content);
        if ($name === '') {
            return $content;
        }

        $prefix = match ($level) {
            self::SUB_SECTION => '====',
            self::NESTED => '-----',
            default => '####',
        };
        $lines = [$prefix . ' BEGIN ' . $name . ' ' . $prefix];
        if ($content !== '') {
            $lines[] = $content;
        }
        $lines[] = $prefix . ' END ' . $name . ' ' . $prefix;

        return implode("\n", $lines);
    }

    /**
     * Formats a nested typed entry such as a presentation rule or retrieval.
     *
     * @param string $type
     * @param string $identifier
     * @param string $content
     * @return string
     */
    public function entry(string $type, string $identifier, string $content): string
    {
        return $this->boundary(trim($type) . ': ' . trim($identifier), $content, self::NESTED);
    }
}
