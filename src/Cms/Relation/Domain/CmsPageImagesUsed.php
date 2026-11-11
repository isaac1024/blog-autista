<?php

declare(strict_types=1);

namespace Cms\Relation\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\ReceivedEvent;

/**
 * The images a page of the CMS embeds changed. Relations declare it on their
 * own, by the name the pages publish it under.
 */
final readonly class CmsPageImagesUsed extends ReceivedEvent
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
        return 'cms.page.images_used';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        return new static($aggregateId, new EventBody($body)->stringList('image_ids'));
    }

    /**
     * @return list<string>
     */
    public function imageIds(): array
    {
        return $this->imageIds;
    }
}
