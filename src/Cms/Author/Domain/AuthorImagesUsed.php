<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;

/**
 * The images the bio of an author embeds changed: those of the version being
 * edited and of the one last published, together; none once the author is
 * deleted. It is told at once, so whoever keeps track of what is in use is
 * never behind the author.
 */
final readonly class AuthorImagesUsed extends DomainEvent implements DeliveredImmediately
{
    /**
     * @param list<string> $imageIds
     */
    public function __construct(
        string $aggregateId,
        private array $imageIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.author.images_used';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['image_ids' => $this->imageIds];
    }
}
