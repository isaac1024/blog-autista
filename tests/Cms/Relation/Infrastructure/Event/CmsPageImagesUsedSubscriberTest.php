<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Event;

use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsPageImagesUsedMother as Ids;
use Shared\Domain\Event\EventId;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The images pages tell they embed, through the events as they arrive.
 */
final class CmsPageImagesUsedSubscriberTest extends KernelTestCase
{
    public function testItRelatesThePageToTheImagesItTells(): void
    {
        $this->handle([Ids::IMAGE, Ids::OTHER_IMAGE]);

        self::assertTrue($this->repository()->isUsed(ResourceType::Image, Ids::IMAGE));
        self::assertTrue($this->repository()->isUsed(ResourceType::Image, Ids::OTHER_IMAGE));
    }

    public function testItForgetsTheImagesThePageNoLongerEmbeds(): void
    {
        $this->handle([Ids::IMAGE]);

        $this->handle([Ids::OTHER_IMAGE]);

        self::assertFalse($this->repository()->isUsed(ResourceType::Image, Ids::IMAGE));
        self::assertTrue($this->repository()->isUsed(ResourceType::Image, Ids::OTHER_IMAGE));
    }

    /**
     * @param list<string> $imageIds
     */
    private function handle(array $imageIds): void
    {
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            'cms.page.images_used',
            EventId::random()->value(),
            Ids::PAGE,
            ClockMother::now()->format(\DATE_ATOM),
            ['image_ids' => $imageIds],
        ));
    }

    private function repository(): RelationRepository
    {
        return self::getContainer()->get(RelationRepository::class);
    }
}
