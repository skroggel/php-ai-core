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
 * Class AiConnectionConfiguration
 *
 * Immutable framework-independent AI connection configuration.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final readonly class AiConnectionConfiguration implements AiConnectionConfigurationInterface
{
    /**
     * @param string $apiKey Provider API key.
     * @param string $baseUrl Provider API base URL.
     * @param string $organization Optional provider organization identifier.
     * @param string $project Optional provider project identifier.
     * @param string $defaultModel Default chat model.
     * @param string $embeddingModel Default embedding model.
     * @param float $defaultTemperature Default chat sampling temperature.
     * @param float $embeddingTemperature Default embedding sampling temperature.
     * @param array<string, mixed> $additionalOptions Provider-specific options.
     * @param string $connectorIdentifier Registered connector identifier.
     * @param int $embeddingDimension Expected number of dimensions produced by the embedding model.
     * @param string $embeddingBaseUrl Optional complete embedding endpoint URL.
     * @param bool $baseUrlNoExtend Do not append the provider resource path to the base URL.
     * @param bool $embeddingBaseUrlNoExtend Do not append the embedding resource path to the embedding URL.
     */
    public function __construct(
        private string $apiKey,
        private string $baseUrl = '',
        private string $organization = '',
        private string $project = '',
        private string $defaultModel = '',
        private string $embeddingModel = '',
        private float $defaultTemperature = 0.2,
        private float $embeddingTemperature = 0.0,
        private array $additionalOptions = [],
        private string $connectorIdentifier = 'openai',
        private int $embeddingDimension = 1536,
        private string $embeddingBaseUrl = '',
        private bool $baseUrlNoExtend = false,
        private bool $embeddingBaseUrlNoExtend = false,
    ) {}

    /** @inheritDoc */
    public function getConnectorIdentifier(): string
    {
        return $this->connectorIdentifier;
    }

    /** @inheritDoc */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /** @inheritDoc */
    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * Returns whether the base URL is already a complete request endpoint.
     *
     * @return bool Complete URL flag.
     */
    public function isBaseUrlNoExtend(): bool
    {
        return $this->baseUrlNoExtend;
    }

    /**
     * Returns the optional complete embedding endpoint URL.
     *
     * @return string Complete embedding endpoint URL or an empty string.
     */
    public function getEmbeddingBaseUrl(): string
    {
        return rtrim($this->embeddingBaseUrl, '/');
    }

    /**
     * Returns whether the embedding URL is already a complete request endpoint.
     *
     * @return bool Complete embedding URL flag.
     */
    public function isEmbeddingBaseUrlNoExtend(): bool
    {
        return $this->embeddingBaseUrlNoExtend;
    }

    /** @inheritDoc */
    public function getOrganization(): string
    {
        return $this->organization;
    }

    /** @inheritDoc */
    public function getProject(): string
    {
        return $this->project;
    }

    /** @inheritDoc */
    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }

    /** @inheritDoc */
    public function getEmbeddingModel(): string
    {
        return $this->embeddingModel;
    }

    /** @inheritDoc */
    public function getDefaultTemperature(): float
    {
        return $this->defaultTemperature;
    }

    /** @inheritDoc */
    public function getEmbeddingTemperature(): float
    {
        return $this->embeddingTemperature;
    }

    /** @inheritDoc */
    public function getAdditionalOptionsArray(): array
    {
        return $this->additionalOptions;
    }

    /** @inheritDoc */
    public function getEmbeddingDimension(): int
    {
        return $this->embeddingDimension;
    }
}
