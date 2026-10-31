<?php
declare(strict_types=1);

/*
 * This file is part of madj2k/ai-core.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Madj2k\AiCore\Tests\Assistant\Context\Trace;

use Madj2k\AiCore\Assistant\Context\Trace\ProcessingTrace;
use PHPUnit\Framework\TestCase;

final class ProcessingTraceTest extends TestCase
{
    public function testNullStepIdentifierIsNormalizedForUnpersistedSteps(): void
    {
        $trace = new ProcessingTrace();

        $trace->add('step.completed', null, ['input' => true], ['output' => true]);

        self::assertSame(0, $trace->all()[0]['step_id']);
    }
}
