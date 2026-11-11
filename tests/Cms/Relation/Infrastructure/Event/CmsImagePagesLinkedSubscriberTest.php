<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Event;

use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsImagePagesLinkedMother as Ids;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The pages the caption of an image tells it links to, through the events
 * as they arrive.
 */
final class CmsImagePagesLinkedSubscriberTest extends KernelTestCase
{
    public function testItRelatesTheImageToThePagesItTells(): void
    {
        $this->handle([Ids::LINKED]);

        self::assertTrue($this->repository()->isUsed(ResourceType::Page, Ids::LINKED));
    }

    public function testItForgetsThePagesTheImageNoLongerLinksTo(): void
    {
        $this->handle([Ids::LINKED]);
        $this->handle([Ids::OTHER_LINKED]);

        self::assertFalse($this->repository()->isUsed(ResourceType::Page, Ids::LINKED));
        self::assertTrue($this->repository()->isUsed(ResourceType::Page, Ids::OTHER_LINKED));
    }

    /**
     * @param list<string> $pageIds
     */
    private function handle(array $pageIds): void
    {
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            'cms.image.pages_linked',
            Ids::IMAGE,
            ['page_ids' => $pageIds],
        ));
    }

    private function repository(): RelationRepository
    {
        return self::getContainer()->get(RelationRepository::class);
    }
}
