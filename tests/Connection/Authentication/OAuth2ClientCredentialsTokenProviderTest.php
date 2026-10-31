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

namespace Madj2k\AiCore\Tests\Connection\Authentication;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Madj2k\AiCore\Connection\Authentication\OAuth2ClientCredentialsTokenProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class OAuth2ClientCredentialsTokenProviderTest
 *
 * Verifies OAuth token acquisition and in-memory token caching.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore\Tests
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class OAuth2ClientCredentialsTokenProviderTest extends TestCase
{
    /**
     * Tests one token request is reused while the token is valid.
     *
     * @return void
     */
    public function testCachesAccessToken(): void
    {
        $handler = new MockHandler([
            new Response(200, [], '{"access_token":"token","expires_in":300}'),
        ]);
        $connection = new class {
            public function getAuthentication(): string { return 'oauth_client_credentials'; }
            public function getOauthTokenEndpoint(): string { return 'https://oauth.test/token'; }
            public function getOauthClientId(): string { return 'client'; }
            public function getOauthClientSecret(): string { return 'secret'; }
            public function getOauthScope(): string { return 'read'; }
        };
        $provider = new OAuth2ClientCredentialsTokenProvider(new Client(['handler' => $handler]));

        self::assertSame('token', $provider->resolve($connection));
        self::assertSame('token', $provider->resolve($connection));
        self::assertCount(0, $handler);
    }
}
