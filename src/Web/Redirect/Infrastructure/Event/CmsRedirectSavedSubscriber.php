<?php

declare(strict_types=1);

namespace Web\Redirect\Infrastructure\Event;

use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Projection\Domain\Projection;
use Web\Redirect\Application\RedirectSaver;
use Web\Redirect\Domain\CmsRedirectSaved;
use Web\Redirect\Domain\RedirectRepository;

/**
 * Puts on the site each redirect the CMS creates or changes.
 */
final readonly class CmsRedirectSavedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private RedirectRepository $redirectRepository,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsRedirectSaved::class];
    }

    #[\Override]
    public function handle(DomainEvent $event): void
    {
        if ($event instanceof CmsRedirectSaved) {
            $this->projection->apply('redirect', $event, function () use ($event): void {
                new RedirectSaver($event)($this->redirectRepository);
            });
        }
    }
}
