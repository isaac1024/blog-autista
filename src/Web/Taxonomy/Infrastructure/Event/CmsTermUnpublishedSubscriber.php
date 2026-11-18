<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Event;

use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Shared\Domain\Projection;
use Web\Taxonomy\Application\TermRemover;
use Web\Taxonomy\Domain\CmsTermUnpublished;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Takes off the site each term the CMS unpublishes.
 */
final readonly class CmsTermUnpublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(
        private TermRepository $termRepository,
        private Projection $projection,
    ) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsTermUnpublished::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsTermUnpublished) {
            $this->projection->apply('term', $event, function () use ($event): void {
                new TermRemover($event)($this->termRepository);
            });
        }
    }
}
