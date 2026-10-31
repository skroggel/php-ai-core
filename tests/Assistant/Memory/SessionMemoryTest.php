<?php
declare(strict_types=1);

/*
 * This file is part of madj2k/ai-core.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Madj2k\AiCore\Tests\Assistant\Memory;

use Madj2k\AiCore\Assistant\Memory\SessionMemory;
use Madj2k\AiCore\Assistant\Memory\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

final class SessionMemoryTest extends TestCase
{
    public function testStoresAndReturnsConversationMessages(): void
    {
        $memory = new SessionMemory($this->createStore());
        $memory->start('chat-id', 123);
        $memory->addMessage('chat-id', 'user', ' Question ');
        $memory->addMessage('chat-id', 'assistant', ' Answer ');

        self::assertSame([
            ['role' => 'user', 'content' => 'Question'],
            ['role' => 'assistant', 'content' => 'Answer'],
        ], $memory->getMessages('chat-id'));
    }

    public function testNewStartTimestampResetsConversation(): void
    {
        $memory = new SessionMemory($this->createStore());
        $memory->start('chat-id', 123);
        $memory->addMessage('chat-id', 'user', 'Old message');

        $memory->start('chat-id', 456);

        self::assertSame([], $memory->getMessages('chat-id'));
        self::assertNull($memory->getLastRetrievalResult('chat-id'));
    }

    public function testMessageLimitKeepsNewestMessages(): void
    {
        $memory = new SessionMemory($this->createStore(), 2);
        $memory->start('chat-id', 123);
        $memory->addMessage('chat-id', 'user', 'First');
        $memory->addMessage('chat-id', 'assistant', 'Second');
        $memory->addMessage('chat-id', 'user', 'Third');

        self::assertSame([
            ['role' => 'assistant', 'content' => 'Second'],
            ['role' => 'user', 'content' => 'Third'],
        ], $memory->getMessages('chat-id'));
    }

    private function createStore(): SessionStoreInterface
    {
        return new class implements SessionStoreInterface {
            /** @var array<string,mixed> */
            private array $data = [];

            public function read(string $key): mixed
            {
                return $this->data[$key] ?? null;
            }

            public function write(string $key, mixed $value): void
            {
                $this->data[$key] = $value;
            }
        };
    }
}
