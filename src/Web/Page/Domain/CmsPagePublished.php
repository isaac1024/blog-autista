<?php

declare(strict_types=1);

namespace Web\Page\Domain;

use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBody;
use Shared\Domain\RichText\RichText;

/**
 * The CMS put a page on the site, or replaced its version there, with all the
 * site needs to serve it. The site declares it on its own, by the name the
 * CMS publishes it under.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class CmsPagePublished extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        private PageType $type,
        private string $title,
        private ?string $slug,
        private RichText $content,
        private ?string $metaDescription,
        private Robots $robots,
        private \DateTimeImmutable $firstPublishedAt,
        private \DateTimeImmutable $lastPublishedAt,
        string $eventId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
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
        $metaDescription = $body->string('meta_description');

        return new static(
            $aggregateId,
            PageType::from($body->string('type')),
            $body->string('title'),
            $body->nullableString('slug'),
            RichText::fromArray($body->array('content')),
            '' === $metaDescription ? null : $metaDescription,
            Robots::from($body->string('robots')),
            new \DateTimeImmutable($body->string('first_published_at')),
            new \DateTimeImmutable($body->string('last_published_at')),
            $eventId,
            $occurredOn,
        );
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content->toArray(),
            'meta_description' => (string) $this->metaDescription,
            'robots' => $this->robots->value,
            'first_published_at' => $this->firstPublishedAt->format(\DATE_ATOM),
            'last_published_at' => $this->lastPublishedAt->format(\DATE_ATOM),
        ];
    }

    public function type(): PageType
    {
        return $this->type;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * Null for the home page.
     */
    public function slug(): ?string
    {
        return $this->slug;
    }

    public function content(): RichText
    {
        return $this->content;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function robots(): Robots
    {
        return $this->robots;
    }

    public function firstPublishedAt(): \DateTimeImmutable
    {
        return $this->firstPublishedAt;
    }

    public function lastPublishedAt(): \DateTimeImmutable
    {
        return $this->lastPublishedAt;
    }
}
