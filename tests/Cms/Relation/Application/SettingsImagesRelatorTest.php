<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\SettingsImagesRelator;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsSettingsImagesUsedMother as Ids;
use PHPUnit\Framework\TestCase;

final class SettingsImagesRelatorTest extends TestCase
{
    public function testItRelatesTheSettingsToTheImagesTheyUseNow(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Settings, Ids::SETTINGS, ResourceType::Image, [Ids::IMAGE, Ids::OTHER_IMAGE]);

        (new SettingsImagesRelator(Ids::create(imageIds: [Ids::IMAGE, Ids::OTHER_IMAGE])))($repository);
    }

    public function testItRelatesTheSettingsToNoImageWhenTheyUseNone(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Settings, Ids::SETTINGS, ResourceType::Image, []);

        (new SettingsImagesRelator(Ids::create(imageIds: [])))($repository);
    }
}
