<?php
declare(strict_types=1);

/*
 * This file is part of madj2k/ai-core.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Madj2k\AiCore\Tests\Assistant;

use Madj2k\AiCore\Assistant\Memory\PhpSessionStore;
use PHPUnit\Framework\TestCase;

final class PhpSessionStoreTest extends TestCase
{
    public function testPersistsValuesWithoutKeepingTheSessionLocked(): void
    {
        session_save_path(sys_get_temp_dir());
        session_id('ai-core-' . bin2hex(random_bytes(8)));
        $store = new PhpSessionStore();

        $store->write('test-key', ['value' => 42]);

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame('0', ini_get('session.use_cookies'));
        self::assertSame('', ini_get('session.cache_limiter'));
        self::assertSame(['value' => 42], $store->read('test-key'));
        self::assertSame(PHP_SESSION_NONE, session_status());

        session_start(['cache_limiter' => '']);
        session_destroy();
    }
}
