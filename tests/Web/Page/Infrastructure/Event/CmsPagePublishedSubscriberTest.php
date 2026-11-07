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
 * Hands the subscriber the publications as they come out of the queue, by
 * name and primitives, as the CMS sends them.
 */
final class CmsPagePublishedSubscriberTest extends KernelTestCase
{
    public function testItPutsOnTheSiteThePageTheCmsPublishes(): void
    {
        $this->handle(CmsPagePublishedMother::body());

        self::assertEquals(Page::publish(CmsPagePublishedMother::create()), $this->storedPage());
    }

    public function testItReplacesThePageWithTheVersionTheCmsPublishesLater(): void
    {
        $this->handle(CmsPagePublishedMother::body());

        $this->handle(CmsPagePublishedMother::body(title: 'Quién soy', slug: 'quien-soy'), '2026-11-07 11:00:00');

        $page = $this->storedPage();
        self::assertNotNull($page);
        self::assertSame('Quién soy', $page->title());
        self::assertSame('quien-soy', $page->slug());
    }

    public function testItKeepsThePageWhenAnEarlierVersionArrivesLate(): void
    {
        $this->handle(CmsPagePublishedMother::body(title: 'Quién soy'), '2026-11-07 11:00:00');

        $this->handle(CmsPagePublishedMother::body(title: 'Sobre mí'), '2026-11-07 09:00:00');

        self::assertSame('Quién soy', $this->storedPage()?->title());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(array $body, string $occurredOn = ClockMother::NOW): void
    {
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            'cms.page.published',
            EventId::random()->value(),
            PageIdMother::DEFAULT_VALUE,
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
