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

namespace Madj2k\AiCore\Connection\Configuration;

/**
 * Interface AiConnectionConfigurationInterface
 *
 * Provides provider credentials and model defaults to AI connectors.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
interface AiConnectionConfigurationInterface
{
    /**
     * Returns the connector identifier resolved by the application.
     *
     * @return string
     */
    public function getConnectorIdentifier(): string;


    /**
     * Returns the provider API key.
     *
     * @return string
     */
    public function getApiKey(): string;


    /**
     * Returns the provider API base URL.
     *
     * @return string
     */
    public function getBaseUrl(): string;

    /**
     * Returns whether the base URL is already a complete request endpoint.
     *
     * @return bool Complete URL flag.
     */
    public function isBaseUrlNoExtend(): bool;

    /**
     * Returns the optional complete embedding endpoint URL.
     *
     * @return string Complete embedding endpoint URL or an empty string.
     */
    public function getEmbeddingBaseUrl(): string;

    /**
     * Returns whether the embedding URL is already a complete request endpoint.
     *
     * @return bool Complete embedding URL flag.
     */
    public function isEmbeddingBaseUrlNoExtend(): bool;


    /**
     * Returns the optional provider organization identifier.
     *
     * @return string
     */
    public function getOrganization(): string;


    /**
     * Returns the optional provider project identifier.
     *
     * @return string
     */
    public function getProject(): string;


    /**
     * Returns the default chat model.
     *
     * @return string
     */
    public function getDefaultModel(): string;


    /**
     * Returns the default embedding model.
     *
     * @return string
     */
    public function getEmbeddingModel(): string;


    /**
     * Returns the expected number of dimensions produced by the embedding model.
     *
     * @return int
     */
    public function getEmbeddingDimension(): int;


    /**
     * Returns the default chat sampling temperature.
     *
     * @return float
     */
    public function getDefaultTemperature(): float;


    /**
     * Returns the default embedding sampling temperature.
     *
     * @return float
     */
    public function getEmbeddingTemperature(): float;


    /**
     * Returns provider-specific connection options.
     *
     * @return array<string, mixed>
     */
    public function getAdditionalOptionsArray(): array;
}
