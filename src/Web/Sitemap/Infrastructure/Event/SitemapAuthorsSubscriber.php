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
use Web\Sitemap\Domain\CmsAuthorPublished;
use Web\Sitemap\Domain\CmsAuthorUnpublished;
use Web\Sitemap\Domain\Indexable;
use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Keeps the page of each author the CMS publishes in the sitemap while
 * search engines may index it, and takes it out when it leaves the site.
 */
final readonly class SitemapAuthorsSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private SitemapRepository $repository,
        private Projection $projection,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsAuthorPublished::class, CmsAuthorUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsAuthorPublished) {
            $this->projection->apply('sitemap_author', $event, function () use ($event): void {
                if (!Indexable::robots($event->robots)) {
                    new EntryRemover($event->aggregateId(), $event->lastPublishedAt)($this->repository);

                    return;
                }

                new EntryPlacer($event->aggregateId(), SitemapFile::AUTHORS, $this->urlGenerator->generate('web_author', ['slug' => $event->slug]), $event->lastPublishedAt, null, [])($this->repository);
            });
        }

        if ($event instanceof CmsAuthorUnpublished) {
            $this->projection->apply('sitemap_author', $event, function () use ($event): void {
                new EntryRemover($event->aggregateId(), Clock::now())($this->repository);
            });
        }
    }
}
