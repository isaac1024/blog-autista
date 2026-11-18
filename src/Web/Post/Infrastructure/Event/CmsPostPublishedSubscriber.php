<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Shared\Infrastructure\Event\EventRetry;
use Web\Post\Application\PostSaver;
use Web\Post\Domain\AddressTaken;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\PostRepository;
use Web\Shared\Domain\Projection;

/**
 * Puts on the site each post the CMS publishes.
 */
final readonly class CmsPostPublishedSubscriber implements DomainEventSubscriber
{
    /**
     * Milliseconds to wait before trying again a post whose address another
     * post still has: long enough for a cron run to take the event that
     * frees it.
     */
    private const int RETRY_DELAY = 60_000;

    public function __construct(
        private PostRepository $postRepository,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPostPublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPostPublished) {
            try {
                $this->projection->apply('post', $event, function () use ($event): void {
                    new PostSaver($event)($this->postRepository);
                });
            } catch (AddressTaken $exception) {
                // The event that frees the address is on its way: try again
                // once it has had time to arrive, within the usual retries.
                throw EventRetry::after(self::RETRY_DELAY, $exception);
            }
        }
    }
}
