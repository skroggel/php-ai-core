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

namespace Madj2k\AiCore\Tests\Assistant\Tool\Execution;

use Madj2k\AiCore\Assistant\Configuration\PipelineStepConfigurationInterface;
use Madj2k\AiCore\Assistant\Tool\DTO\ToolCall;
use Madj2k\AiCore\Assistant\Tool\DTO\ToolDefinition;
use Madj2k\AiCore\Assistant\Tool\DTO\ToolResult;
use Madj2k\AiCore\Assistant\Tool\Execution\ToolCallingService;
use Madj2k\AiCore\Assistant\Tool\Provider\ToolProviderInterface;
use Madj2k\AiCore\Assistant\Tool\Registry\ToolRegistry;
use Madj2k\AiCore\Connection\Ai\AiConnectorInterface;
use Madj2k\AiCore\Connection\Ai\DTO\AiMessage;
use Madj2k\AiCore\Connection\Ai\DTO\AiRequest;
use Madj2k\AiCore\Connection\Ai\DTO\AiResponse;
use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingRequest;
use Madj2k\AiCore\Connection\Ai\DTO\EmbeddingResponse;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfiguration;
use Madj2k\AiCore\Connection\Resolver\AiConnectorResolver;
use PHPUnit\Framework\TestCase;

/**
 * Class ToolCallingServiceTest
 *
 * Verifies model/tool/model orchestration and round limits.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore\Tests
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class ToolCallingServiceTest extends TestCase
{
    /**
     * Tests that a requested tool is executed before the final answer.
     *
     * @return void
     */
    public function testExecutesToolAndReturnsFinalAnswer(): void
    {
        $connector = new class implements AiConnectorInterface {
            public int $calls = 0;

            public function getIdentifier(): string { return 'test'; }
            public function chat($connection, AiRequest $request): AiResponse
            {
                $this->calls++;
                return $this->calls === 1
                    ? new AiResponse('', ['choices' => [['message' => ['tool_calls' => [[
                        'id' => 'call-1',
                        'function' => ['name' => 'list_entities', 'arguments' => '{}'],
                    ]]]]]])
                    : new AiResponse('The entities are available.');
            }
            public function streamChat($connection, AiRequest $request, callable $onData): void {}
            public function embed($connection, EmbeddingRequest $request): EmbeddingResponse { return new EmbeddingResponse([], 'test'); }
            public function embedBatch($connection, array $requests): array { return []; }
        };

        $provider = new class implements ToolProviderInterface {
            public function getIdentifier(): string { return 'test'; }
            public function getTools(): array { return [new ToolDefinition('list_entities')]; }
            public function call(ToolCall $call): ToolResult { return new ToolResult('["datasets"]'); }
        };

        $step = $this->createStub(PipelineStepConfigurationInterface::class);
        $step->method('getModel')->willReturn('test-model');
        $step->method('getTemperature')->willReturn(0.0);
        $step->method('getMaxTokens')->willReturn(100);

        $service = new ToolCallingService(
            new AiConnectorResolver([$connector]),
            new ToolRegistry([$provider]),
        );
        $answer = $service->run(
            null,
            [new AiMessage('user', 'List entities.')],
            $step,
            new AiConnectionConfiguration('key', defaultModel: 'test-model', connectorIdentifier: 'test'),
        );

        self::assertSame('The entities are available.', $answer);
        self::assertSame(2, $connector->calls);
    }

    /**
     * Tests that the configured round limit stops a non-terminating loop.
     *
     * @return void
     */
    public function testStopsAfterMaximumRounds(): void
    {
        $connector = new class implements AiConnectorInterface {
            public function getIdentifier(): string { return 'test'; }
            public function chat($connection, AiRequest $request): AiResponse
            {
                return new AiResponse('', ['choices' => [['message' => ['tool_calls' => [[
                    'id' => 'call-1', 'function' => ['name' => 'loop', 'arguments' => '{}'],
                ]]]]]]);
            }
            public function streamChat($connection, AiRequest $request, callable $onData): void {}
            public function embed($connection, EmbeddingRequest $request): EmbeddingResponse { return new EmbeddingResponse([], 'test'); }
            public function embedBatch($connection, array $requests): array { return []; }
        };
        $provider = new class implements ToolProviderInterface {
            public function getIdentifier(): string { return 'test'; }
            public function getTools(): array { return [new ToolDefinition('loop')]; }
            public function call(ToolCall $call): ToolResult { return new ToolResult('continue'); }
        };
        $step = $this->createStub(PipelineStepConfigurationInterface::class);
        $step->method('getMaxTokens')->willReturn(100);

        $this->expectException(\Madj2k\AiCore\Exception\AssistantException::class);
        (new ToolCallingService(new AiConnectorResolver([$connector]), new ToolRegistry([$provider]), 2))
            ->run(null, [new AiMessage('user', 'Loop.')], $step, new AiConnectionConfiguration('key', connectorIdentifier: 'test'));
    }
}
