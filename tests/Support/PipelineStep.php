<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Support;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineFailureStrategy;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineStage;
use Madj2k\AiCore\Assistant\Enum\HistoryMode;
use Madj2k\AiCore\Assistant\Enum\IntentSelectionOutputType;
use Madj2k\AiCore\Connection\VectorStore\Filter\DTO\FilterConjunction;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfigurationInterface;

final readonly class PipelineStep implements PipelineStepConfigurationInterface
{
    /**
     * @param array<int, string> $metadataFields
     */
    public function __construct(
        private AssistantPipelineProcessorType $type = AssistantPipelineProcessorType::AnswerGenerator,
        private string $identifier = 'test.processor',
        private AssistantPipelineFailureStrategy $failureStrategy = AssistantPipelineFailureStrategy::Stop,
        private int $maxContextChunks = 6,
        private int $maxContextCharacters = 9000,
        private int $maxChunkCharacters = 0,
        private array $metadataFields = ['title', 'url'],
        private HistoryMode $historyMode = HistoryMode::None,
        private int $historyLimit = 0,
        private bool $includeIdentity = false,
        private bool $includeBehavior = false,
        private bool $includeRetrieval = false,
        private bool $includeOutput = false,
        private string $stepIdentity = '',
        private string $stepBehavior = '',
        private string $stepRetrieval = '',
        private string $stepOutput = '',
        private ?int $uid = null,
        private string $title = 'Test step',
        private AssistantPipelineStage $stage = AssistantPipelineStage::PreAnswer,
        private ?VectorStoreConnectionConfigurationInterface $retrievalVectorStoreConnection = null,
        private string $retrievalCollection = '',
        private array $presentationRuleIdentifiers = [],
        private string $retrievalIdentifier = '',
        private array $intentSelectionOutputTypes = [],
        private string $intentSelectionPayloadField = '',
        private FilterConjunction $intentSelectionPayloadConjunction = FilterConjunction::And,
    ) {
    }

    public function getUid(): ?int { return $this->uid; }
    public function getTitle(): string { return $this->title; }
    public function getType(): AssistantPipelineProcessorType { return $this->type; }
    public function getProcessorIdentifier(): string { return $this->identifier; }
    public function getRetrievalIdentifier(): string { return $this->retrievalIdentifier; }
    /** @return array<int,IntentSelectionOutputType> */
    public function getIntentSelectionOutputTypes(): array { return $this->intentSelectionOutputTypes; }
    public function getIntentSelectionPayloadField(): string { return $this->intentSelectionPayloadField; }
    /** @return array<int,string> */
    public function getIntentSelectionPayloadRetrievers(): array { return []; }
    public function getIntentSelectionPayloadConjunction(): FilterConjunction { return $this->intentSelectionPayloadConjunction; }
    public function getIntentSelectionInstructions(): string { return ''; }
    public function getIntentSelectionMetadata(): string { return ''; }
    public function getStage(): AssistantPipelineStage { return $this->stage; }
    public function getIncludeIdentityPrompt(): bool { return $this->includeIdentity; }
    public function getIncludeBehaviorRules(): bool { return $this->includeBehavior; }
    public function getIncludeRetrievalRules(): bool { return $this->includeRetrieval; }
    public function getIncludeOutputRules(): bool { return $this->includeOutput; }
    public function getStepIdentity(): string { return $this->stepIdentity; }
    public function getStepBehaviorRules(): string { return $this->stepBehavior; }
    public function getStepRetrievalRules(): string { return $this->stepRetrieval; }
    public function getStepOutputRules(): string { return $this->stepOutput; }
    public function getHistoryMode(): HistoryMode { return $this->historyMode; }
    public function getHistoryLimit(): int { return $this->historyLimit; }
    public function getModel(): string { return ''; }
    public function getTemperature(): float { return 0.0; }
    public function getMaxTokens(): int { return 0; }
    public function getMaxRetrievalResults(): int { return 0; }
    public function getScoreThreshold(): float { return 0.0; }
    public function getMaxContextChunks(): int { return $this->maxContextChunks; }
    public function getMaxContextCharacters(): int { return $this->maxContextCharacters; }
    public function getMaxChunksPerResult(): int { return 1; }
    public function getMaxChunkCharacters(): int { return $this->maxChunkCharacters; }
    public function getPromptMetadataFieldList(): array { return $this->metadataFields; }
    public function getPresentationRuleIdentifiers(): array { return $this->presentationRuleIdentifiers; }
    public function getFailureStrategy(): AssistantPipelineFailureStrategy { return $this->failureStrategy; }
    public function getRetrievalVectorStoreConnection(): ?VectorStoreConnectionConfigurationInterface { return $this->retrievalVectorStoreConnection; }
    public function getRetrievalCollection(): string { return $this->retrievalCollection; }
    public function isLlmStep(): bool
    {
        return !in_array($this->type, [AssistantPipelineProcessorType::Retriever, AssistantPipelineProcessorType::Memory], true);
    }
}
