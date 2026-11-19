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

namespace Madj2k\AiCore\Tests\Assistant\Contract;

use Madj2k\AiCore\Assistant\Contract\QualityGateContract;
use Madj2k\AiCore\Assistant\DTO\QualityGateDecision;
use Madj2k\AiCore\Assistant\Enum\AssistantPipelineProcessorType;
use Madj2k\AiCore\Tests\Support\PipelineStep;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class QualityGateContractTest
 *
 * Verifies the strict Quality Gate accept/replace contract.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright Steffen Kroggel <developer@steffenkroggel.de>, Maximilian Fäßler <maximilian@faesslerweb.de>
 * @package Madj2k\AiCore\Tests
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */
final class QualityGateContractTest extends TestCase
{
    /** @return void */
    public function testKeepSentinelKeepsTheCandidate(): void
    {
        $decision = (new QualityGateContract())->parse(
            'KEEP',
            new PipelineStep(type: AssistantPipelineProcessorType::QualityGate),
        );

        self::assertInstanceOf(QualityGateDecision::class, $decision);
        self::assertSame('keep', $decision->decision);
        self::assertNull($decision->answer);
    }

    /** @return void */
    public function testReplaceRequiresCompleteAnswer(): void
    {
        $decision = (new QualityGateContract())->parse(
            'Complete @@UI_COMPONENT answer',
            new PipelineStep(type: AssistantPipelineProcessorType::QualityGate),
        );

        self::assertInstanceOf(QualityGateDecision::class, $decision);
        self::assertSame('replace', $decision->decision);
        self::assertSame('Complete @@UI_COMPONENT answer', $decision->answer);
    }

    /** @return void */
    public function testContractOwnsKeepPrefixDetection(): void
    {
        $contract = new QualityGateContract();

        self::assertTrue($contract->isKeepPrefix('K'));
        self::assertTrue($contract->isKeepPrefix('KEEP '));
        self::assertFalse($contract->isKeepPrefix('KEEP corrected'));
        self::assertSame('KEEP', QualityGateContract::KEEP_SENTINEL);
    }

    /**
     * @param string $json Invalid contract JSON.
     * @return void
     */
    #[DataProvider('invalidContractProvider')]
    public function testRejectsInvalidCombinations(string $json): void
    {
        self::assertNull((new QualityGateContract())->parse(
            $json,
            new PipelineStep(type: AssistantPipelineProcessorType::QualityGate),
        ));
    }

    /** @return array<string,array{string}> */
    public static function invalidContractProvider(): array
    {
        return [
            'empty response' => ['  '],
        ];
    }
}
