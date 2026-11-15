<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\ImageContentsRelator;
use Cms\Relation\Domain\CmsImageContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use PHPUnit\Framework\TestCase;

final class ImageContentsRelatorTest extends TestCase
{
    private const string SOURCE = '01a1da50-0000-7000-8000-00000000c001';

    /**
     * Each kind of content its caption links to, apart, with none left of those
     * it no longer links to.
     */
    public function testItRelatesTheImageToTheContentsCaptionLinksToNow(): void
    {
        $replaced = [];
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::exactly(4))->method('replace')->willReturnCallback(static function (ResourceType $source, string $id, ResourceType $target, array $ids) use (&$replaced): void {
            $replaced[] = [$source, $id, $target, $ids];
        });

        new ImageContentsRelator(new CmsImageContentsLinked(self::SOURCE, ['post'], ['term'], [], ['external']))($repository);

        self::assertSame([
            [ResourceType::Image, self::SOURCE, ResourceType::Post, ['post']],
            [ResourceType::Image, self::SOURCE, ResourceType::Term, ['term']],
            [ResourceType::Image, self::SOURCE, ResourceType::Author, []],
            [ResourceType::Image, self::SOURCE, ResourceType::External, ['external']],
        ], $replaced);
    }
}
