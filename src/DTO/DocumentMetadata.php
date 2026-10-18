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

namespace Madj2k\AiCore\DTO;

/**
 * Class DocumentMetadata
 *
 * Contains structured and additional source metadata for indexable and retrieved content.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
class DocumentMetadata
{
    /**
     * Source type.
     *
     * @var string
     */
    protected string $sourceType = '';


    /**
     * Source identifier.
     *
     * @var string
     */
    protected string $sourceIdentifier = '';


    /**
     * @var string
     */
    protected string $sourceGroupIdentifier = '';


    /**
     * Source title.
     *
     * @var string
     */
    protected string $title = '';


    /**
     * Source URL.
     *
     * @var string
     */
    protected string $url = '';


    /**
     * ISO language code.
     *
     * @var int
     */
    protected string $language = '';


    /**
     * TYPO3 language uid.
     *
     * @var int
     */
    protected int $languageId = -1;

    /**
     * Page uid.
     *
     * @var int
     */
    protected int $pageId = 0;


    /**
     * File path.
     *
     * @var string
     */
    protected string $path = '';


    /**
     * File name.
     *
     * @var string
     */
    protected string $filename = '';


    /**
     * Last changed timestamp.
     *
     * @var int
     */
    protected int $changedAt = 0;


    /**
     * Additional non-standard metadata.
     *
     * @var array<string, mixed>
     */
    protected array $additional = [];


    /**
     * Constructor.
     *
     * @param string $sourceType Source type.
     * @param string $sourceIdentifier Source identifier.
     * @param string $title Source title.
     * @param string $url Source URL.
     * @param string $language ISO language code.
     * @param int $languageId TYPO3 language uid.
     * @param int $pageId Page uid.
     * @param string $path File path.
     * @param string $filename File name.
     * @param int $changedAt Last changed timestamp.
     * @param array<string, mixed> $additional Additional non-standard metadata.
     */
    public function __construct(
        string $sourceType = '',
        string $sourceIdentifier = '',
        string $title = '',
        string $url = '',
        string $language = '',
        int $languageId = -1,
        int $pageId = 0,
        string $path = '',
        string $filename = '',
        int $changedAt = 0,
        array $additional = [],
        string $sourceGroupIdentifier = ''
    ) {
        $this->sourceType = trim($sourceType);
        $this->sourceIdentifier = trim($sourceIdentifier);
        $this->sourceGroupIdentifier = trim($sourceGroupIdentifier) !== ''
            ? trim($sourceGroupIdentifier)
            : trim($sourceIdentifier);
        $this->title = trim($title);
        $this->url = trim($url);
        $this->language = trim($language);
        $this->languageId = $languageId;
        $this->pageId = $pageId;
        $this->path = trim($path);
        $this->filename = trim($filename);
        $this->changedAt = $changedAt;
        $this->additional = $additional;
    }


    /**
     * Returns the source type.
     *
     * @return string Source type.
     */
    public function getSourceType(): string
    {
        return $this->sourceType;
    }


    /**
     * Sets the source type.
     *
     * @param string $sourceType Source type.
     * @return void
     */
    public function setSourceType(string $sourceType): void
    {
        $this->sourceType = trim($sourceType);
    }


    /**
     * Returns the source identifier.
     *
     * @return string Source identifier.
     */
    public function getSourceIdentifier(): string
    {
        return $this->sourceIdentifier;
    }


    /**
     * Sets the source identifier.
     *
     * @param string $sourceIdentifier Source identifier.
     * @return void
     */
    public function setSourceIdentifier(string $sourceIdentifier): void
    {
        $this->sourceIdentifier = trim($sourceIdentifier);
    }


    /**
     * Gets the source group identifier.
     *
     * @return string
     */
    public function getSourceGroupIdentifier(): string
    {
        return $this->sourceGroupIdentifier !== '' ? $this->sourceGroupIdentifier : $this->sourceIdentifier;
    }


    /**
     * Sets the source group identifier.
     *
     * @param string $sourceGroupIdentifier
     * @return void
     */
    public function setSourceGroupIdentifier(string $sourceGroupIdentifier): void
    {
        $this->sourceGroupIdentifier = trim($sourceGroupIdentifier);
    }


    /**
     * Returns the source title.
     *
     * @return string Source title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }


    /**
     * Sets the source title.
     *
     * @param string $title Source title.
     * @return void
     */
    public function setTitle(string $title): void
    {
        $this->title = trim($title);
    }


    /**
     * Returns the source URL.
     *
     * @return string Source URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }


    /**
     * Sets the source URL.
     *
     * @param string $url Source URL.
     * @return void
     */
    public function setUrl(string $url): void
    {
        $this->url = trim($url);
    }


    /**
     * Returns the ISO language code.
     *
     * @return string ISO language code.
     */
    public function getLanguage(): string
    {
        return $this->language;
    }


    /**
     * Sets the ISO language code.
     *
     * @param string $language ISO language code.
     * @return void
     */
    public function setLanguage(string $language): void
    {
        $this->language = trim($language);
    }


    /**
     * Returns the TYPO3 language uid.
     *
     * @return int Language uid or -1 when unset.
     */
    public function getLanguageId(): int
    {
        return $this->languageId;
    }


    /**
     * Sets the TYPO3 language uid.
     *
     * @param int $languageId Language uid.
     * @return void
     */
    public function setLanguageId(int $languageId): void
    {
        $this->languageId = $languageId;
    }


    /**
     * Returns the page uid.
     *
     * @return int Page uid.
     */
    public function getPageId(): int
    {
        return $this->pageId;
    }


    /**
     * Sets the page uid.
     *
     * @param int $pageId Page uid.
     * @return void
     */
    public function setPageId(int $pageId): void
    {
        $this->pageId = $pageId;
    }


    /**
     * Returns the file path.
     *
     * @return string File path.
     */
    public function getPath(): string
    {
        return $this->path;
    }


    /**
     * Sets the file path.
     *
     * @param string $path File path.
     * @return void
     */
    public function setPath(string $path): void
    {
        $this->path = trim($path);
    }


    /**
     * Returns the file name.
     *
     * @return string File name.
     */
    public function getFilename(): string
    {
        return $this->filename;
    }


    /**
     * Sets the file name.
     *
     * @param string $filename File name.
     * @return void
     */
    public function setFilename(string $filename): void
    {
        $this->filename = trim($filename);
    }


    /**
     * Returns the last changed timestamp.
     *
     * @return int Last changed timestamp.
     */
    public function getChangedAt(): int
    {
        return $this->changedAt;
    }


    /**
     * Sets the last changed timestamp.
     *
     * @param int $changedAt Last changed timestamp.
     * @return void
     */
    public function setChangedAt(int $changedAt): void
    {
        $this->changedAt = $changedAt;
    }


    /**
     * Returns additional metadata.
     *
     * @return array<string, mixed> Additional metadata.
     */
    public function getAdditional(): array
    {
        return $this->additional;
    }


    /**
     * Sets additional metadata.
     *
     * @param array<string, mixed> $additional Additional metadata.
     * @return void
     */
    public function setAdditional(array $additional): void
    {
        $this->additional = $additional;
    }


    /**
     * Adds one additional metadata value.
     *
     * @param string $key Metadata key.
     * @param mixed $value Metadata value.
     * @return void
     */
    public function addAdditional(string $key, mixed $value): void
    {
        $key = trim($key);
        if ($key === '') {
            return;
        }

        $this->additional[$key] = $value;
    }


    /**
     * Returns one normalized metadata value.
     *
     * @param string $field Metadata field name.
     * @return mixed Metadata value.
     */
    public function getValue(string $field): mixed
    {
        return match ($field) {
            'source_type', 'sourceType' => $this->sourceType,
            'source_identifier', 'source_id', 'sourceIdentifier' => $this->sourceIdentifier,
            'source_group_identifier', 'sourceGroupIdentifier' => $this->getSourceGroupIdentifier(),
            'title' => $this->title,
            'url' => $this->url,
            'language' => $this->language,
            'language_id' => $this->languageId,
            'page_id', 'pageId' => $this->pageId,
            'path' => $this->path,
            'filename' => $this->filename,
            'changed_at', 'changedAt' => $this->changedAt,
            'additional' => $this->additional,
            default => $this->additional[$field] ?? null,
        };
    }


    /**
     * Returns selected metadata fields.
     *
     * @param array<int,string> $fields Field names.
     * @return array<string,mixed> Selected metadata.
     */
    public function toSelectedArray(array $fields): array
    {
        $selected = [];

        foreach ($fields as $field) {
            $value = $this->getValue($field);
            if ($value === null || $value === '') {
                continue;
            }

            $selected[$field] = $value;
        }

        return $selected;
    }


    /**
     * Exports the metadata as array.
     *
     * The legacy key source_id is kept as alias for existing vector payloads.
     *
     * @return array<string, mixed> Metadata array.
     */
    public function toArray(): array
    {
        return [
            'source_type' => $this->sourceType,
            'source_identifier' => $this->sourceIdentifier,
            'source_id' => $this->sourceIdentifier,
            'source_group_identifier' => $this->getSourceGroupIdentifier(),
            'title' => $this->title,
            'url' => $this->url,
            'language' => $this->language,
            'language_id' => $this->languageId,
            'page_id' => $this->pageId,
            'path' => $this->path,
            'filename' => $this->filename,
            'changed_at' => $this->changedAt,
            'additional' => $this->additional,
        ];
    }


    /**
     * Creates metadata from an array representation.
     *
     * @param array<string,mixed> $data Metadata array.
     * @return self Metadata.
     */
    public static function fromArray(array $data): self
    {
        /** @var mixed $additional */
        $additional = $data['additional'] ?? [];
        $additional = is_array($additional) ? $additional : [];

        foreach ($data as $key => $value) {
            if (in_array($key, [
                'source_type',
                'source_identifier',
                'source_id',
                'source_group_identifier',
                'title',
                'url',
                'language',
                'language_id',
                'page_id',
                'path',
                'filename',
                'changed_at',
                'additional',
            ], true)) {
                continue;
            }

            $additional[(string)$key] = $value;
        }

        return new self(
            sourceType: (string)($data['source_type'] ?? ''),
            sourceIdentifier: (string)($data['source_identifier'] ?? $data['source_id'] ?? ''),
            sourceGroupIdentifier: (string)($data['source_group_identifier'] ?? ''),
            title: (string)($data['title'] ?? ''),
            url: (string)($data['url'] ?? ''),
            language: (string)($data['language'] ?? ''),
            languageId: (int)($data['language_id'] ?? -1),
            pageId: (int)($data['page_id'] ?? 0),
            path: (string)($data['path'] ?? ''),
            filename: (string)($data['filename'] ?? ''),
            changedAt: (int)($data['changed_at'] ?? 0),
            additional: $additional,
        );
    }
}
