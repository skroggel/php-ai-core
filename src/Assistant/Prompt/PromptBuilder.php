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

namespace Madj2k\AiCore\Assistant\Prompt;

use Madj2k\AiCore\Assistant\Context\Context;
use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Enum\HistoryMode;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\DTO\PromptType;
use Madj2k\AiCore\Assistant\Prompt\Context\Formatter;
use Madj2k\AiCore\Assistant\Prompt\Context\PromptSection;
use Madj2k\AiCore\Assistant\Prompt\Context\Registry\ContextBuilderRegistry;

/**
 * Class PromptBuilder
 *
 * Builds compact prompts for LLM-based chat pipeline steps.
 *
 *
 * Workflow
 * -> PromptBuilder
 * -> ContextBuilderRegistry
 * -> suitable ContextBuilder
 * -> PromptSection[]
 * -> Formatter
 * -> final Prompt-Text
 * -> LLM
 *
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class PromptBuilder
{
    /**
     * constructor
     */
    public function __construct(
        private readonly ContextBuilderRegistry $contextBuilderRegistry,
        private readonly Formatter $contextFormatter,
    ) {
    }


    /**
     * Builds chat messages for an LLM step.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step
     * @return array[]
     */
    public function buildMessages(Context $context, PipelineStepConfigurationInterface $step): array
    {
        $systemPrompt = $this->buildSystemPrompt($context, $step);
        $historyPrompt = $this->buildHistoryPrompt($context, $step);
        $contextPrompt = $this->buildContextPrompt($context, $step);
        $userPrompt = trim($historyPrompt . "\n\n" . $contextPrompt);

        return [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => trim($userPrompt),
            ],
        ];
    }


    /**
     * Returns one section content from the centralized prompt context.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step
     * @param string $title
     * @return string
     */
    public function getContextSectionContent(
        Context $context,
        PipelineStepConfigurationInterface $step,
        string $title
    ): string {
        foreach ($this->contextBuilderRegistry->buildSections($context, $step) as $section) {
            if ($section->title === $title) {
                return trim($section->content);
            }
        }

        return '';
    }

    /**
     * Builds the system prompt from selected assistant and step prompt parts.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step
     * @return string
     */
    private function buildSystemPrompt(Context $context, PipelineStepConfigurationInterface $step): string
    {
        $assistant = $context->getAssistant();
        $parts = [];
        $parts = array_merge($parts, $this->buildSystemPromptTypeSections(
            $context,
            $step,
            PromptType::Identity,
            'Global Assistant Identity',
            $step->getIncludeIdentityPrompt() ? $assistant->getIdentityPrompt() : '',
            'Step Identity',
            $step->getStepIdentity(),
        ));

        $parts = array_merge($parts, $this->buildSystemPromptTypeSections(
            $context,
            $step,
            PromptType::Behavior,
            'Global Assistant Behavior Rules',
            $step->getIncludeBehaviorRules() ? $assistant->getBehaviorRules() : '',
            'Step Behavior Rules',
            $step->getStepBehaviorRules(),
        ));

        $parts = array_merge($parts, $this->buildSystemPromptTypeSections(
            $context,
            $step,
            PromptType::Retrieval,
            'Global Assistant Retrieval Rules',
            $step->getIncludeRetrievalRules() ? $assistant->getRetrievalRules() : '',
            'Step Retrieval Rules',
            $step->getStepRetrievalRules(),
        ));

        $parts = array_merge($parts, $this->buildSystemPromptTypeSections(
            $context,
            $step,
            PromptType::Output,
            'Global Assistant Output Rules',
            $step->getIncludeOutputRules() ? $assistant->getOutputRules() : '',
            'Step Output Rules',
            $step->getStepOutputRules(),
        ));

        $parts = array_merge($parts, $this->buildSystemPromptTypeSections(
            $context,
            $step,
            PromptType::None,
            'Additional Prompt Context',
            '',
            'Additional Prompt Context',
            '',
        ));

        // Forward enabled runtime options to the language model as prompt instructions.
        if (in_array($step->getType(), [
            AssistantPipelineProcessorType::AnswerGenerator,
            AssistantPipelineProcessorType::QualityGate,
        ], true)) {
            $chatOptions = $context->getRequest()->getChatOptions();
            if ($chatOptions->responseLanguage !== '') {
                $parts[] = new PromptSection('Response Language', "Respond in this language: "
                    . $chatOptions->responseLanguage
                    . '. This explicit response language takes priority over the language of the user message, quoted text and retrieved documents.');
            }
            if ($chatOptions->plainLanguage) {
                $parts[] = new PromptSection(
                    'Accessibility Requirements',
                    'Use plain language. Prefer familiar words, short sentences, short paragraphs and a clear structure. Explain unavoidable technical terms.'
                );
            }
        }

        $parts[] = new PromptSection('Priority Instructions',
            'Runtime response language and accessibility requirements take priority over assistant and step output preferences.' .
            'Step rules may specialize global assistant rules but must not weaken their safety, privacy, thematic restrictions or factual-source requirements. ' .
            'For other conflicts, step rules take priority over global assistant rules.'
        );

        return $this->contextFormatter->format(array_filter($parts));
    }

    /**
     * Builds one typed prompt parent and attaches the matching context sections.
     *
     * Step-specific rules receive the context sections whenever they are present;
     * otherwise the sections are attached to the global rules.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context Current assistant context.
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @param \Madj2k\AiCore\Assistant\DTO\PromptType $type Parent prompt type.
     * @param string $globalTitle Global parent title.
     * @param string $globalContent Global parent content.
     * @param string $stepTitle Step parent title.
     * @param string $stepContent Step parent content.
     * @return array<int, \Madj2k\AiCore\Assistant\Prompt\Context\PromptSection> Prompt sections.
     */
    private function buildSystemPromptTypeSections(
        Context $context,
        PipelineStepConfigurationInterface $step,
        PromptType $type,
        string $globalTitle,
        string $globalContent,
        string $stepTitle,
        string $stepContent,
    ): array {
        $sections = $this->contextBuilderRegistry->buildSystemSections($context, $step, $type);
        if ($type === PromptType::None) {
            return $sections === []
                ? []
                : [new PromptSection($globalTitle, '', sections: $sections)];
        }

        if ($stepContent !== '' && $this->usesStepPromptTypeConfiguration($step, $type)) {
            $result = $globalContent !== '' ? [new PromptSection($globalTitle, $globalContent)] : [];
            $result[] = new PromptSection($stepTitle, $stepContent, sections: $sections);
            return $result;
        }

        if ($globalContent !== '') {
            $result = [new PromptSection($globalTitle, $globalContent, sections: $sections)];
            if ($stepContent !== '') {
                $result[] = new PromptSection($stepTitle, $stepContent);
            }
            return $result;
        }

        return [];
    }

    /**
     * Determines whether a prompt type is explicitly configured on the step.
     *
     * Inherit modes keep presentation and UI context on the global parent even
     * when the step itself contains output rules.
     *
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step Pipeline step.
     * @param \Madj2k\AiCore\Assistant\DTO\PromptType $type Prompt type.
     * @return bool Whether the step owns the typed context.
     */
    private function usesStepPromptTypeConfiguration(
        PipelineStepConfigurationInterface $step,
        PromptType $type,
    ): bool {
        if ($type !== PromptType::Output) {
            return true;
        }

        $presentationMode = method_exists($step, 'getPresentationRulesMode')
            ? $step->getPresentationRulesMode()
            : 'inherit';
        $uiMode = method_exists($step, 'getUiComponentsMode')
            ? $step->getUiComponentsMode()
            : 'inherit';

        return $presentationMode !== 'inherit' || $uiMode !== 'inherit';
    }


    /**
     * Builds the history prompt according to the configured history policy.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step
     * @return string
     */
    private function buildHistoryPrompt(Context $context, PipelineStepConfigurationInterface $step): string
    {
        if ($step->getHistoryMode() === HistoryMode::None) {
            return '';
        }

        $messages = $context->getHistory()->last($step->getHistoryLimit());

        if ($messages === []) {
            return '';
        }

        $lines = [];
        foreach ($messages as $message) {
            $lines[] = sprintf('%s: %s', $message['role'], $message['content']);
        }

        return $this->contextFormatter->boundary('Chat History', implode("\n", $lines));
    }


    /**
     * Builds the step-specific context prompt from centralized prompt context sections.
     *
     * @param \Madj2k\AiCore\Assistant\Context\Context $context
     * @param \Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface $step
     * @return string
     */
    private function buildContextPrompt(Context $context, PipelineStepConfigurationInterface $step): string
    {
        $userSections = $this->contextBuilderRegistry->buildUserSections($context, $step);
        return $this->contextFormatter->format($userSections);
    }
}
