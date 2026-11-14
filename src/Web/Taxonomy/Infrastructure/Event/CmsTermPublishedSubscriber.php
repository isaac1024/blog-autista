<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Shared\Infrastructure\Event\EventRetry;
use Web\Projection\Domain\Projection;
use Web\Taxonomy\Application\TermSaver;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\SlugTaken;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Puts on the site each term the CMS publishes.
 */
final readonly class CmsTermPublishedSubscriber implements DomainEventSubscriber
{
    /**
     * Milliseconds to wait before trying again a term whose address another
     * term still has: long enough for a cron run to take the event that
     * frees it.
     */
    private const int RETRY_DELAY = 60_000;

    public function __construct(
        private TermRepository $termRepository,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsTermPublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsTermPublished) {
            try {
                $this->projection->apply('term', $event, function () use ($event): void {
                    new TermSaver($event)($this->termRepository);
                });
            } catch (SlugTaken $exception) {
                // The event that frees the address is on its way: try again
                // once it has had time to arrive, within the usual retries.
                throw EventRetry::after(self::RETRY_DELAY, $exception);
            }
        }
    }
}
