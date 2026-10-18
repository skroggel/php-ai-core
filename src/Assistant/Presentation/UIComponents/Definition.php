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

namespace Madj2k\AiCore\Assistant\Presentation\UIComponents;

/**
 * Class Definition
 *
 * Defines a renderable and interactive assistant UI component independently
 * of the content management system or frontend framework providing it.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class Definition
{
    /**
     * Constructor.
     *
     * @param string $identifier Configured component reference identifier.
     * @param string $description Description included in the LLM instructions.
     * @param string $template Framework-specific rendering template.
     * @param array<int,Action> $actions Allowed component actions.
     * @param array<string,mixed> $dataSchema Schema for the component payload.
     * @param array<int,string> $placeholders Component and context placeholders.
     * @param string $cssClass Additional CSS class for the component wrapper.
     * @param string $title Human-readable component title.
     * @param string $renderMode Component rendering mode: inline or block.
     * @param string $type Technical component type, such as buttons or link.
     * @param string $usage Prompt usage template. Supports {type} and {identifier} placeholders.
     */
    public function __construct(
        public string $identifier,
        public string $description,
        public string $template = '',
        public array $actions = [],
        public array $dataSchema = [],
        public array $placeholders = [],
        public string $cssClass = '',
        public string $title = '',
        public string $renderMode = 'block',
        public string $type = '',
        public string $usage = '',
    ) {
    }

    /**
     * Returns the definition in a frontend-neutral array representation.
     *
     * @return array<string,mixed> Serialized definition.
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'type' => $this->type !== '' ? $this->type : $this->identifier,
            'usage' => $this->usage,
            'template' => $this->template,
            'actions' => array_map(
                static fn (Action $action): array => [
                    'identifier' => $action->identifier,
                    'promptTemplate' => $action->promptTemplate,
                    'placeholders' => $action->placeholders,
                    'label' => $action->label,
                    'type' => $action->type,
                    'url' => $action->url,
                ],
                $this->actions,
            ),
            'dataSchema' => $this->dataSchema,
            'placeholders' => $this->placeholders,
            'cssClass' => $this->cssClass,
            'title' => $this->title,
            'renderMode' => $this->renderMode,
        ];
    }

    /**
     * Returns a copy with a different action collection.
     *
     * @param array<int,Action> $actions Actions.
     * @return self Definition copy.
     */
    public function withActions(array $actions): self
    {
        return new self(
            $this->identifier,
            $this->description,
            $this->template,
            $actions,
            $this->dataSchema,
            $this->placeholders,
            $this->cssClass,
            $this->title,
            $this->renderMode,
            $this->type,
            $this->usage,
        );
    }

    /**
     * Returns a copy with a different wrapper CSS class.
     *
     * @param string $cssClass CSS class.
     * @return self Definition copy.
     */
    public function withCssClass(string $cssClass): self
    {
        $cssClass = trim($cssClass);
        if ($cssClass === '') {
            return $this;
        }

        $classes = array_values(array_unique(array_filter(array_merge(
            preg_split('/\s+/', $this->cssClass) ?: [],
            preg_split('/\s+/', $cssClass) ?: [],
        ))));

        return new self(
            $this->identifier,
            $this->description,
            $this->template,
            $this->actions,
            $this->dataSchema,
            $this->placeholders,
            implode(' ', $classes),
            $this->title,
            $this->renderMode,
            $this->type,
            $this->usage,
        );
    }

    /**
     * Returns a copy with a different human-readable title.
     *
     * @param string $title Component title.
     * @return self Definition copy.
     */
    public function withTitle(string $title): self
    {
        return new self(
            $this->identifier,
            $this->description,
            $this->template,
            $this->actions,
            $this->dataSchema,
            $this->placeholders,
            $this->cssClass,
            trim($title),
            $this->renderMode,
            $this->type,
            $this->usage,
        );
    }

    /**
     * Returns a copy with a different technical identifier.
     *
     * @param string $identifier Component identifier.
     * @return self Definition copy.
     */
    public function withIdentifier(string $identifier): self
    {
        return new self(
            trim($identifier),
            $this->description,
            $this->template,
            $this->actions,
            $this->dataSchema,
            $this->placeholders,
            $this->cssClass,
            $this->title,
            $this->renderMode,
            $this->type,
            $this->usage,
        );
    }

    /**
     * Returns a copy with a different technical component type.
     *
     * @param string $type Technical component type.
     * @return self Definition copy.
     */
    public function withType(string $type): self
    {
        return new self(
            $this->identifier,
            $this->description,
            $this->template,
            $this->actions,
            $this->dataSchema,
            $this->placeholders,
            $this->cssClass,
            $this->title,
            $this->renderMode,
            trim($type),
        );
    }

    /**
     * Applies non-empty configured overrides to this definition.
     *
     * @param array<string,mixed> $overrides Override values.
     * @return self Definition copy.
     */
    public function withOverrides(array $overrides): self
    {
        return new self(
            $this->identifier,
            trim((string)($overrides['description'] ?? '')) !== ''
                ? trim((string)$overrides['description'])
                : $this->description,
            trim((string)($overrides['template'] ?? '')) !== ''
                ? (string)$overrides['template']
                : $this->template,
            is_array($overrides['actions'] ?? null) && $overrides['actions'] !== []
                ? $overrides['actions']
                : $this->actions,
            is_array($overrides['dataSchema'] ?? null) && $overrides['dataSchema'] !== []
                ? $overrides['dataSchema']
                : $this->dataSchema,
            is_array($overrides['placeholders'] ?? null) && $overrides['placeholders'] !== []
                ? $overrides['placeholders']
                : $this->placeholders,
            $this->cssClass,
            $this->title,
            $this->renderMode,
            $this->type,
            $this->usage,
        )->withCssClass((string)($overrides['cssClass'] ?? ''));
    }
}
