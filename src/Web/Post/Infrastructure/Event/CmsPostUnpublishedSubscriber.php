<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Post\Application\PostRemover;
use Web\Post\Domain\CmsPostUnpublished;
use Web\Post\Domain\PostRepository;
use Web\Shared\Domain\Projection;

/**
 * Takes off the site each post the CMS unpublishes.
 */
final readonly class CmsPostUnpublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private PostRepository $postRepository,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPostUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPostUnpublished) {
            $this->projection->apply('post', $event, function () use ($event): void {
                new PostRemover($event)($this->postRepository);
            });
        }
    }
}
