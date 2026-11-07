<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Event;

use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Page\Application\PageRemover;
use Web\Page\Domain\CmsPageUnpublished;
use Web\Page\Domain\PageRepository;

/**
 * Takes off the site each page the CMS unpublishes.
 */
final readonly class CmsPageUnpublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(private PageRepository $pageRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPageUnpublished::class];
    }

    #[\Override]
    public function handle(DomainEvent $event): void
    {
        if ($event instanceof CmsPageUnpublished) {
            new PageRemover($event)($this->pageRepository);
        }
    }
}
