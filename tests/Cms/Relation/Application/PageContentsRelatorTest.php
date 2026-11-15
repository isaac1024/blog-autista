<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\PageContentsRelator;
use Cms\Relation\Domain\CmsPageContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use PHPUnit\Framework\TestCase;

final class PageContentsRelatorTest extends TestCase
{
    private const string SOURCE = '01a1da50-0000-7000-8000-00000000c001';

    /**
     * Each kind of content its content links to, apart, with none left of those
     * it no longer links to.
     */
    public function testItRelatesThePageToTheContentsContentLinksToNow(): void
    {
        $replaced = [];
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::exactly(4))->method('replace')->willReturnCallback(static function (ResourceType $source, string $id, ResourceType $target, array $ids) use (&$replaced): void {
            $replaced[] = [$source, $id, $target, $ids];
        });

        new PageContentsRelator(new CmsPageContentsLinked(self::SOURCE, ['post'], ['term'], [], ['external']))($repository);

        self::assertSame([
            [ResourceType::Page, self::SOURCE, ResourceType::Post, ['post']],
            [ResourceType::Page, self::SOURCE, ResourceType::Term, ['term']],
            [ResourceType::Page, self::SOURCE, ResourceType::Author, []],
            [ResourceType::Page, self::SOURCE, ResourceType::External, ['external']],
        ], $replaced);
    }
}
