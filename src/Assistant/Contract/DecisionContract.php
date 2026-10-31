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
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Context\Presentation\PresentationRulePlan;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalPlan;
use Madj2k\AiCore\Assistant\DTO\DecisionResult;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterCondition;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterOperator;

/**
 * Parses the shared JSON contract used by intent selection and query optimization.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class DecisionContract implements ContractInterface
{
    /**
     * Constructor.
     *
     * @param iterable<\Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface> $parts Registered decision contract parts.
     */
    public function __construct(private iterable $parts)
    {
    }


    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'pipeline_decision';
    }


    /**
     * @inheritDoc
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return in_array($type, [
            AssistantPipelineProcessorType::IntentSelector,
            AssistantPipelineProcessorType::QueryOptimizer,
        ], true);
    }


    /**
     * @inheritDoc
     */
    public function getPromptInstructions(?PipelineStepConfigurationInterface $step = null): string
    {
        $outputs = $step?->getContracts() ?? ['retrieval', 'presentationRules'];
        if ($step?->getType() === AssistantPipelineProcessorType::QueryOptimizer) {
            $outputs = ['retrieval'];
        }
        if ($outputs === []) {
            $outputs = [
                'retrieval',
                'presentationRules',
            ];
        }

        $parts = [];
        foreach ($this->parts as $part) {
            $identifier = $part->getIdentifier();
            $enabled = match ($identifier) {
                'retrieval' => in_array('retrieval', $outputs, true),
                'presentationRules' => in_array('presentationRules', $outputs, true),
                'retrieverPayload' => in_array('retrieverPayload', $outputs, true),
                default => false,
            };
            if ($enabled) {
                $parts[] = $part->getPromptInstructions($step);
            }
        }

        $contract = [];
        if ($step?->getType() === AssistantPipelineProcessorType::QueryOptimizer) {
            $contract['query'] = '';
        }
        if (in_array('retrieval', $outputs, true)) {
            $contract['retrieval'] = ['mode' => 'all', 'targets' => []];
        }
        if (in_array('presentationRules', $outputs, true)) {
            $contract['presentationRules'] = ['mode' => 'all', 'rules' => []];
        }
        if (in_array('retrieverPayload', $outputs, true)) {
            $contract['retrieverPayload'] = ['values' => []];
        }
        $contract['metadata'] = new \stdClass();

        $parts[] = 'Return only this JSON structure: ' . json_encode($contract, JSON_UNESCAPED_SLASHES);

        return implode(' ', $parts);
    }


    /**
     * Parses an LLM response.
     *
     * @param string $raw LLM response.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return \Madj2k\AiCore\Assistant\DTO\DecisionResult|null Parsed result.
     */
    public function parse(
        string $raw,
        PipelineStepConfigurationInterface $step,
    ): ?DecisionResult {

        $value = trim($raw);
        $json = $this->decodeJson($value);
        if ($json === null) {
            return null;
        }

        $retrieval = is_array($json['retrieval'] ?? null) ? $json['retrieval'] : [];
        [$retrievalMode, $targets] = $this->parseSelection($retrieval, 'targets');
        $metadata = is_array($json['metadata'] ?? null) ? $json['metadata'] : [];
        $isIntentSelector = $step->getType() === AssistantPipelineProcessorType::IntentSelector;
        $contracts = $step->getContracts();
        $includePresentationRules = $isIntentSelector && in_array('presentationRules', $contracts, true);
        $includePayloadFilter = $isIntentSelector && in_array('retrieverPayload', $contracts, true);
        $payloadFilter = $includePayloadFilter ? $this->parsePayloadFilter($json, $step) : null;
        $payloadRetrievers = $includePayloadFilter
            ? $step->getDecisionPayloadRetrievers()
            : [];

        $retrievalPlan = new RetrievalPlan(
            $retrievalMode,
            $targets,
            $metadata,
            $payloadFilter,
            $payloadRetrievers,
        );
        $presentationPlan = new PresentationRulePlan();
        if ($includePresentationRules) {
            $presentationRules = is_array($json['presentationRules'] ?? null)
                ? $json['presentationRules']
                : [];
            [$rulesMode, $rules] = $this->parseSelection($presentationRules, 'rules');
            $presentationPlan = new PresentationRulePlan($rulesMode, $rules, $metadata);
        }

        return new DecisionResult(
            trim((string)($json['query'] ?? '')),
            $retrievalPlan,
            $presentationPlan,
        );
    }


    /**
     * @param string $value Raw response.
     * @return array<string,mixed>|null Decoded JSON object.
     */
    private function decodeJson(string $value): ?array
    {
        $value = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $value) ?? $value;
        $decoded = json_decode(trim($value), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($value, '{');
        $end = strrpos($value, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($value, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }


    /**
     * @param array<string,mixed> $area Selection area.
     * @param string $key Value key.
     * @return array{0:string,1:array<int,string>} Selection mode and values.
     */
    private function parseSelection(array $area, string $key): array
    {
        $mode = in_array($area['mode'] ?? null, ['all', 'selected', 'all_except', 'none'], true)
            ? (string)$area['mode']
            : 'all';
        $values = is_array($area[$key] ?? null) ? $area[$key] : [];
        $values = array_map(static fn (mixed $value): string => trim((string)$value), $values);
        if (in_array($mode, ['all', 'none'], true)) {
            $values = [];
        }

        return [$mode, array_values(array_filter($values, static fn (string $value): bool => $value !== ''))];
    }


    /**
     * @param array<string,mixed> $json Decoded response.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return \Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup|null Dynamic filter.
     */
    private function parsePayloadFilter(array $json, PipelineStepConfigurationInterface $step): ?FilterGroup
    {
        $field = trim($step->getDecisionPayloadField());
        $payload = is_array($json['retrieverPayload'] ?? null) ? $json['retrieverPayload'] : [];
        $values = is_array($payload['values'] ?? null) ? $payload['values'] : [];
        $values = array_values(array_filter($values, static fn (mixed $value): bool => $value !== ''));
        if ($field === '' || $values === []) {
            return null;
        }

        $conditions = array_map(
            static fn (mixed $value): FilterCondition => new FilterCondition(
                $field,
                FilterOperator::In,
                [$value],
            ),
            $values,
        );

        return new FilterGroup(
            $step->getDecisionPayloadConjunction(),
            $conditions,
        );
    }
}
