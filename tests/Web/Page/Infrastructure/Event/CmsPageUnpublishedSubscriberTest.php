<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Event;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
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

        $this->handle(2);

        self::assertNull($this->storedPage());
    }

    public function testItKeepsThePageWhenItWasPublishedAgainAfterwards(): void
    {
        $this->pageOnTheSite('2026-11-07 11:00:00', 2);

        $this->handle();

        self::assertNotNull($this->storedPage());
    }

    public function testItKeepsThePageOffTheSiteWhenItsPublicationIsDeliveredAgain(): void
    {
        $this->pageOnTheSite('2026-11-07 09:00:00');
        $this->pageOnTheSite('2026-11-07 10:00:00', 2);
        $this->handle(3);

        // The queue retries the second publication, which it took for failed.
        $this->pageOnTheSite('2026-11-07 10:00:00', 2);

        self::assertNull($this->storedPage());
    }

    public function testItTakesTheUnpublicationOfAPageNotOnTheSiteAsDone(): void
    {
        $this->handle();

        self::assertNull($this->storedPage());
    }

    public function testItDoesNotRestoreAPageWhenAnOlderPublicationArrivesAfterTheUnpublication(): void
    {
        $this->handle(2);

        $this->pageOnTheSite('2026-11-07 09:00:00');

        self::assertNull($this->storedPage());
    }

    private function pageOnTheSite(string $publishedOn, int $revision = 1): void
    {
        $body = CmsPagePublishedMother::body();
        $body['revision'] = $revision;

        // As the worker does after each message, so the projection reads
        // what it keeps from the database, not from Doctrine's memory.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            'cms.page.published',
            PageIdMother::DEFAULT_VALUE,
            $body,
        ));
    }

    private function handle(int $revision = 1): void
    {
        // As the worker does after each message, so the projection reads
        // what it keeps from the database, not from Doctrine's memory.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            'cms.page.unpublished',
            PageIdMother::DEFAULT_VALUE,
            ['revision' => $revision],
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
