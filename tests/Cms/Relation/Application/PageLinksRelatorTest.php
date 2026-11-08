<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\PageLinksRelator;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsPagePagesLinkedMother as Ids;
use PHPUnit\Framework\TestCase;

final class PageLinksRelatorTest extends TestCase
{
    public function testItRelatesThePageToThePagesItLinksToNow(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(
            ResourceType::Page,
            Ids::PAGE,
            ResourceType::Page,
            [Ids::LINKED, Ids::OTHER_LINKED],
        );

        (new PageLinksRelator(Ids::create(pageIds: [Ids::LINKED, Ids::OTHER_LINKED])))($repository);
    }

    public function testItRelatesThePageToNoPageWhenItLinksToNone(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Page, Ids::PAGE, ResourceType::Page, []);

        (new PageLinksRelator(Ids::create(pageIds: [])))($repository);
    }
}
