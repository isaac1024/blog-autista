<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\ImageLinksRelator;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsImagePagesLinkedMother as Ids;
use PHPUnit\Framework\TestCase;

final class ImageLinksRelatorTest extends TestCase
{
    public function testItRelatesTheImageToThePagesItsCaptionLinksToNow(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(
            ResourceType::Image,
            Ids::IMAGE,
            ResourceType::Page,
            [Ids::LINKED, Ids::OTHER_LINKED],
        );

        (new ImageLinksRelator(Ids::create(pageIds: [Ids::LINKED, Ids::OTHER_LINKED])))($repository);
    }

    public function testItRelatesTheImageToNoPageWhenItLinksToNone(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Image, Ids::IMAGE, ResourceType::Page, []);

        (new ImageLinksRelator(Ids::create(pageIds: [])))($repository);
    }
}
