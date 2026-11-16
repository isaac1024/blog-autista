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
use Web\Sitemap\Domain\CmsTermPublished;
use Web\Sitemap\Domain\CmsTermUnpublished;
use Web\Sitemap\Domain\Indexable;
use Web\Sitemap\Domain\SitemapFile;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Keeps each section and tag the CMS publishes in the sitemap while search
 * engines may index it, and takes it out when it leaves the site. The
 * sitemap tells one only while it lists some post.
 */
final readonly class SitemapTermsSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private SitemapRepository $repository,
        private Projection $projection,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsTermPublished::class, CmsTermUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsTermPublished) {
            $this->projection->apply('sitemap_term', $event, function () use ($event): void {
                if (!Indexable::robots($event->robots)) {
                    new EntryRemover($event->aggregateId(), $event->lastPublishedAt)($this->repository);

                    return;
                }

                $section = 'section' === $event->taxonomy;
                new EntryPlacer(
                    $event->aggregateId(),
                    $section ? SitemapFile::SECTIONS : SitemapFile::TAGS,
                    $this->urlGenerator->generate($section ? 'web_section' : 'web_tag', ['slug' => $event->slug]),
                    $event->lastPublishedAt,
                    null,
                    [],
                )($this->repository);
            });
        }

        if ($event instanceof CmsTermUnpublished) {
            $this->projection->apply('sitemap_term', $event, function () use ($event): void {
                new EntryRemover($event->aggregateId(), Clock::now())($this->repository);
            });
        }
    }
}
