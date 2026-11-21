<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Page\Application\PageRemover;
use Web\Page\Domain\CmsPageUnpublished;
use Web\Page\Domain\PageRepository;
use Web\Shared\Domain\Projection;
use Web\Shared\Infrastructure\Cache\SiteCachePurge;

/**
 * Takes off the site each page the CMS unpublishes.
 */
final readonly class CmsPageUnpublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private PageRepository $pageRepository,
        private Projection $projection,
        private SiteCachePurge $cachePurge,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPageUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPageUnpublished) {
            // Forgotten by the page cache once applied, so it doesn't show until it expires.
            $this->cachePurge->around('page', $event->aggregateId(), fn () => $this->projection->apply('page', $event, function () use ($event): void {
                new PageRemover($event)($this->pageRepository);
            }));
        }
    }
}
