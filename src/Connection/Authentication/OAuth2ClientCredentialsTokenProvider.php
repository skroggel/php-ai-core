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

namespace Madj2k\AiCore\Connection\Authentication;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Madj2k\AiCore\Exception\ApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class OAuth2ClientCredentialsTokenProvider
 *
 * Resolves and caches OAuth 2.0 client-credentials tokens for AI connections.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class OAuth2ClientCredentialsTokenProvider
{
    /** @var array<string, array{token: string, expiresAt: int}> */
    private static array $tokens = [];

    /**
     * @param ClientInterface|null $httpClient Optional HTTP client for tests or custom transports.
     */
    private readonly LoggerInterface $logger;

    /**
     * @param ClientInterface|null $httpClient Optional HTTP client for tests or custom transports.
     * @param LoggerInterface|null $logger Optional diagnostic logger.
     */
    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }


    /**
     * Returns an OAuth access token when the configuration selects OAuth.
     *
     * @param object $connection AI connection configuration.
     * @return string Access token or API key.
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Madj2k\AiCore\Exception\ApiException
     */
    public function resolve(object $connection): string
    {
        if (!method_exists($connection, 'getAuthentication') || $connection->getAuthentication() !== 'oauth_client_credentials') {
            return method_exists($connection, 'getApiKey') ? (string)$connection->getApiKey() : '';
        }

        $endpoint = (string)$connection->getOauthTokenEndpoint();
        $clientId = (string)$connection->getOauthClientId();
        $clientSecret = (string)$connection->getOauthClientSecret();
        $scope = method_exists($connection, 'getOauthScope') ? (string)$connection->getOauthScope() : '';
        $key = sha1(implode('|', [$endpoint, $clientId, $scope]));
        if (isset(self::$tokens[$key]) && time() < self::$tokens[$key]['expiresAt']) {
            return self::$tokens[$key]['token'];
        }

        if ($endpoint === '' || $clientId === '' || $clientSecret === '') {
            throw new ApiException('OAuth token endpoint, client ID and client secret are required.', 1789004001);
        }

        $form = ['grant_type' => 'client_credentials'];
        if ($scope !== '') {
            $form['scope'] = $scope;
        }

        $this->logger->debug('OAuth client-credentials request prepared.', [
            'endpoint' => $endpoint,
            'client_id' => '[REDACTED]',
            'client_secret' => '[REDACTED]',
            'scope' => $scope,
            'grant_type' => 'client_credentials',
            'authentication' => 'http_basic',
        ]);

        try {
            $response = ($this->httpClient ?? new Client())->request('POST', $endpoint, [
                'auth' => [$clientId, $clientSecret],
                'form_params' => $form,
                'headers' => ['Accept' => 'application/json'],
                'http_errors' => false,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->debug('OAuth client-credentials request failed before response.', [
                'endpoint' => $endpoint,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        $responseBody = (string)$response->getBody();
        $this->logger->debug('OAuth client-credentials response received.', [
            'endpoint' => $endpoint,
            'status_code' => $response->getStatusCode(),
            'content_type' => $response->getHeaderLine('Content-Type'),
            'body' => $this->redactResponseBody($responseBody),
        ]);

        $payload = json_decode($responseBody, true);
        $token = is_array($payload) ? trim((string)($payload['access_token'] ?? '')) : '';
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || $token === '') {
            throw new ApiException('OAuth token endpoint returned an invalid response.', 1789004002);
        }

        self::$tokens[$key] = [
            'token' => $token,
            'expiresAt' => time() + max(1, (int)($payload['expires_in'] ?? 300) - 60),
        ];

        return $token;
    }

    /**
     * Redacts credentials and limits the OAuth response diagnostics.
     *
     * @param string $body OAuth response body.
     * @return string Redacted response preview.
     */
    private function redactResponseBody(string $body): string
    {
        $payload = json_decode($body, true);
        if (is_array($payload)) {
            foreach (['access_token', 'refresh_token', 'id_token', 'client_secret'] as $key) {
                if (array_key_exists($key, $payload)) {
                    $payload[$key] = '[REDACTED]';
                }
            }

            $body = (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return substr($body, 0, 2000);
    }
}
