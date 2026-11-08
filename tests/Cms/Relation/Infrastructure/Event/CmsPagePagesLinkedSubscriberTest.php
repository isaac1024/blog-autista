<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Event;

use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsPagePagesLinkedMother as Ids;
use Shared\Domain\Event\EventId;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The pages pages tell they link to, through the events as they arrive.
 */
final class CmsPagePagesLinkedSubscriberTest extends KernelTestCase
{
    public function testItRelatesThePageToThePagesItTells(): void
    {
        $this->handle([Ids::LINKED, Ids::OTHER_LINKED]);

        self::assertTrue($this->repository()->isUsed(ResourceType::Page, Ids::LINKED));
        self::assertTrue($this->repository()->isUsed(ResourceType::Page, Ids::OTHER_LINKED));
    }

    public function testItForgetsThePagesThePageNoLongerLinksTo(): void
    {
        $this->handle([Ids::LINKED]);
        $this->handle([Ids::OTHER_LINKED]);

        self::assertFalse($this->repository()->isUsed(ResourceType::Page, Ids::LINKED));
        self::assertTrue($this->repository()->isUsed(ResourceType::Page, Ids::OTHER_LINKED));
    }

    /**
     * Pages and images are related apart: telling one leaves the other be.
     */
    public function testItKeepsTheImagesThePageEmbeds(): void
    {
        $this->repository()->replace(ResourceType::Page, Ids::PAGE, ResourceType::Image, ['01a1b801-ce58-723d-8234-b76d9105d001']);

        $this->handle([]);

        self::assertTrue($this->repository()->isUsed(ResourceType::Image, '01a1b801-ce58-723d-8234-b76d9105d001'));
    }

    /**
     * @param list<string> $pageIds
     */
    private function handle(array $pageIds): void
    {
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            'cms.page.pages_linked',
            EventId::random()->value(),
            Ids::PAGE,
            ClockMother::now()->format(\DATE_ATOM),
            ['page_ids' => $pageIds],
        ));
    }

    private function repository(): RelationRepository
    {
        return self::getContainer()->get(RelationRepository::class);
    }
}
