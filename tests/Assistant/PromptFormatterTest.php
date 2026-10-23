<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Assistant;

use Madj2k\AiCore\Assistant\Prompt\Context\Formatter;
use Madj2k\AiCore\Assistant\Prompt\Context\PromptSection;
use PHPUnit\Framework\TestCase;

final class PromptFormatterTest extends TestCase
{
    public function testFormatsTopLevelAndNestedBoundaries(): void
    {
        $formatter = new Formatter();

        self::assertSame(
            "#### BEGIN Query ####\nvalue\n#### END Query ####",
            $formatter->format([new PromptSection('Query', 'value')]),
        );
        self::assertSame(
            "----- BEGIN PRESENTATION RULE: answer_topic -----\nrule\n----- END PRESENTATION RULE: answer_topic -----",
            $formatter->entry('PRESENTATION RULE', 'answer_topic', 'rule'),
        );
    }

    /**
     * Verifies that nested prompt sections are formatted centrally.
     *
     * @return void
     */
    public function testFormatsNestedPromptSections(): void
    {
        $formatter = new Formatter();

        self::assertSame(
            "#### BEGIN Parent ####\nGeneral instructions\n\n==== BEGIN Child ====\nChild content\n==== END Child ====\n#### END Parent ####",
            $formatter->format([
                new PromptSection(
                    'Parent',
                    'General instructions',
                    sections: [new PromptSection('Child', 'Child content')],
                ),
            ]),
        );
    }

    /**
     * Verifies that prompt entries use the third formatting level.
     *
     * @return void
     */
    public function testFormatsThreePromptLevels(): void
    {
        $formatter = new Formatter();

        self::assertSame(
            "#### BEGIN Output Rules ####\n==== BEGIN Selected Presentation Rules ====\n----- BEGIN Selected Presentation Rule -----\nRule content\n----- END Selected Presentation Rule -----\n==== END Selected Presentation Rules ====\n#### END Output Rules ####",
            $formatter->format([
                new PromptSection(
                    'Output Rules',
                    '',
                    sections: [
                        new PromptSection(
                            'Selected Presentation Rules',
                            '',
                            sections: [new PromptSection('Selected Presentation Rule', 'Rule content')],
                        ),
                    ],
                ),
            ]),
        );
    }
}
