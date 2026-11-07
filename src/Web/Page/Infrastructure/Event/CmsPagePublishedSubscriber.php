<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Event;

use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Page\Application\PageSaver;
use Web\Page\Domain\CmsPagePublished;
use Web\Page\Domain\PageRepository;

/**
 * Puts on the site each page the CMS publishes.
 */
final readonly class CmsPagePublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(private PageRepository $pageRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPagePublished::class];
    }

    #[\Override]
    public function handle(DomainEvent $event): void
    {
        if ($event instanceof CmsPagePublished) {
            new PageSaver($event)($this->pageRepository);
        }
    }
}
