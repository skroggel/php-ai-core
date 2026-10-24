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

namespace Madj2k\AiCore\Assistant\Context\Retrieval;

use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup;

/**
 * Class RetrievalPlan
 *
 * Holds the optional runtime selection of retrieval targets. The default is
 * deliberately permissive so existing pipelines keep all retrievals enabled.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class RetrievalPlan
{
    /**
     * Constructor.
     *
     * @param string $mode Plan mode: all, selected, all_except or none.
     * @param array<int,string> $targets Selected target identifiers.
     * @param array<string,mixed> $metadata Selector metadata.
     * @param \Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup|null $payloadFilter Dynamic payload filter.
     * @param array<int,string> $payloadRetrievers Retriever identifiers targeted by the payload filter.
     */
    public function __construct(
        public string $mode = 'all',
        public array $targets = [],
        public array $metadata = [],
        public ?FilterGroup $payloadFilter = null,
        public array $payloadRetrievers = [],
    ) {
    }

    /**
     * Determines whether one retrieval target may execute.
     *
     * @param string $identifier Retrieval identifier.
     * @return bool Execution flag.
     */
    public function allows(string $identifier): bool
    {
        return match ($this->mode) {
            'selected' => in_array($identifier, $this->targets, true),
            'all_except' => !in_array($identifier, $this->targets, true),
            'none' => false,
            default => true,
        };
    }
}
