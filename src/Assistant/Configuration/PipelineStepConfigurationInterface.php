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

namespace Madj2k\AiCore\Assistant\Configuration;

use Madj2k\AiCore\Assistant\Enum\AssistantPipelineFailureStrategy;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineStage;
use Madj2k\AiCore\Assistant\Enum\HistoryMode;
use Madj2k\AiCore\Assistant\Enum\IntentSelectionOutputType;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterConjunction;

/**
 * Interface PipelineStepConfigurationInterface
 *
 * Defines processor selection, prompt composition and execution limits for one pipeline step.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
interface PipelineStepConfigurationInterface
{
    /**
     * Returns the optional persistent identifier.
     *
     * @return int|null
     */
    public function getUid(): ?int;


    /**
     * Returns the step title.
     *
     * @return string
     */
    public function getTitle(): string;


    /**
     * Returns the semantic processor type.
     *
     * @return \Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType
     */
    public function getType(): AssistantPipelineProcessorType;


    /**
     * Returns the registered processor identifier.
     *
     * @return string
     */
    public function getProcessorIdentifier(): string;


    /**
     * Returns the optional prompt-visible retrieval identifier.
     *
     * @return string
     */
    public function getRetrievalIdentifier(): string;


    /**
     * Returns the pipeline stage.
     *
     * @return \Madj2k\AiCore\Assistant\Enum\AssistantPipelineStage;
     */
    public function getStage(): AssistantPipelineStage;


    /**
     * Determines whether the global identity prompt is included.
     *
     * @return bool
     */
    public function getIncludeIdentityPrompt(): bool;


    /**
     * Determines whether the global behavior rules are included.
     *
     * @return bool
     */
    public function getIncludeBehaviorRules(): bool;


    /**
     * Determines whether the global retrieval rules are included.
     *
     * @return bool
     */
    public function getIncludeRetrievalRules(): bool;


    /**
     * Determines whether the global output rules are included.
     *
     * @return bool
     */
    public function getIncludeOutputRules(): bool;


    /**
     * Returns the step-specific identity prompt.
     *
     * @return string
     */
    public function getStepIdentity(): string;


    /**
     * Returns the step-specific behavior rules.
     *
     * @return string
     */
    public function getStepBehaviorRules(): string;


    /**
     * Returns the step-specific retrieval rules.
     *
     * @return string
     */
    public function getStepRetrievalRules(): string;


    /**
     * Returns the step-specific output rules.
     *
     * @return string
     */
    public function getStepOutputRules(): string;

    /**
     * Returns Stable identifiers of presentation rules.
     *
     * @return array<int,string>
     */
    public function getPresentationRuleIdentifiers(): array;


    /**
     * Returns the output areas controlled by the intent selector.
     *
     * @return array<int,\Madj2k\AiCore\Assistant\Enum\IntentSelectionOutputType> Output areas.
     */
    public function getIntentSelectionOutputTypes(): array;


    /**
     * Returns the payload field path used for dynamic retriever filtering.
     *
     * @return string Payload field path.
     */
    public function getIntentSelectionPayloadField(): string;


    /**
     * Returns retriever identifiers that receive the dynamic payload filter.
     *
     * An empty array means all retrievers allowed by the retrieval plan.
     *
     * @return array<int,string> Retriever identifiers.
     */
    public function getIntentSelectionPayloadRetrievers(): array;


    /**
     * Returns the conjunction used for multiple dynamic payload values.
     *
     * @return \Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterConjunction Payload conjunction.
     */
    public function getIntentSelectionPayloadConjunction(): FilterConjunction;


    /**
     * Returns additional intent selector instructions.
     *
     * @return string Additional instructions.
     */
    public function getIntentSelectionInstructions(): string;


    /**
     * Returns the optional intent selector metadata schema.
     *
     * @return string Metadata schema.
     */
    public function getIntentSelectionMetadata(): string;


    /**
     * Returns the conversation history mode.
     *
     * @return \Madj2k\AiCore\Assistant\Enum\HistoryMode
     */
    public function getHistoryMode(): HistoryMode;


    /**
     * Returns the maximum number of history entries.
     *
     * @return int
     */
    public function getHistoryLimit(): int;


    /**
     * Returns the model override or an empty string for the connection default.
     *
     * @return string
     */
    public function getModel(): string;


    /**
     * Returns the sampling temperature.
     *
     * @return float
     */
    public function getTemperature(): float;


    /**
     * Returns the maximum number of generated tokens.
     *
     * @return int
     */
    public function getMaxTokens(): int;


    /**
     * Returns the maximum number of retrieval results.
     *
     * @return int
     */
    public function getMaxRetrievalResults(): int;


    /**
     * Returns an optional vector store connection override.
     *
     * @return \Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface|null
     */
    public function getRetrievalVectorStoreConnection(): ?VectorStoreConnectionConfigurationInterface;


    /**
     * Returns an optional vector collection override.
     *
     * @return string
     */
    public function getRetrievalCollection(): string;


    /**
     * Returns the minimum retrieval score.
     *
     * @return float
     */
    public function getScoreThreshold(): float;


    /**
     * Returns the maximum number of context chunks.
     *
     * @return int
     */
    public function getMaxContextChunks(): int;


    /**
     * Returns the maximum context length in characters.
     *
     * @return int
     */
    public function getMaxContextCharacters(): int;

    /**
     * Returns the maximum number of excerpts created from one retrieval result.
     *
     * @return int
     */
    public function getMaxChunksPerResult(): int;

    /**
     * Returns the maximum character length of one retrieval excerpt.
     *
     * @return int
     */
    public function getMaxChunkCharacters(): int;


    /**
     * Returns metadata fields exposed to prompt context builders.
     *
     * @return array<int, string>
     */
    public function getPromptMetadataFieldList(): array;


    /**
     * Returns the strategy applied when the processor fails.
     * @return \Madj2k\AiCore\Assistant\Enum\AssistantPipelineFailureStrategy
     */
    public function getFailureStrategy(): AssistantPipelineFailureStrategy;



    /**
     * Determines whether the configured processor calls an LLM.
     *
     * @return bool
     */
    public function isLlmStep(): bool;
}
