<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * A post went on the site, or its version there was replaced, with all the
 * site needs to serve it: where it is served, and the name of its section
 * and of its author as they were then, which the site shows once either is
 * off it. Its tags and its opening image are told by id alone: the site
 * only shows those on it.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PostPublished extends RevisedDomainEvent
{
    /**
     * @param list<string>                                            $tagIds
     * @param array{version: int, blocks: list<array<string, mixed>>} $content in the stored rich text format
     * @param string                                                  $day     the day it was first published, in Madrid, as Y-m-d
     */
    public function __construct(
        string $aggregateId,
        private string $title,
        private string $slug,
        private string $sectionId,
        private string $sectionName,
        private string $authorId,
        private string $authorName,
        private array $tagIds,
        private string $openingImageId,
        private array $content,
        private string $metaDescription,
        private string $robots,
        private string $day,
        private string $firstPublishedAt,
        private string $lastPublishedAt,
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
    public function toPrimitives(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'section_id' => $this->sectionId,
            'section_name' => $this->sectionName,
            'author_id' => $this->authorId,
            'author_name' => $this->authorName,
            'tag_ids' => $this->tagIds,
            'opening_image_id' => $this->openingImageId,
            'content' => $this->content,
            'meta_description' => $this->metaDescription,
            'robots' => $this->robots,
            'day' => $this->day,
            'first_published_at' => $this->firstPublishedAt,
            'last_published_at' => $this->lastPublishedAt,
            'revision' => $this->revision(),
        ];
    }
}
