<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Event;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Event\EventId;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

/**
 * Hands the subscriber the unpublications as they come out of the queue, by
 * name, as the CMS sends them.
 */
final class CmsPageUnpublishedSubscriberTest extends KernelTestCase
{
    public function testItTakesOffTheSiteThePageTheCmsUnpublishes(): void
    {
        $this->pageOnTheSite('2026-11-07 09:00:00');

        $this->handle();

        self::assertNull($this->storedPage());
    }

    public function testItKeepsThePageWhenItWasPublishedAgainAfterwards(): void
    {
        $this->pageOnTheSite('2026-11-07 11:00:00');

        $this->handle();

        self::assertNotNull($this->storedPage());
    }

    public function testItTakesTheUnpublicationOfAPageNotOnTheSiteAsDone(): void
    {
        $this->handle();

        self::assertNull($this->storedPage());
    }

    private function pageOnTheSite(string $publishedOn): void
    {
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(occurredOn: $publishedOn)));
    }

    private function handle(): void
    {
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            'cms.page.unpublished',
            EventId::random()->value(),
            PageIdMother::DEFAULT_VALUE,
            ClockMother::now()->format(\DATE_ATOM),
            [],
        ));
    }

    /**
     * Reads the page back from the database, not from Doctrine's identity map.
     */
    private function storedPage(): ?Page
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(PageRepository::class)->find(PageIdMother::create());
    }
}
