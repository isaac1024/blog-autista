<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Event;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Event\EventId;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Infrastructure\Event\EventRetry;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

/**
 * Hands the subscriber the publications as they come out of the queue, by
 * name and primitives, as the CMS sends them.
 */
final class CmsPagePublishedSubscriberTest extends KernelTestCase
{
    private const string OTHER_PAGE = '01a1b835-7cfd-7864-b83b-873347d10bab';

    public function testItPutsOnTheSiteThePageTheCmsPublishes(): void
    {
        $this->handle(CmsPagePublishedMother::body());

        self::assertEquals(Page::publish(CmsPagePublishedMother::create()), $this->storedPage());
    }

    public function testItPutsTheHomePageOnTheSiteWithoutASlug(): void
    {
        $this->handle(CmsPagePublishedMother::body(title: 'Inicio', slug: null, type: 'home'));

        self::assertEquals(Page::publish(CmsPagePublishedMother::create(title: 'Inicio', slug: null, type: PageType::Home)), $this->storedPage());
    }

    public function testItReplacesThePageWithTheVersionTheCmsPublishesLater(): void
    {
        $this->handle(CmsPagePublishedMother::body());

        $this->handle(CmsPagePublishedMother::body(title: 'Quién soy', slug: 'quien-soy'), '2026-11-07 11:00:00', 2);

        $page = $this->storedPage();
        self::assertNotNull($page);
        self::assertSame('Quién soy', $page->title());
        self::assertSame('quien-soy', $page->slug());
    }

    public function testItKeepsThePageWhenAnEarlierVersionArrivesLate(): void
    {
        $this->handle(CmsPagePublishedMother::body(title: 'Quién soy'), '2026-11-07 11:00:00', 2);

        $this->handle(CmsPagePublishedMother::body(title: 'Sobre mí'), '2026-11-07 09:00:00');

        self::assertSame('Quién soy', $this->storedPage()?->title());
    }

    public function testItWaitsForAnotherPageToFreeTheAddressBeforeTakingIt(): void
    {
        $this->handle(CmsPagePublishedMother::body(slug: 'sobre-mi'), id: self::OTHER_PAGE);

        try {
            $this->handle(CmsPagePublishedMother::body(slug: 'sobre-mi'));
            self::fail('A page was put at the address of another one.');
        } catch (EventRetry $retry) {
            self::assertFalse($retry->forceRetry(), 'the retries stay within those of the transport');
            self::assertSame(60_000, $retry->getRetryDelay(), 'it waits a minute, for a cron run to take the event that frees the address');
            self::assertNull($this->storedPage());
        }

        $this->dispatch('cms.page.unpublished', self::OTHER_PAGE, ['revision' => 2]);
        $this->handle(CmsPagePublishedMother::body(slug: 'sobre-mi'));

        self::assertSame('sobre-mi', $this->storedPage()?->slug());
    }

    public function testItRefusesAPublicationWithoutRevision(): void
    {
        try {
            $this->dispatch('cms.page.published', PageIdMother::DEFAULT_VALUE, CmsPagePublishedMother::body());
            self::fail('A publication without revision was taken.');
        } catch (\UnexpectedValueException) {
            self::assertNull($this->storedPage());
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(array $body, string $occurredOn = ClockMother::NOW, int $revision = 1, string $id = PageIdMother::DEFAULT_VALUE): void
    {
        $body['revision'] = $revision;

        $this->dispatch('cms.page.published', $id, $body, $occurredOn);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatch(string $name, string $id, array $body, string $occurredOn = ClockMother::NOW): void
    {
        // As the worker does after each message, so the projection reads
        // what it keeps from the database, not from Doctrine's memory.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            $name,
            EventId::random()->value(),
            $id,
            ClockMother::now($occurredOn)->format(\DATE_ATOM),
            $body,
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
