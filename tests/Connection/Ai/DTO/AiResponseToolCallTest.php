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

namespace Madj2k\AiCore\Tests\Connection\Ai\DTO;

use Madj2k\AiCore\Connection\Ai\DTO\AiResponse;
use PHPUnit\Framework\TestCase;

/**
 * Class AiResponseToolCallTest
 *
 * Verifies provider response normalization for tool calls.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore\Tests
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class AiResponseToolCallTest extends TestCase
{
    /**
     * Tests OpenAI-compatible tool-call normalization.
     *
     * @return void
     */
    public function testNormalizesOpenAiToolCall(): void
    {
        $response = new AiResponse('', ['choices' => [['message' => ['tool_calls' => [[
            'id' => 'call-1',
            'function' => ['name' => 'find_records', 'arguments' => '{"limit":5}'],
        ]]]]]]);
        $calls = $response->getToolCalls();

        self::assertCount(1, $calls);
        self::assertSame('find_records', $calls[0]->name);
        self::assertSame(['limit' => 5], $calls[0]->arguments);
    }

    /**
     * Tests Gemini function-call normalization.
     *
     * @return void
     */
    public function testNormalizesGeminiFunctionCall(): void
    {
        $response = new AiResponse('', ['candidates' => [['content' => ['parts' => [[
            'functionCall' => ['name' => 'describe_dataset', 'args' => ['dataset' => 'survey']],
        ]]]]]]);
        $calls = $response->getToolCalls();

        self::assertCount(1, $calls);
        self::assertSame('describe_dataset', $calls[0]->name);
        self::assertSame(['dataset' => 'survey'], $calls[0]->arguments);
    }
}
