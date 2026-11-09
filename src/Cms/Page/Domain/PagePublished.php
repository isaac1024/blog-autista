<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedDomainEvent;
use Shared\Domain\RichText\RichText;

/**
 * A page went on the site, or its version there was replaced, with all the
 * site needs to serve it.
 *
 * It carries what the site shows, one parameter each. The home page has no
 * slug.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PagePublished extends RevisedDomainEvent
{
    /**
     * @param array{version: int, blocks: list<array<string, mixed>>} $content in the stored rich text format
     */
    public function __construct(
        string $aggregateId,
        private string $type,
        private string $title,
        private ?string $slug,
        private array $content,
        private string $metaDescription,
        private string $robots,
        private string $firstPublishedAt,
        private string $lastPublishedAt,
        string $eventId,
        \DateTimeImmutable $occurredOn,
        int $revision,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.page.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, \DateTimeImmutable $occurredOn): static
    {
        $body = new EventBody($body);

        return new static(
            $aggregateId,
            $body->string('type'),
            $body->string('title'),
            $body->nullableString('slug'),
            RichText::fromArray($body->array('content'))->toArray(),
            $body->string('meta_description'),
            $body->string('robots'),
            $body->string('first_published_at'),
            $body->string('last_published_at'),
            $eventId,
            $occurredOn,
            $body->integer('revision'),
        );
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'meta_description' => $this->metaDescription,
            'robots' => $this->robots,
            'first_published_at' => $this->firstPublishedAt,
            'last_published_at' => $this->lastPublishedAt,
            'revision' => $this->revision(),
        ];
    }
}
