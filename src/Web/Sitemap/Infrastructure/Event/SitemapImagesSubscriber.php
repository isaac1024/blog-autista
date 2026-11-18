<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Event;

use Shared\Domain\Clock;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Shared\Domain\Projection;
use Web\Sitemap\Application\ImageEntriesToucher;
use Web\Sitemap\Domain\CmsImagePublished;
use Web\Sitemap\Domain\CmsImageUnpublished;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Tells the sitemap each image the CMS puts on the site or takes off it,
 * as the posts that open with it name it, or another one its address now
 * leads to, or none.
 */
final readonly class SitemapImagesSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private SitemapRepository $repository,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsImagePublished::class, CmsImageUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsImagePublished || $event instanceof CmsImageUnpublished) {
            $this->projection->apply('sitemap_image', $event, function () use ($event): void {
                new ImageEntriesToucher($event->aggregateId(), Clock::now())($this->repository);
            });
        }
    }
}
