<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Indexing;

use Madj2k\AiCore\DTO\DocumentMetadata;
use Madj2k\AiCore\Indexing\Adapter\MarkdownAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests Markdown document extraction without involving indexing storage.
 */
final class MarkdownAdapterTest extends TestCase
{
    public function testSplitsAtLevelTwoHeadingsAndKeepsCodeBlocksIntact(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'markdown-adapter-');
        self::assertNotFalse($path);
        file_put_contents($path, <<<'MARKDOWN'
# Handbook

Introduction.

## First section

Text.

### Subsection

```markdown
## Not a section
```

## Second section

More text.
MARKDOWN);

        try {
            $metadata = new DocumentMetadata('file', '/docs/handbook.md', 'handbook.md');
            $documents = (new MarkdownAdapter())->extractDocuments($path, $metadata);

            self::assertCount(3, $documents);
            self::assertSame('Handbook', $documents[0]->getMetadata()->getTitle());
            self::assertStringContainsString('Introduction.', $documents[0]->getContent());
            self::assertStringContainsString('### Subsection', $documents[1]->getContent());
            self::assertStringContainsString('## Not a section', $documents[1]->getContent());
            self::assertSame(
                $documents[0]->getMetadata()->getSourceGroupIdentifier(),
                $documents[2]->getMetadata()->getSourceGroupIdentifier(),
            );
            self::assertNotSame(
                $documents[0]->getMetadata()->getSourceIdentifier(),
                $documents[1]->getMetadata()->getSourceIdentifier(),
            );
        } finally {
            unlink($path);
        }
    }
}
