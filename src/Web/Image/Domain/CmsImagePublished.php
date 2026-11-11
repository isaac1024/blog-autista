<?php

declare(strict_types=1);

namespace Web\Image\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;
use Shared\Domain\RichText\RichText;

/**
 * The CMS put an image on the site, or replaced its version there, with all
 * the site needs to serve it. The site declares it on its own, by the name
 * the CMS publishes it under.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class CmsImagePublished extends RevisedReceivedEvent
{
    /**
     * @param string $path where the CMS keeps its file, relative to its image storage
     */
    public function __construct(
        string $aggregateId,
        private string $alt,
        private RichText $caption,
        private string $path,
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);

        return new static(
            $aggregateId,
            $body->string('alt'),
            RichText::fromArray($body->array('caption')),
            $body->string('path'),
            $body->integer('revision'),
        );
    }

    public function alt(): string
    {
        return $this->alt;
    }

    public function caption(): RichText
    {
        return $this->caption;
    }

    public function path(): string
    {
        return $this->path;
    }
}
