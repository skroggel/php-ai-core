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
namespace Madj2k\AiCore\Assistant\Pipeline\Processor\Llm;

use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Enum\IntentSelectionOutputType;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalPlan;
use Madj2k\AiCore\Assistant\Context\Presentation\PresentationRulePlan;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Pipeline\Processor\AbstractLlmProcessor;
use Madj2k\AiCore\Assistant\Prompt\PromptBuilder;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterCondition;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterConjunction;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterOperator;
/**
 * Class IntentSelectionProcessor
 *
 * Uses a configurable JSON contract to select retrieval targets, presentation
 * rules and optional retriever payload filters.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class IntentSelectionProcessor extends AbstractLlmProcessor
{
    /**
     * Constructor.
     *
     * @param \Madj2k\AiCore\Connection\Resolver\AiConnectorResolver $aiConnectorResolver AI connector resolver.
     * @param \Madj2k\AiCore\Assistant\Prompt\PromptBuilder $promptBuilder Prompt builder.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface $pipelineLogger Pipeline logger.
     */
    public function __construct(AiConnectorResolver $aiConnectorResolver, PromptBuilder $promptBuilder, PipelineLoggerInterface $pipelineLogger)
    {
        parent::__construct($aiConnectorResolver, $promptBuilder, $pipelineLogger);
    }

    /**
     * @return string Processor identifier.
     */
    public function getIdentifier(): string
    {
        return 'aiassistant.intent_selector.default';
    }

    /**
     * @param \Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType $type Step type.
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return $type === AssistantPipelineProcessorType::IntentSelector;
    }

    /**
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Assistant context.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     */
    public function canProcess(Context $context, PipelineStepConfigurationInterface $step): bool
    {
        return trim($context->getCurrentQuery()) !== '';
    }

    /**
     * Parses the fixed selector contract and stores it in the context.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Assistant context.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLogMetaData|null $logContext Optional pipeline log context.
     * @return void
     * @throws \Madj2k\AiCore\Exception\AppException
     * @throws \Madj2k\AiCore\Exception\AssistantException
     */
    public function process(Context $context, PipelineStepConfigurationInterface $step, ?PipelineLogMetaData $logContext = null): void
    {
        $raw = $this->callAi($context, $this->promptBuilder->buildMessages($context, $step), $step, $logContext);
        $json = $this->decodeSelection($raw);
        if (!is_array($json)) {
            return;
        }
        $outputTypes = $this->resolveOutputTypes($step);
        $metadata = is_array($json['metadata'] ?? null) ? $json['metadata'] : [];

        if (in_array(IntentSelectionOutputType::Retriever, $outputTypes, true)) {
            $retrieval = is_array($json['retrieval'] ?? null) ? $json['retrieval'] : [];
            [$retrievalMode, $targets] = $this->selection($retrieval, 'targets');
            $context->setRetrievalPlan(new RetrievalPlan($retrievalMode, $targets, $metadata));
        }

        if (in_array(IntentSelectionOutputType::PresentationRules, $outputTypes, true)) {
            $presentationRules = is_array($json['presentationRules'] ?? null)
                ? $json['presentationRules']
                : [];
            [$rulesMode, $rules] = $this->selection($presentationRules, 'rules');
            $context->setPresentationRulePlan(new PresentationRulePlan($rulesMode, $rules, $metadata));
        }

        if (in_array(IntentSelectionOutputType::RetrieverPayload, $outputTypes, true)) {
            $payloadFilter = $this->createPayloadFilter($json, $step);
            if ($payloadFilter !== null) {
                $retrievalPlan = $context->getRetrievalPlan();
                $context->setRetrievalPlan(new RetrievalPlan(
                    $retrievalPlan->mode,
                    $retrievalPlan->targets,
                    $retrievalPlan->metadata,
                    $payloadFilter,
                    $step->getIntentSelectionPayloadRetrievers(),
                ));
            }
        }
    }

    /**
     * Decodes plain, fenced or embedded JSON returned by the selector.
     *
     * @param string $raw Raw model output.
     * @return array<string,mixed>|null Decoded selector result.
     */
    private function decodeSelection(string $raw): ?array
    {
        $value = trim($raw);
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
     * @return array{string,array<int,string>}
     */
    private function selection(array $area, string $key): array
    {
        $mode = in_array($area['mode'] ?? null, ['all', 'selected', 'all_except', 'none'], true)
            ? $area['mode']
            : 'all';
        $values = is_array($area[$key] ?? null) ? $area[$key] : [];
        $values = array_map(static fn ($value): string => trim((string)$value), $values);
        if (in_array($mode, ['all', 'none'], true)) {
            $values = [];
        }
        return [$mode, array_values(array_filter($values, static fn (string $value): bool => $value !== ''))];
    }

    /**
     * Resolves configured selector output areas.
     *
     * Empty or unavailable legacy configuration enables the two original
     * output areas and leaves payload filtering disabled.
     *
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return array<int,\Madj2k\AiCore\Assistant\Enum\IntentSelectionOutputType> Enabled output types.
     */
    private function resolveOutputTypes(PipelineStepConfigurationInterface $step): array
    {
        $types = $step->getIntentSelectionOutputTypes();
        if (!is_array($types) || $types === []) {
            return [IntentSelectionOutputType::Retriever, IntentSelectionOutputType::PresentationRules];
        }

        return array_values(array_filter(
            $types,
            static fn (mixed $type): bool => $type instanceof IntentSelectionOutputType,
        ));
    }

    /**
     * Creates a dynamic payload filter from the selector response.
     *
     * @param array<string,mixed> $json Decoded selector response.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return \Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterGroup|null Payload filter or null when no value was returned.
     */
    private function createPayloadFilter(
        array $json,
        PipelineStepConfigurationInterface $step,
    ): ?FilterGroup {
        $field = trim($step->getIntentSelectionPayloadField());
        $payload = is_array($json['retrieverPayload'] ?? null) ? $json['retrieverPayload'] : [];
        $values = $payload['values'] ?? [];
        $values = is_array($values) ? $values : [$values];
        $values = array_values(array_filter($values, static fn (mixed $value): bool => $value !== ''));

        if ($field === '' || $values === []) {
            return null;
        }

        $conjunction = FilterConjunction::And;
        $configuredConjunction = $step->getIntentSelectionPayloadConjunction();
        if ($configuredConjunction instanceof FilterConjunction) {
            $conjunction = $configuredConjunction;
        }

        $conditions = [];
        foreach ($values as $value) {
            $conditions[] = new FilterCondition($field, FilterOperator::In, [$value]);
        }

        return new FilterGroup($conjunction, $conditions);
    }
}
