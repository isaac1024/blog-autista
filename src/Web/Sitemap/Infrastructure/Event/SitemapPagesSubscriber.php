<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Event;

use Shared\Domain\Clock;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Projection\Domain\Projection;
use Web\Sitemap\Application\EntryPlacer;
use Web\Sitemap\Application\EntryRemover;
use Web\Sitemap\Domain\CmsPagePublished;
use Web\Sitemap\Domain\CmsPageUnpublished;
use Web\Sitemap\Domain\Indexable;
use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Keeps each page the CMS publishes in the sitemap while search engines may
 * index it, the home page at the root of the site, and takes it out when
 * it leaves the site.
 */
final readonly class SitemapPagesSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private SitemapRepository $repository,
        private Projection $projection,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPagePublished::class, CmsPageUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPagePublished) {
            $this->projection->apply('sitemap_page', $event, function () use ($event): void {
                if (!Indexable::robots($event->robots)) {
                    new EntryRemover($event->aggregateId(), $event->lastPublishedAt)($this->repository);

                    return;
                }

                $path = null === $event->slug
                    ? $this->urlGenerator->generate('web_home')
                    : $this->urlGenerator->generate('web_page', ['slug' => $event->slug]);
                new EntryPlacer($event->aggregateId(), SitemapFile::PAGES, $path, $event->lastPublishedAt, null, [])($this->repository);
            });
        }

        if ($event instanceof CmsPageUnpublished) {
            $this->projection->apply('sitemap_page', $event, function () use ($event): void {
                new EntryRemover($event->aggregateId(), Clock::now())($this->repository);
            });
        }
    }
}
