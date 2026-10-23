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
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalPlan;
use Madj2k\AiCore\Assistant\Context\Presentation\PresentationRulePlan;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Log\PipelineLogMetaData;
use Madj2k\AiCore\Assistant\Log\PipelineLoggerInterface;
use Madj2k\AiCore\Assistant\Pipeline\Processor\AbstractLlmProcessor;
use Madj2k\AiCore\Assistant\Prompt\PromptBuilder;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
/**
 * Class IntentSelectionProcessor
 *
 * Uses a fixed JSON contract to select retrieval targets and presentation rules.
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

    /** @return string Processor identifier. */
    public function getIdentifier(): string {
        return 'aiassistant.intent_selector.default'; }

    /** @param \Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType $type Step type. */
    public function supports(AssistantPipelineProcessorType $type): bool {
        return $type === AssistantPipelineProcessorType::IntentSelector;
    }

    /** @param \Madj2k\AiCore\Assistant\Context\Context $context Assistant context. @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step. */
    public function canProcess(Context $context, PipelineStepConfigurationInterface $step): bool { return trim($context->getCurrentQuery()) !== ''; }

    /**
     * Parses the fixed selector contract and stores it in the context.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Assistant context.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @param \Madj2k\AiCore\Assistant\Log\PipelineLogMetaData|null $logContext Optional pipeline log context.
     * @return void
     */
    public function process(Context $context, PipelineStepConfigurationInterface $step, ?PipelineLogMetaData $logContext = null): void
    {
        $raw = $this->callAi($context, $this->promptBuilder->buildMessages($context, $step), $step, $logContext);
        $json = $this->decodeSelection($raw);
        if (!is_array($json)) { return; }
        $retrieval = is_array($json['retrieval'] ?? null) ? $json['retrieval'] : [];
        $presentationRules = is_array($json['presentationRules'] ?? null) ? $json['presentationRules'] : [];
        [$retrievalMode, $targets] = $this->selection($retrieval, 'targets');
        [$rulesMode, $rules] = $this->selection($presentationRules, 'rules');
        $metadata = is_array($json['metadata'] ?? null) ? $json['metadata'] : [];
        $context->setRetrievalPlan(new RetrievalPlan($retrievalMode, $targets, $metadata));
        $context->setPresentationRulePlan(new PresentationRulePlan($rulesMode, $rules, $metadata));
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

    /** @return array{string,array<int,string>} */
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
}
