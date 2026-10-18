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

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Context\Context;

/**
 * Class PresentationRuleProvider
 *
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class PresentationRuleProvider implements PresentationRuleProviderInterface
{

    /** @var array<string,PresentationRule> */
    private array $rules;

    /**
     * Rules indexed by identifier.
     * @param iterable<PresentationRule> $rules
     */
    public function __construct(iterable $rules = [])
    {
        $resolved = [];
        foreach ($rules as $rule) {
            if ($rule instanceof PresentationRule && trim($rule->identifier) !== '') {
                $resolved[trim($rule->identifier)] = $rule;
            }
        }
        $this->rules = $resolved;
    }

    /**
     * @param string $identifier
     * @return \Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRule|null
     */
    public function resolve(string $identifier): ?PresentationRule
    {
        return $this->rules[trim($identifier)] ?? null;
    }

    public function getRules(Context $context, PipelineStepConfigurationInterface $step): array
    {
        $plan = $context->getPresentationRulePlan();
        if ($plan->mode !== 'all') {
            return array_values(array_filter(
                $this->getAll(),
                static fn (PresentationRule $rule): bool => $plan->allows($rule->identifier),
            ));
        }

        $rules = [];
        foreach ($step->getPresentationRuleIdentifiers() as $identifier) {
            $rule = $this->resolve($identifier);
            if ($rule !== null && $context->getPresentationRulePlan()->allows($rule->identifier)) {
                $rules[] = $rule;
            }
        }
        return $rules;
    }

    public function getAll(): array
    {
        return array_values($this->rules);
    }
}
