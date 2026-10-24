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

use Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface;
use Madj2k\AiCore\Connection\Resilience\RetryPolicy;
use OpenAI\Contracts\ClientContract;

/**
 * Interface OpenAiClientFactoryInterface
 *
 * Creates configured OpenAI SDK clients and provides an injection point for custom transports.
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
interface OpenAiClientFactoryInterface
{

    /**
     * Creates an OpenAI client for the given connection and timeout policy.
     *
     * @param \Madj2k\AiCore\Connection\Configuration\AiConnectionConfigurationInterface $connection
     * @param \Madj2k\AiCore\Connection\Resilience\RetryPolicy $policy
     * @param string|null $endpointOverride Complete request endpoint override.
     * @param string|null $baseUrlOverride Request base URL override.
     * @return \OpenAI\Contracts\ClientContract
     */
    public function create(
        AiConnectionConfigurationInterface $connection,
        RetryPolicy $policy,
        ?string $endpointOverride = null,
        ?string $baseUrlOverride = null,
    ): ClientContract;
}
