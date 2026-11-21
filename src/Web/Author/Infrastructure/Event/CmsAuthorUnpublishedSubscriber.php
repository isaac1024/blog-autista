<?php

declare(strict_types=1);

namespace Web\Author\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Author\Application\AuthorRemover;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\AvatarCopies;
use Web\Author\Domain\CmsAuthorUnpublished;
use Web\Shared\Domain\Projection;
use Web\Shared\Infrastructure\Cache\SiteCachePurge;

/**
 * Takes off the site each author the CMS unpublishes.
 */
final readonly class CmsAuthorUnpublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private AuthorRepository $authorRepository,
        private AvatarCopies $avatarCopies,
        private Projection $projection,
        private SiteCachePurge $cachePurge,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsAuthorUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsAuthorUnpublished) {
            // Forgotten by the page cache once applied, so it doesn't show until it expires.
            $this->cachePurge->around('author', $event->aggregateId(), fn () => $this->projection->apply('author', $event, function () use ($event): void {
                new AuthorRemover($event)($this->authorRepository, $this->avatarCopies);
            }));
        }
    }
}
