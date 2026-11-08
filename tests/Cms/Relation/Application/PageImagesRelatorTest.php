<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\PageImagesRelator;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsPageImagesUsedMother;
use PHPUnit\Framework\TestCase;

final class PageImagesRelatorTest extends TestCase
{
    public function testItRelatesThePageToTheImagesItEmbedsNow(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(
            ResourceType::Page,
            CmsPageImagesUsedMother::PAGE,
            ResourceType::Image,
            [CmsPageImagesUsedMother::IMAGE, CmsPageImagesUsedMother::OTHER_IMAGE],
        );

        (new PageImagesRelator(CmsPageImagesUsedMother::create(imageIds: [CmsPageImagesUsedMother::IMAGE, CmsPageImagesUsedMother::OTHER_IMAGE])))($repository);
    }

    public function testItRelatesThePageToNoImageWhenItEmbedsNone(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Page, CmsPageImagesUsedMother::PAGE, ResourceType::Image, []);

        (new PageImagesRelator(CmsPageImagesUsedMother::create(imageIds: [])))($repository);
    }
}
