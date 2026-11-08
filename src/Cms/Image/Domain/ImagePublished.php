<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBody;
use Shared\Domain\RichText\RichText;

/**
 * An image was put on the site, or its version there replaced, with all the
 * site needs to serve it: its texts and where its file is kept.
 */
final readonly class ImagePublished extends DomainEvent
{
    /**
     * @param array{version: int, blocks: list<array<string, mixed>>} $caption in the stored rich text format
     * @param string                                                  $path    where its file is kept, relative to the image storage
     */
    public function __construct(
        string $aggregateId,
        private string $alt,
        private array $caption,
        private string $path,
        string $eventId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, \DateTimeImmutable $occurredOn): static
    {
        $body = new EventBody($body);

        return new static(
            $aggregateId,
            $body->string('alt'),
            RichText::fromArray($body->array('caption'))->toArray(),
            $body->string('path'),
            $eventId,
            $occurredOn,
        );
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'alt' => $this->alt,
            'caption' => $this->caption,
            'path' => $this->path,
        ];
    }
}
