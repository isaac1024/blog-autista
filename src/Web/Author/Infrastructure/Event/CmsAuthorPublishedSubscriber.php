<?php

declare(strict_types=1);

namespace Web\Author\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Shared\Infrastructure\Event\EventRetry;
use Web\Author\Application\AuthorSaver;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\AvatarCopies;
use Web\Author\Domain\CmsAuthorPublished;
use Web\Author\Domain\SlugTaken;
use Web\Shared\Domain\Projection;

/**
 * Puts on the site each author the CMS publishes.
 */
final readonly class CmsAuthorPublishedSubscriber implements DomainEventSubscriber
{
    /**
     * Milliseconds to wait before trying again an author whose address
     * another author still has: long enough for a cron run to take the
     * event that frees it.
     */
    private const int RETRY_DELAY = 60_000;

    public function __construct(
        private AuthorRepository $authorRepository,
        private AvatarCopies $avatarCopies,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsAuthorPublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsAuthorPublished) {
            try {
                $this->projection->apply('author', $event, function () use ($event): void {
                    new AuthorSaver($event)($this->authorRepository, $this->avatarCopies);
                });
            } catch (SlugTaken $exception) {
                // The event that frees the address is on its way: try again
                // once it has had time to arrive, within the usual retries.
                throw EventRetry::after(self::RETRY_DELAY, $exception);
            }
        }
    }
}
