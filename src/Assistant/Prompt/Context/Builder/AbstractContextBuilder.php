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

namespace Madj2k\AiCore\Assistant\Prompt\Context\Builder;

use Madj2k\AiCore\Assistant\Prompt\Context\PromptSection;
use Madj2k\AiCore\Assistant\Prompt\Context\Formatter;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\DTO\PromptRole;
use Madj2k\AiCore\Assistant\DTO\PromptType;

/**
 * Class AbstractContextBuilder
 *
 * Provides shared helpers for prompt context builders.
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
abstract class AbstractContextBuilder implements ContextBuilderInterface
{
    /**
     * @inheritDoc
     */
    public function getPromptRole(): PromptRole
    {
        return PromptRole::User;
    }

    /**
     * @inheritDoc
     */
    public function getPromptType(): PromptType
    {
        return PromptType::None;
    }

    /**
     * @inheritDoc
     */
    public function getGeneralInstructions(AssistantPipelineProcessorType $type): array
    {
        return [];
    }

    /**
     * Creates a prompt section or returns null for empty section data.
     *
     * @param string $title Section title.
     * @param string $content Section content.
     * @param int $priority Sorting priority.
     * @return \Madj2k\AiCore\Assistant\Prompt\Context\PromptSection|null Prompt section.
     */
    protected function section(string $title, string $content, int $priority): ?PromptSection
    {
        $section = new PromptSection($title, $content, $priority);
        return $section->isEmpty() ? null : $section;
    }

    /**
     * Removes empty section placeholders.
     *
     * @param array<int, \Madj2k\AiCore\Assistant\Prompt\Context\PromptSection|null> $sections Prompt sections.
     * @return array<int, \Madj2k\AiCore\Assistant\Prompt\Context\PromptSection> Filtered prompt sections.
     */
    protected function filterSections(array $sections): array
    {
        return array_values(array_filter(
            $sections,
            static fn (?PromptSection $section): bool => $section instanceof PromptSection && !$section->isEmpty()
        ));
    }

    /**
     * Creates a nested prompt entry.
     *
     * @param string $type Entry type.
     * @param string $identifier Entry identifier.
     * @param string $content Entry content.
     * @return string Formatted entry.
     */
    protected function entry(string $type, string $identifier, string $content): string
    {
        return new Formatter()->entry($type, $identifier, $content);
    }

}
