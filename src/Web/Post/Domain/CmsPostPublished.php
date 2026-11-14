<?php

declare(strict_types=1);

namespace Web\Post\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;
use Shared\Domain\RichText\RichText;

/**
 * The CMS put a post on the site, or replaced its version there, with all the
 * site needs to serve it: where it is served, and the names of its section
 * and its author as they were then. The site declares it on its own, by the
 * name the CMS publishes it under.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 * @SuppressWarnings("PHPMD.TooManyFields")
 */
final readonly class CmsPostPublished extends RevisedReceivedEvent
{
    /**
     * @param list<string> $tagIds
     * @param string       $day    the day it was first published, in Madrid, as Y-m-d
     */
    public function __construct(
        string $aggregateId,
        private string $title,
        private string $slug,
        private string $sectionId,
        private string $sectionName,
        private string $sectionSlug,
        private string $authorId,
        private string $authorName,
        private array $tagIds,
        private RichText $content,
        private ?string $metaDescription,
        private Robots $robots,
        private string $day,
        private \DateTimeImmutable $firstPublishedAt,
        private \DateTimeImmutable $lastPublishedAt,
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.post.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);
        $metaDescription = $body->string('meta_description');

        return new static(
            $aggregateId,
            $body->string('title'),
            $body->string('slug'),
            $body->string('section_id'),
            $body->string('section_name'),
            $body->string('section_slug'),
            $body->string('author_id'),
            $body->string('author_name'),
            $body->stringList('tag_ids'),
            RichText::fromArray($body->array('content')),
            '' === $metaDescription ? null : $metaDescription,
            Robots::from($body->string('robots')),
            $body->string('day'),
            new \DateTimeImmutable($body->string('first_published_at')),
            new \DateTimeImmutable($body->string('last_published_at')),
            $body->integer('revision'),
        );
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function sectionId(): string
    {
        return $this->sectionId;
    }

    public function sectionName(): string
    {
        return $this->sectionName;
    }

    public function sectionSlug(): string
    {
        return $this->sectionSlug;
    }

    public function authorId(): string
    {
        return $this->authorId;
    }

    public function authorName(): string
    {
        return $this->authorName;
    }

    /**
     * @return list<string>
     */
    public function tagIds(): array
    {
        return $this->tagIds;
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

    public function day(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->day);
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
