<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\PostExternalsRelator;
use Cms\Relation\Domain\CmsPostExternalsLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use PHPUnit\Framework\TestCase;

final class PostExternalsRelatorTest extends TestCase
{
    private const string SOURCE = '01a1da50-0000-7000-8000-00000000c001';

    private const string TARGET = '01a1da50-0000-7000-8000-00000000d001';

    private const string OTHER_TARGET = '01a1da50-0000-7000-8000-00000000d002';

    public function testItRelatesThePostToTheExternalsItsContentLinksToNow(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Post, self::SOURCE, ResourceType::External, [self::TARGET, self::OTHER_TARGET]);

        new PostExternalsRelator(new CmsPostExternalsLinked(self::SOURCE, [self::TARGET, self::OTHER_TARGET]))($repository);
    }

    public function testItRelatesThePostToNoExternalWhenItHasNone(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Post, self::SOURCE, ResourceType::External, []);

        new PostExternalsRelator(new CmsPostExternalsLinked(self::SOURCE, []))($repository);
    }
}
