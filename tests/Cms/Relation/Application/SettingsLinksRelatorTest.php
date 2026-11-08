<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\SettingsLinksRelator;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsSettingsPagesLinkedMother as Ids;
use PHPUnit\Framework\TestCase;

final class SettingsLinksRelatorTest extends TestCase
{
    public function testItRelatesTheSettingsToThePagesTheirMenusLeadToNow(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Settings, Ids::SETTINGS, ResourceType::Page, [Ids::PAGE, Ids::OTHER_PAGE]);

        (new SettingsLinksRelator(Ids::create(pageIds: [Ids::PAGE, Ids::OTHER_PAGE])))($repository);
    }

    public function testItRelatesTheSettingsToNoPageWhenTheirMenusAreEmpty(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Settings, Ids::SETTINGS, ResourceType::Page, []);

        (new SettingsLinksRelator(Ids::create(pageIds: [])))($repository);
    }
}
