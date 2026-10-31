<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant\Pipeline\Processor\Llm;

use Madj2k\AiCore\Assistant\Context\Presentation\PresentationRulePlan;
use PHPUnit\Framework\TestCase;

final class IntentSelectionTest extends TestCase
{
    public function testBothPlansDefaultToAllAndSupportAllSelectionModes(): void
    {
        $retrieval = new \Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalPlan();
        $presentation = new PresentationRulePlan();
        self::assertTrue($retrieval->allows('source'));
        self::assertTrue($presentation->allows('rule'));

        foreach ([
            ['selected', ['one'], ['one', 'rule']],
            ['all_except', ['one'], ['two', 'rule']],
            ['none', [], []],
        ] as [$mode, $values, $allowed]) {
            $retrieval = new \Madj2k\AiCore\Assistant\Context\Retrieval\RetrievalPlan($mode, $values);
            $presentation = new PresentationRulePlan($mode, $values);
            self::assertSame(in_array('one', $allowed, true), $retrieval->allows('one'));
            self::assertSame($mode === 'all_except', $presentation->allows('rule'));
        }
    }

}
