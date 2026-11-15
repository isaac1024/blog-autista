<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\PostLinksRelator;
use Cms\Relation\Domain\CmsPostPagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use PHPUnit\Framework\TestCase;

final class PostLinksRelatorTest extends TestCase
{
    private const string SOURCE = '01a1da50-0000-7000-8000-00000000c001';

    private const string TARGET = '01a1da50-0000-7000-8000-00000000d001';

    private const string OTHER_TARGET = '01a1da50-0000-7000-8000-00000000d002';

    public function testItRelatesThePostToThePagesItsContentLinksToNow(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Post, self::SOURCE, ResourceType::Page, [self::TARGET, self::OTHER_TARGET]);

        new PostLinksRelator(new CmsPostPagesLinked(self::SOURCE, [self::TARGET, self::OTHER_TARGET]))($repository);
    }

    public function testItRelatesThePostToNoPageWhenItHasNone(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Post, self::SOURCE, ResourceType::Page, []);

        new PostLinksRelator(new CmsPostPagesLinked(self::SOURCE, []))($repository);
    }
}
