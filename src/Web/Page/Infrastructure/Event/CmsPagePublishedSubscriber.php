<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Shared\Infrastructure\Event\EventRetry;
use Web\Page\Application\PageSaver;
use Web\Page\Domain\CmsPagePublished;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\SlugTaken;
use Web\Projection\Domain\Projection;

/**
 * Puts on the site each page the CMS publishes.
 */
final readonly class CmsPagePublishedSubscriber implements DomainEventSubscriber
{
    /**
     * Milliseconds to wait before trying again a page whose address another
     * page still has: long enough for a cron run to take the event that
     * frees it.
     */
    private const int RETRY_DELAY = 60_000;

    public function __construct(
        private PageRepository $pageRepository,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPagePublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPagePublished) {
            try {
                $this->projection->apply('page', $event, function () use ($event): void {
                    new PageSaver($event)($this->pageRepository);
                });
            } catch (SlugTaken $exception) {
                // The event that frees the address is on its way: try again
                // once it has had time to arrive, within the usual retries.
                throw EventRetry::after(self::RETRY_DELAY, $exception);
            }
        }
    }
}
