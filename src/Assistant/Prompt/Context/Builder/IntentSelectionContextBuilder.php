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

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalTargetProviderInterface;
use Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRuleProviderInterface;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Enum\IntentSelectionOutputType;
use Madj2k\AiCore\Assistant\Prompt\Context\PromptSection;
use Madj2k\AiCore\Assistant\DTO\PromptRole;

/**
 * Class IntentSelectionContextBuilder
 *
 * Adds available retrieval targets and presentation rules to the LLM context.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class IntentSelectionContextBuilder extends AbstractContextBuilder
{

    /**
     * Constructor.
     *
     * @param RetrievalTargetProviderInterface $targetProvider Retrieval target provider.
     * @param PresentationRuleProviderInterface $presentationRuleProvider Presentation rule provider.
     */
    public function __construct(
        private RetrievalTargetProviderInterface $targetProvider,
        private PresentationRuleProviderInterface $presentationRuleProvider,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function supports(AssistantPipelineProcessorType $type): bool
    {
        return $type === AssistantPipelineProcessorType::IntentSelector;
    }

    /**
     * @inheritDoc
     */
    public function getGeneralInstructions(AssistantPipelineProcessorType $type): array
    {
        if (!$this->supports($type)) {
            return [];
        }

        $outputs = $this->resolveOutputTypes(null);

        return $this->buildGeneralInstructions($outputs);
    }

    /**
     * Builds general selector instructions for the configured output areas.
     *
     * @param array<int,\Madj2k\AiCore\Assistant\Enum\IntentSelectionOutputType> $outputs Output types.
     * @return array<int,string> Prompt instructions.
     */
    private function buildGeneralInstructions(array $outputs): array
    {
        $instructions = [
            'Determine the user intent for the original query. Do not answer the query.',
            'Only produce the configured JSON object. Do not add Markdown fences or explanatory text.',
        ];

        if (in_array(IntentSelectionOutputType::Retriever, $outputs, true)) {
            $instructions[] = 'Select retrieval targets only when the intent requires a restriction; otherwise use mode all.';
            $instructions[] = 'The retrieval area supports mode all, selected, all_except or none. Use only available retrieval identifiers.';
        }

        if (in_array(IntentSelectionOutputType::PresentationRules, $outputs, true)) {
            $instructions[] = 'Select presentation rules only when the intent requires a restriction; otherwise use mode all.';
            $instructions[] = 'The presentationRules area supports mode all, selected, all_except or none. Use only available rule identifiers.';
        }

        if (in_array(IntentSelectionOutputType::RetrieverPayload, $outputs, true)) {
            $instructions[] = $this->buildPayloadInstructions(null);
        }

        $instructions[] = 'For mode all or none, the corresponding list must be empty.';
        $instructions[] = 'Return this JSON structure: ' . $this->buildOutputContract($outputs);

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    public function build(Context $context, PipelineStepConfigurationInterface $step): array
    {
        $outputs = $this->resolveOutputTypes($step);
        $lines = $this->supports($step->getType())
            ? $this->buildGeneralInstructions($outputs)
            : [];

        if (in_array(IntentSelectionOutputType::Retriever, $outputs, true)) {
            foreach ($this->targetProvider->getTargets($context, $step) as $target) {
                $lines[] = sprintf(
                    '- %s: %s',
                    $target->identifier,
                    $target->description !== '' ? $target->description : $target->title,
                );
            }
        }

        if (in_array(IntentSelectionOutputType::PresentationRules, $outputs, true)) {
            $lines[] = '';
            $lines[] = 'Available presentation rules:';
            foreach ($this->presentationRuleProvider->getAll() as $rule) {
                $lines[] = sprintf(
                    '- %s: %s',
                    $rule->identifier,
                    $rule->title !== '' ? $rule->title : $rule->content,
                );
            }
        }

        if (in_array(IntentSelectionOutputType::RetrieverPayload, $outputs, true)) {
            $lines[] = '';
            $lines[] = $this->buildPayloadInstructions($step);
        }
        if ($step->getIntentSelectionInstructions() !== '') {
            $lines[] = '';
            $lines[] = 'Additional selection rules:';
            $lines[] = $step->getIntentSelectionInstructions();
        }
        if ($step->getIntentSelectionMetadata() !== '') {
            $lines[] = '';
            $lines[] = 'Return metadata according to this schema:';
            $lines[] = $step->getIntentSelectionMetadata();
        }

        return [new PromptSection('Available Intent Selections', implode("\n", $lines), 70)];
    }

    /**
     * Resolves configured output types with the legacy default.
     *
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface|null $step Pipeline step.
     * @return array<int,\Madj2k\AiCore\Assistant\Enum\IntentSelectionOutputType> Output types.
     */
    private function resolveOutputTypes(?PipelineStepConfigurationInterface $step): array
    {
        if ($step === null) {
            return [IntentSelectionOutputType::Retriever, IntentSelectionOutputType::PresentationRules];
        }

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
     * Builds the payload filtering instructions.
     *
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface|null $step Pipeline step.
     * @return string Payload filtering instruction.
     */
    private function buildPayloadInstructions(?PipelineStepConfigurationInterface $step): string
    {
        $field = 'the configured payload field';

        if ($step !== null) {
            $configuredField = trim((string)$step->getIntentSelectionPayloadField());
            if ($configuredField !== '') {
                $field = '`' . $configuredField . '`';
            }
        }

        return sprintf(
            'Return retriever payload values in "retrieverPayload":{"values":[...]}. The values are matched against payload field %s.',
            $field,
        );
    }

    /**
     * Builds the exact JSON contract for the selected output areas.
     *
     * @param array<int,\Madj2k\AiCore\Assistant\Enum\IntentSelectionOutputType> $outputs Output types.
     * @return string JSON contract.
     */
    private function buildOutputContract(array $outputs): string
    {
        $contract = [];
        if (in_array(IntentSelectionOutputType::Retriever, $outputs, true)) {
            $contract['retrieval'] = ['mode' => 'all', 'targets' => []];
        }
        if (in_array(IntentSelectionOutputType::PresentationRules, $outputs, true)) {
            $contract['presentationRules'] = ['mode' => 'all', 'rules' => []];
        }
        if (in_array(IntentSelectionOutputType::RetrieverPayload, $outputs, true)) {
            $contract['retrieverPayload'] = ['values' => []];
        }
        $contract['metadata'] = new \stdClass();

        return (string)json_encode($contract, JSON_UNESCAPED_SLASHES);
    }
}
