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

namespace Madj2k\AiCore\Connection\Factory;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Authentication\OAuth2ClientCredentialsTokenProvider;
use Madj2k\AiCore\Connection\Resilience\RetryPolicy;
use OpenAI\Contracts\ClientContract;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Http\Message\RequestInterface;

/**
 * Class OpenAiClientFactory
 *
 * Creates native OpenAI SDK clients backed by a timeout-aware Guzzle client.
 *
 * @internal Use OpenAiClientFactoryInterface to provide custom client creation.
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class OpenAiClientFactory implements OpenAiClientFactoryInterface
{
    /**
     * Constructor.
     *
     * @param LoggerInterface|null $logger Optional diagnostic logger.
     */
    public function __construct(private readonly ?LoggerInterface $logger = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function create(
        AiConnectionConfigurationInterface $connection,
        RetryPolicy $policy,
        ?string $endpointOverride = null,
        ?string $baseUrlOverride = null,
    ): ClientContract {
        $logger = $this->logger ?? new NullLogger();
        $stack = HandlerStack::create();
        $endpointOverride = trim($endpointOverride ?? '');

        $stack->push(Middleware::mapRequest(
            static function (RequestInterface $request) use ($endpointOverride, $logger): RequestInterface {
                if ($endpointOverride !== '') {
                    $request = $request->withUri(new \GuzzleHttp\Psr7\Uri($endpointOverride));
                }

                $body = (string)$request->getBody();
                $headers = $request->getHeaders();
                $authorization = $request->getHeaderLine('Authorization');
                if ($authorization !== '') {
                    $headers['Authorization'] = ['Bearer [REDACTED]'];
                    unset($headers['authorization']);
                }

                $logger->debug('OpenAI-compatible HTTP request prepared.', [
                    'method' => $request->getMethod(),
                    'uri' => (string)$request->getUri(),
                    'headers' => $headers,
                    'body_length' => strlen($body),
                    'body_preview' => substr($body, 0, 2000),
                ]);

                if ($request->getBody()->isSeekable()) {
                    $request->getBody()->rewind();
                }

                return $request;
            },
        ));

        $stack->push(Middleware::mapResponse(
            static function (ResponseInterface $response) use ($logger): ResponseInterface {
                $contentType = $response->getHeaderLine('Content-Type');
                if (str_contains(strtolower($contentType), 'text/event-stream')) {
                    $logger->debug('OpenAI-compatible streaming response received.', [
                        'status_code' => $response->getStatusCode(),
                        'content_type' => $contentType,
                    ]);

                    return $response;
                }

                $body = (string)$response->getBody();
                $logger->debug('OpenAI-compatible HTTP response received.', [
                    'status_code' => $response->getStatusCode(),
                    'content_type' => $contentType,
                    'body_length' => strlen($body),
                    'body_preview' => substr($body, 0, 1000),
                ]);

                if ($response->getBody()->isSeekable()) {
                    $response->getBody()->rewind();
                }

                return $response;
            },
        ));

        $factory = \OpenAI::factory()
            ->withApiKey((new OAuth2ClientCredentialsTokenProvider(logger: $logger))->resolve($connection))
            ->withHttpClient(new HttpClient([
                'handler' => $stack,
                'timeout' => $policy->getTimeoutSeconds(),
                'connect_timeout' => $policy->getConnectTimeoutSeconds(),
            ]));

        $baseUrl = trim($baseUrlOverride ?? '') !== ''
            ? trim((string)$baseUrlOverride)
            : $connection->getBaseUrl();
        if ($baseUrl !== '') {
            $factory = $factory->withBaseUri($baseUrl);
        }
        if ($connection->getOrganization() !== '') {
            $factory = $factory->withOrganization($connection->getOrganization());
        }
        if ($connection->getProject() !== '') {
            $factory = $factory->withProject($connection->getProject());
        }

        return $factory->make();
    }
}
