<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Connection\Configuration;

use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest;
use Madj2k\AiCore\Connection\Ai\Enum\EmbeddingPurpose;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfiguration;
use Madj2k\AiCore\Connection\Configuration\VectorStoreConnectionConfiguration;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testAiConfigurationIsNormalizedAndIndependent(): void
    {
        $configuration = new AiConnectionConfiguration(
            apiKey: 'secret',
            baseUrl: 'https://example.test/v1/',
            additionalOptions: ['seed' => 42],
        );

        self::assertSame('secret', $configuration->getApiKey());
        self::assertSame('openai', $configuration->getConnectorIdentifier());
        self::assertSame('https://example.test/v1', $configuration->getBaseUrl());
        self::assertSame(['seed' => 42], $configuration->getAdditionalOptionsArray());
    }

    public function testAiConfigurationHasProviderNeutralDefaults(): void
    {
        $configuration = new AiConnectionConfiguration(apiKey: 'secret');

        self::assertSame('', $configuration->getBaseUrl());
        self::assertSame('', $configuration->getDefaultModel());
        self::assertSame('', $configuration->getEmbeddingModel());
        self::assertSame(1536, $configuration->getEmbeddingDimension());
    }

    public function testVectorStoreConfigurationIsNormalized(): void
    {
        $configuration = new VectorStoreConnectionConfiguration('https://qdrant.test/');

        self::assertSame('https://qdrant.test', $configuration->getEndpoint());
        self::assertSame('qdrant', $configuration->getConnectorIdentifier());
        self::assertSame('', $configuration->getApiKey());
        self::assertSame('', $configuration->getDefaultCollection());
    }

    public function testEmbeddingDimensionCanBeConfiguredOnAiConnection(): void
    {
        $configuration = new AiConnectionConfiguration(
            apiKey: 'secret',
            embeddingDimension: 3072,
        );

        self::assertSame(3072, $configuration->getEmbeddingDimension());
    }

    public function testEmbeddingRequestPurposeIsOptionalAndMutable(): void
    {
        $request = new EmbeddingRequest('content');

        self::assertSame(EmbeddingPurpose::Unspecified, $request->getPurpose());

        $request->setPurpose(EmbeddingPurpose::RetrievalDocument);

        self::assertSame(EmbeddingPurpose::RetrievalDocument, $request->getPurpose());
    }
}
