<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\AuthorImagesRelator;
use Cms\Relation\Domain\CmsAuthorImagesUsed;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each author to the images its bio uses, as the
 * author tells them.
 */
final readonly class CmsAuthorImagesUsedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsAuthorImagesUsed::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsAuthorImagesUsed) {
            new AuthorImagesRelator($event)($this->relationRepository);
        }
    }
}
