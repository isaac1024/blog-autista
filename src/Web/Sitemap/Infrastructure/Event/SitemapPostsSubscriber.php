<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Event;

use Shared\Domain\Clock;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Projection\Domain\Projection;
use Web\Sitemap\Application\PostEntryPlacer;
use Web\Sitemap\Application\PostEntryRemover;
use Web\Sitemap\Domain\CmsPostPublished;
use Web\Sitemap\Domain\CmsPostUnpublished;
use Web\Sitemap\Domain\Indexable;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Keeps each post the CMS publishes in the sitemap of the year it was first
 * published, while search engines may index it, and takes it out when it
 * leaves the site; the lists of its section and tags change with it.
 */
final readonly class SitemapPostsSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private SitemapRepository $repository,
        private Projection $projection,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPostPublished::class, CmsPostUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPostPublished) {
            $this->projection->apply('sitemap_post', $event, function () use ($event): void {
                $day = new \DateTimeImmutable($event->day);
                new PostEntryPlacer(
                    $event->aggregateId(),
                    $this->urlGenerator->generate('web_post', [
                        'section' => $event->sectionSlug,
                        'year' => $day->format('Y'),
                        'month' => $day->format('m'),
                        'day' => $day->format('d'),
                        'slug' => $event->slug,
                    ]),
                    $day,
                    $event->lastPublishedAt,
                    $event->openingImageId,
                    [$event->sectionId, ...$event->tagIds],
                    Indexable::robots($event->robots),
                )($this->repository);
            });
        }

        if ($event instanceof CmsPostUnpublished) {
            $this->projection->apply('sitemap_post', $event, function () use ($event): void {
                new PostEntryRemover($event->aggregateId(), Clock::now())($this->repository);
            });
        }
    }
}
