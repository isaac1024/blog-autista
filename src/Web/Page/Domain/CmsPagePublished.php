<?php

declare(strict_types=1);

namespace Web\Page\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;
use Shared\Domain\RichText\RichText;
use Web\Shared\Domain\Robots;

/**
 * The CMS put a page on the site, or replaced its version there, with all the
 * site needs to serve it. The site declares it on its own, by the name the
 * CMS publishes it under.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class CmsPagePublished extends RevisedReceivedEvent
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
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.page.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
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
            $body->integer('revision'),
        );
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
