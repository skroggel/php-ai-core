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

namespace Madj2k\AiCore\Assistant\Export;

use Madj2k\AiCore\Assistant\Configuration\AssistantConfigurationInterface;
use Madj2k\AiCore\Assistant\Configuration\FilterAwarePipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Context\Answer\AnswerState;
use Madj2k\AiCore\Assistant\Context\Assistant\AssistantContext;
use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Context\Request\History;
use Madj2k\AiCore\Assistant\Context\Request\Request;
use Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalResult;
use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Presentation\UIComponents\Definition;
use Madj2k\AiCore\Assistant\Presentation\Rules\PresentationRule;
use Madj2k\AiCore\Assistant\Prompt\Context\Registry\ContextBuilderRegistry;
use Madj2k\AiCore\Assistant\Prompt\Context\Formatter;
use Madj2k\AiCore\Assistant\Prompt\PromptBuilder;

/**
 * Class PipelineMarkdownExporter
 *
 * Exports an assistant profile and its pipeline as an AI-readable Markdown document.
 * Secrets and provider credentials are deliberately excluded.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class PipelineMarkdownExporter
{

    /**
     * @var \Madj2k\AiCore\Assistant\Prompt\Context\Formatter
     */
    private readonly Formatter $promptFormatter;

    /**
     * @var \Madj2k\AiCore\Assistant\Prompt\PromptBuilder
     */
    private readonly PromptBuilder $promptBuilder;


    /**
     * Constructor
     *
     * @param \Madj2k\AiCore\Assistant\Prompt\Context\Registry\ContextBuilderRegistry|null $contextBuilderRegistry
     */
    public function __construct(
        private readonly ?ContextBuilderRegistry $contextBuilderRegistry = null,
    ) {
        $this->promptFormatter = new Formatter();
        $this->promptBuilder = new PromptBuilder(
            $this->contextBuilderRegistry ?? new ContextBuilderRegistry([]),
            $this->promptFormatter,
        );
    }

    /**
     * Exports one assistant configuration.
     *
     * @param AssistantConfigurationInterface $assistant Assistant configuration.
     * @param array<int,Definition> $uiComponents Effective UI component definitions.
     * @param array<int,PresentationRule> $presentationRules Reusable presentation rule definitions.
     * @return string Markdown export.
     */
    public function export(AssistantConfigurationInterface $assistant, array $uiComponents = [], array $presentationRules = []): string
    {
        $lines = [
            '> You are reviewing the configuration of an AI assistant pipeline.',
            '> The document below is an implementation brief, not a user conversation.',
            '> Analyze the assistant identity, behavior, retrieval policy, output rules and every pipeline step together.',
            '> Pay particular attention to processor types, execution stages, include settings, step prompts, history policy and retrieval limits.',
            '> When proposing improvements, preserve the distinction between retrieval, context optimization, answer generation and quality gate responsibilities.',
            '> Do not assume that a later step can repair content that an earlier step has already emitted.',
            '> If the pipeline contains an intent selector step, presentation rules and retrievers may be prefiltered for subsequent steps based on the detected intent; account for this when analyzing the effective prompts and pipeline behavior.',
            '',
            '# Assistant Pipeline Brief',
            '',
            '## Assistant',
            '',
            '- Title: ' . $this->value($assistant->getTitle()),
            '- Name: ' . $this->value($assistant->getAssistantName()),
            '',
            '## Reference Definitions',
        ];
        $definitionStart = count($lines) - 1;

        if ($uiComponents !== []) {
            $lines[] = '';
            $lines[] = '## UI Components';
            foreach ($uiComponents as $component) {
                if (!$component instanceof Definition) {
                    continue;
                }
                $lines[] = '';
                $lines[] = '### ' . $this->value($component->title !== '' ? $component->title : $component->identifier);
                $lines[] = '';
                $lines[] = '- Identifier: `' . $component->identifier . '`';
                $lines[] = '- Description: ' . $this->value($component->description);
                $lines[] = '- Render mode: `' . $component->renderMode . '`';
                $lines[] = '- CSS class: `' . $this->value($component->cssClass) . '`';
                $lines[] = '';
                $lines[] = '#### Template';
                $lines[] = '```html';
                $lines[] = $component->template;
                $lines[] = '```';
                $lines[] = '';
                $lines[] = '#### Data schema';
                $lines[] = '```json';
                $schema = json_encode($component->dataSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $lines[] = is_string($schema) ? $schema : '{}';
                $lines[] = '```';
                $lines[] = '';
                $lines[] = '#### Placeholders';
                $lines[] = implode(', ', $component->placeholders);
                if ($component->actions !== []) {
                    $lines[] = '';
                    $lines[] = '#### Actions';
                    foreach ($component->actions as $action) {
                        $lines[] = '- `' . $action->identifier . '` — label: ' . $this->value($action->label) . ', type: `' . $action->type . '`, prompt: `' . $action->promptTemplate . '`';
                    }
                }
            }
        }

        if ($presentationRules !== []) {
            $lines[] = '';
            $lines[] = '## Presentation Rule Definitions';
            foreach ($presentationRules as $rule) {
                if (!$rule instanceof PresentationRule) {
                    continue;
                }
                $lines[] = '';
                $lines[] = '### ' . $this->value($rule->title !== '' ? $rule->title : $rule->identifier);
                $lines[] = '';
                $lines[] = '- Identifier: `' . $rule->identifier . '`';
                $lines[] = '```text';
                $lines[] = $rule->content;
                $lines[] = '```';
            }
        }

        $referenceDefinitions = array_splice($lines, $definitionStart);
        $lines[] = '';
        $lines[] = '## Pipeline';

        $steps = [];
        foreach ($assistant->getChatPipelineSteps() as $candidate) {
            if ($candidate instanceof PipelineStepConfigurationInterface) {
                $steps[] = $candidate;
            }
        }

        $index = 0;
        foreach ($steps as $step) {
            $index++;
            $lines[] = '';
            $lines[] = '### ' . $index . '. ' . $this->value($step->getTitle());
            $lines[] = '';
            $lines[] = '- Type: `' . $step->getType()->value . '`';
            $lines[] = '- Type label: ' . $this->value($step->getType()->getLabel());
            $lines[] = '- Stage: `' . $step->getStage()->value . '`';
            $lines[] = '- Processor: `' . $this->value($step->getProcessorIdentifier()) . '`';
            $lines[] = '- Failure strategy: `' . $step->getFailureStrategy()->value . '`';
            $lines[] = '';
            if ($step->isLlmStep()) {
                $messages = $this->buildExportMessages($assistant, $step);
                $lines[] = '#### Effective System Prompt';
                $lines[] = '';
                $this->appendTextBlock($lines, $messages[0]['content']);
                $lines[] = '';
                $lines[] = '#### Effective User Prompt';
                $lines[] = '';
                $this->appendTextBlock($lines, $messages[1]['content']);
                $lines[] = '';
            }
            $lines[] = '#### Settings';
            $lines[] = '';
            $lines[] = '- Identity prompt: ' . $this->boolean($step->getIncludeIdentityPrompt());
            $lines[] = '- Behavior rules: ' . $this->boolean($step->getIncludeBehaviorRules());
            $lines[] = '- Retrieval rules: ' . $this->boolean($step->getIncludeRetrievalRules());
            $lines[] = '- Output rules: ' . $this->boolean($step->getIncludeOutputRules());
            $lines[] = '';
            $lines[] = '#### Runtime Settings';
            $lines[] = '';
            $lines[] = '- History mode: `' . $step->getHistoryMode()->value . '`';
            $lines[] = '- History limit: `' . $step->getHistoryLimit() . '`';
            $lines[] = '- Model: `' . $this->value($step->getModel()) . '`';
            $lines[] = '- Temperature: `' . $step->getTemperature() . '`';
            $lines[] = '- Maximum tokens: `' . $step->getMaxTokens() . '`';
            $lines[] = '- Maximum retrieval results: `' . $step->getMaxRetrievalResults() . '`';
            $lines[] = '- Maximum context chunks: `' . $step->getMaxContextChunks() . '`';
            $lines[] = '- Maximum context characters: `' . $step->getMaxContextCharacters() . '`';
            $lines[] = '- Maximum chunks per result: `' . $step->getMaxChunksPerResult() . '`';
            $lines[] = '- Maximum chunk characters: `' . $step->getMaxChunkCharacters() . '`';
            $lines[] = '- Score threshold: `' . $step->getScoreThreshold() . '`';
            $lines[] = '- Collection: `' . $this->value($step->getRetrievalCollection()) . '`';
            $lines[] = '- Prompt metadata fields: `' . implode(', ', $step->getPromptMetadataFieldList()) . '`';
            $lines[] = '- Presentation rule references: `' . implode(', ', $step->getPresentationRuleIdentifiers()) . '`';
            if (method_exists($step, 'getPresentationRulesMode')) {
                $lines[] = '- Presentation rules mode: `' . $this->value($step->getPresentationRulesMode()) . '`';
            }
            if (method_exists($step, 'getUiComponentsMode')) {
                $lines[] = '- UI components mode: `' . $this->value($step->getUiComponentsMode()) . '`';
            }
            if (method_exists($step, 'getUiComponentUids') && $step->getUiComponentUids() !== []) {
                $lines[] = '- UI component references: `' . implode(', ', $step->getUiComponentUids()) . '`';
            }

            if ($step->getType() === AssistantPipelineProcessorType::Retriever && method_exists($step, 'getRetrievalIdentifier')) {
                $lines[] = '- Retrieval identifier: `' . $this->value($step->getRetrievalIdentifier()) . '`';
            }

            if ($step->getType() === AssistantPipelineProcessorType::IntentSelector) {
                $lines[] = '';
                $lines[] = '#### Configured Retrieval Targets';
                $lines[] = '';
                foreach ($steps as $targetStep) {
                    if ($targetStep instanceof PipelineStepConfigurationInterface
                        && $targetStep->getType() === AssistantPipelineProcessorType::Retriever
                        && method_exists($targetStep, 'getRetrievalIdentifier')
                    ) {
                        $lines[] = '- `' . $targetStep->getRetrievalIdentifier() . '` - ' . $this->value($targetStep->getTitle());
                    }
                }
                if (method_exists($step, 'getIntentSelectionMetadata') && $step->getIntentSelectionMetadata() !== '') {
                    $lines[] = '';
                    $lines[] = 'Configured metadata schema:';
                    $lines[] = '```json';
                    $lines[] = $step->getIntentSelectionMetadata();
                    $lines[] = '```';
                }
            }

            if ($step instanceof FilterAwarePipelineStepConfigurationInterface && $step->getRetrievalFilter() !== null) {
                $lines[] = '';
                $lines[] = '#### Vector Filter';
                $lines[] = '';
                foreach ($step->getRetrievalFilter()->conditions as $condition) {
                    $value = is_array($condition->value) ? implode(', ', $condition->value) : (string)$condition->value;
                    $lines[] = '- `' . $condition->field . '` `' . $condition->operator->value . '` `' . $value . '`';
                }
            }
        }

        $lines[] = '';
        $lines = array_merge($lines, $referenceDefinitions);

        return trim(implode("\n", $lines)) . "\n";
    }

    /**
     * Builds the exact prompt messages for one pipeline step through PromptBuilder.
     *
     * The context intentionally contains no retrieval or conversation data. This
     * keeps the export deterministic while using the same prompt composition path
     * as the runtime pipeline.
     *
     * @param \Madj2k\AiCore\Assistant\Configuration\AssistantConfigurationInterface $assistant Assistant configuration.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @return array<int,array{role:string,content:string}> Prompt messages.
     */
    private function buildExportMessages(
        AssistantConfigurationInterface $assistant,
        PipelineStepConfigurationInterface $step,
    ): array {
        $context = new Context(
            new AssistantContext(
                $assistant->getUid() ?? 0,
                $assistant->getTitle(),
                $assistant->getAssistantName(),
                $assistant->getAiConnection(),
                $assistant->getVectorStoreConnection(),
                $assistant->getIdentityPrompt(),
                $assistant->getBehaviorRules(),
                $assistant->getRetrievalRules(),
                $assistant->getOutputRules(),
            ),
            new Request(''),
            new History([]),
            new RetrievalResult(),
            new AnswerState(),
            new ProcessingTrace(),
        );

        return $this->promptBuilder->buildMessages($context, $step);
    }

    /**
     * Appends a Markdown text block whose fence cannot be closed by prompt content.
     *
     * @param array<int,string> &$lines Markdown output lines.
     * @param string $content Prompt content.
     * @return void
     */
    private function appendTextBlock(array &$lines, string $content): void
    {
        preg_match_all('/`+/', $content, $matches);
        $longestFence = 0;
        foreach ($matches[0] as $match) {
            $longestFence = max($longestFence, strlen($match));
        }

        $fence = str_repeat('`', max(3, $longestFence + 1));
        $lines[] = $fence . 'text';
        $lines[] = $content;
        $lines[] = $fence;
    }

    /**
     * @param bool $value
     * @return string
     */
    private function boolean(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    /**
     * @param string $value
     * @return string
     */
    private function value(string $value): string
    {
        return trim($value) === '' ? '(not configured)' : trim($value);
    }
}
