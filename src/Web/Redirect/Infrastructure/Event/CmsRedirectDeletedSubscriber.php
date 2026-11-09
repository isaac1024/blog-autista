<?php

declare(strict_types=1);

namespace Web\Redirect\Infrastructure\Event;

use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Projection\Domain\Projection;
use Web\Redirect\Application\RedirectRemover;
use Web\Redirect\Domain\CmsRedirectDeleted;
use Web\Redirect\Domain\RedirectRepository;

/**
 * Takes off the site each redirect the CMS deletes.
 */
final readonly class CmsRedirectDeletedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private RedirectRepository $redirectRepository,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsRedirectDeleted::class];
    }

    #[\Override]
    public function handle(DomainEvent $event): void
    {
        if ($event instanceof CmsRedirectDeleted) {
            $this->projection->apply('redirect', $event, function () use ($event): void {
                new RedirectRemover($event)($this->redirectRepository);
            });
        }
    }
}
