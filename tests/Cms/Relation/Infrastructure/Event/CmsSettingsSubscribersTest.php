<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Event;

use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsPageImagesUsedMother;
use Cms\Tests\Relation\Domain\CmsSettingsImagesUsedMother as Images;
use Cms\Tests\Relation\Domain\CmsSettingsPagesLinkedMother as Pages;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The images and pages the settings tell they use, through the events as
 * they arrive.
 */
final class CmsSettingsSubscribersTest extends KernelTestCase
{
    public function testItRelatesTheSettingsToTheImagesTheyTell(): void
    {
        $this->handle('cms.settings.images_used', ['image_ids' => [Images::IMAGE, Images::OTHER_IMAGE]]);

        self::assertTrue($this->repository()->isUsed(ResourceType::Image, Images::IMAGE));
        self::assertTrue($this->repository()->isUsed(ResourceType::Image, Images::OTHER_IMAGE));
    }

    public function testItForgetsTheImagesTheSettingsNoLongerUse(): void
    {
        $this->handle('cms.settings.images_used', ['image_ids' => [Images::IMAGE]]);

        $this->handle('cms.settings.images_used', ['image_ids' => [Images::OTHER_IMAGE]]);

        self::assertFalse($this->repository()->isUsed(ResourceType::Image, Images::IMAGE));
        self::assertTrue($this->repository()->isUsed(ResourceType::Image, Images::OTHER_IMAGE));
    }

    public function testItRelatesTheSettingsToThePagesTheyTell(): void
    {
        $this->handle('cms.settings.pages_linked', ['page_ids' => [Pages::PAGE, Pages::OTHER_PAGE]]);

        self::assertTrue($this->repository()->isUsed(ResourceType::Page, Pages::PAGE));
        self::assertTrue($this->repository()->isUsed(ResourceType::Page, Pages::OTHER_PAGE));
    }

    public function testItForgetsThePagesTheMenusNoLongerLeadTo(): void
    {
        $this->handle('cms.settings.pages_linked', ['page_ids' => [Pages::PAGE]]);

        $this->handle('cms.settings.pages_linked', ['page_ids' => [Pages::OTHER_PAGE]]);

        self::assertFalse($this->repository()->isUsed(ResourceType::Page, Pages::PAGE));
        self::assertTrue($this->repository()->isUsed(ResourceType::Page, Pages::OTHER_PAGE));
    }

    /**
     * What the settings use and what a page embeds are kept apart: the
     * settings forgetting an image leave it in use by the page.
     */
    public function testItKeepsTheImagesAPageUsesWhenTheSettingsForgetThem(): void
    {
        $image = Images::IMAGE;
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            'cms.page.images_used',
            CmsPageImagesUsedMother::PAGE,
            ['image_ids' => [$image]],
        ));
        $this->handle('cms.settings.images_used', ['image_ids' => [$image]]);

        $this->handle('cms.settings.images_used', ['image_ids' => []]);

        self::assertTrue($this->repository()->isUsed(ResourceType::Image, $image));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(string $name, array $body): void
    {
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            $name,
            Images::SETTINGS,
            $body,
        ));
    }

    private function repository(): RelationRepository
    {
        return self::getContainer()->get(RelationRepository::class);
    }
}
