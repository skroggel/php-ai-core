<?php
declare(strict_types=1);

/*
 * This file is part of madj2k\ai-core
 *
 * Copyright (C) 2026 Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Madj2k\AiCore\Assistant\Contract;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Contract\DecisionContract\DecisionContractInterface;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;

/**
 * Resolves prompt-aware contracts by identifier.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class ContractRegistry
{
    /**
     * @var array<string,ContractInterface>
     */
    private array $contracts = [];

    /**
     * Constructor.
     *
     * @param iterable<ContractInterface> $contracts Registered contracts.
     */
    public function __construct(iterable $contracts)
    {
        foreach ($contracts as $contract) {
            $this->contracts[$contract->getIdentifier()] = $contract;
        }
    }

    /**
     * Returns a contract by identifier.
     *
     * @param string $identifier Contract identifier.
     * @return ContractInterface Contract.
     * @throws \InvalidArgumentException When the contract is unknown.
     */
    public function get(string $identifier): ContractInterface
    {
        return $this->contracts[$identifier]
            ?? throw new \InvalidArgumentException(sprintf('Unknown contract "%s".', $identifier));
    }

    /**
     * Returns all registered contracts.
     *
     * @return array<string,ContractInterface> Contracts keyed by identifier.
     */
    public function all(): array
    {
        return $this->contracts;
    }

    /**
     * Resolves the configured or applicable contract for a pipeline step.
     *
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return ContractInterface Contract.
     * @throws \InvalidArgumentException When the configured contract is unknown or unsupported.
     */
    public function resolveForStep(PipelineStepConfigurationInterface $step): ContractInterface
    {
        if (in_array($step->getType(), [
            AssistantPipelineProcessorType::IntentSelector,
            AssistantPipelineProcessorType::QueryOptimizer,
        ], true)) {
            $selected = $step->getDecisionContracts();
            $parts = [];
            foreach ($this->contracts as $contract) {
                if (!$contract instanceof DecisionContractInterface) {
                    continue;
                }
                if ($selected !== [] && !in_array($contract->getIdentifier(), $selected, true)) {
                    continue;
                }
                $parts[] = $contract;
            }

            return new \Madj2k\AiCore\Assistant\Contract\DecisionContract($parts);
        }

        foreach ($this->contracts as $contract) {
            if ($contract->getIdentifier() === 'default') {
                continue;
            }
            if ($contract->supports($step->getType())) {
                return $contract;
            }
        }

        if (isset($this->contracts['default'])) {
            return $this->contracts['default'];
        }

        throw new \InvalidArgumentException(sprintf(
            'No contract supports processor type "%s".',
            $step->getType()->value,
        ));
    }
}
