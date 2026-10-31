<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Connection\Resolver;

use Madj2k\AiCore\Connection\Ai\OpenAiConnector;
use Madj2k\AiCore\Connection\Ai\GeminiConnector;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use Madj2k\AiCore\Exception\ConnectorNotFoundException;
use Madj2k\AiCore\Exception\DuplicateConnectorIdentifierException;
use PHPUnit\Framework\TestCase;

final class ResolverTest extends TestCase
{
    public function testResolvesConnectorByIdentifier(): void
    {
        $connector = new OpenAiConnector();
        $geminiConnector = new GeminiConnector();
        $resolver = new AiConnectorResolver([$connector, $geminiConnector]);

        self::assertTrue($resolver->has('openai'));
        self::assertTrue($resolver->has('gemini'));
        self::assertSame($connector, $resolver->get('openai'));
        self::assertSame($geminiConnector, $resolver->get('gemini'));
        self::assertSame([
            'openai' => $connector,
            'gemini' => $geminiConnector,
        ], $resolver->all());
    }

    public function testRejectsDuplicateIdentifiers(): void
    {
        $this->expectException(DuplicateConnectorIdentifierException::class);

        new AiConnectorResolver([new OpenAiConnector(), new OpenAiConnector()]);
    }

    public function testReportsUnknownIdentifiers(): void
    {
        $resolver = new AiConnectorResolver([]);

        $this->expectException(ConnectorNotFoundException::class);
        $resolver->get('missing');
    }
}
